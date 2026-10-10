<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\CachedPdfPairImage;
use App\Models\CachedPdfPlayerImage;
use App\Models\Player;

/**
 * Shared resolve → compose → cleanup for PDF cached player/pair images.
 */
class PdfCachedImageGenerator
{
    public function __construct(
        private readonly PdfPlayerPhotoForCacheResolver $photoResolver = new PdfPlayerPhotoForCacheResolver(),
        private readonly CachedPdfPlayerImageService $playerImages = new CachedPdfPlayerImageService(),
        private readonly CachedPdfPairImageService $pairImages = new CachedPdfPairImageService(),
    ) {
    }

    /**
     * @return string Absolute path of composed player avatar (Mode A persisted or Mode B temp)
     */
    public function generatePlayerAvatar(
        Player $player,
        bool $collecting,
        bool $persist = true,
    ): string {
        $resolved = $this->photoResolver->resolve($player);
        $temps = $resolved->isEphemeral() ? [$resolved->absolutePath] : [];

        try {
            return $this->playerImages->resolvePath(
                (int) $player->id_player,
                $resolved->absolutePath,
                $collecting,
                $persist,
                $resolved->sourcePlayerProfileImageId,
                $this->playerUpdatedAt($player),
            );
        } finally {
            $this->unlinkTemps($temps);
        }
    }

    /**
     * Persist Mode A player avatar and return public URL.
     */
    public function generatePlayerAvatarUrl(Player $player, bool $collecting): ?string
    {
        $this->generatePlayerAvatar($player, $collecting, true);

        $cached = CachedPdfPlayerImage::query()
            ->where('player_id', (int) $player->id_player)
            ->where('player_is_collecting_points', $collecting)
            ->first();

        return $cached?->url;
    }

    /**
     * @return string Absolute path of composed pair image
     */
    public function generatePairImage(
        Player $player1,
        Player $player2,
        bool $player1Collecting,
        bool $player2Collecting,
        bool $persist = true,
    ): string {
        $resolved1 = $this->photoResolver->resolve($player1);
        $resolved2 = $this->photoResolver->resolve($player2);
        $temps = [];
        if ($resolved1->isEphemeral()) {
            $temps[] = $resolved1->absolutePath;
        }
        if ($resolved2->isEphemeral()) {
            $temps[] = $resolved2->absolutePath;
        }

        try {
            return $this->pairImages->resolvePath(
                (int) $player1->id_player,
                (int) $player2->id_player,
                $resolved1->absolutePath,
                $resolved2->absolutePath,
                $player1Collecting,
                $player2Collecting,
                $persist,
                $resolved1->sourcePlayerProfileImageId,
                $resolved2->sourcePlayerProfileImageId,
                $this->playerUpdatedAt($player1),
                $this->playerUpdatedAt($player2),
            );
        } finally {
            $this->unlinkTemps($temps);
        }
    }

    /**
     * Persist Mode A pair image and return public URL.
     */
    public function generatePairImageUrl(
        Player $player1,
        Player $player2,
        bool $player1Collecting,
        bool $player2Collecting,
    ): ?string {
        // A reversed pair is served as a mirrored temp copy; only the persisted row matters here.
        $composed = $this->generatePairImage($player1, $player2, $player1Collecting, $player2Collecting, true);
        $this->unlinkIfTemp($composed);

        $idA = (int) $player1->id_player;
        $idB = (int) $player2->id_player;
        if ($idA <= $idB) {
            $p1 = $idA;
            $p2 = $idB;
            $c1 = $player1Collecting;
            $c2 = $player2Collecting;
        } else {
            $p1 = $idB;
            $p2 = $idA;
            $c1 = $player2Collecting;
            $c2 = $player1Collecting;
        }

        $cached = CachedPdfPairImage::query()
            ->where('player_1_id', $p1)
            ->where('player_2_id', $p2)
            ->where('player_1_is_collecting_points', $c1)
            ->where('player_2_is_collecting_points', $c2)
            ->first();

        return $cached?->url;
    }

    /**
     * Resolve photo for a player payload used by PosterPdfService (tracks ephemeral temps for later cleanup).
     *
     * @param  list<string>  $ephemeralTemps
     * @return array{absolute_path: string, source_player_profile_image_id: ?int}
     */
    public function resolvePhotoForPoster(Player $player, array &$ephemeralTemps): array
    {
        $resolved = $this->photoResolver->resolve($player);
        if ($resolved->isEphemeral()) {
            $ephemeralTemps[] = $resolved->absolutePath;
        }

        return [
            'absolute_path' => $resolved->absolutePath,
            'source_player_profile_image_id' => $resolved->sourcePlayerProfileImageId,
        ];
    }

    /**
     * @param  list<string>  $paths
     */
    public function unlinkTemps(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function unlinkIfTemp(string $path): void
    {
        if (str_starts_with($path, storage_path('app/tmp'))) {
            $this->unlinkTemps([$path]);
        }
    }

    private function playerUpdatedAt(Player $player): ?int
    {
        $value = $player->updated_at;
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
