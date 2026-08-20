<?php

declare(strict_types=1);

namespace App\Services\PlayerProfileImage;

use App\Models\CachedPdfPlayerImage;
use App\Models\Player;
use App\Models\PlayerProfileImage;
use Illuminate\Support\Facades\DB;

final class PlayerProfileImagePrimaryService
{
    public function __construct(
        private readonly PlayerProfileImageResolver $resolver,
    ) {
    }

    public function setPdfPrimary(Player $player, PlayerProfileImage $image): void
    {
        if ((int) $image->player_id !== (int) $player->id_player) {
            throw new \InvalidArgumentException('Profile image does not belong to this player.');
        }

        DB::transaction(function () use ($player, $image): void {
            PlayerProfileImage::query()
                ->where('player_id', $player->id_player)
                ->where('id', '!=', $image->id)
                ->update(['is_pdf_primary' => false]);

            $image->update(['is_pdf_primary' => true]);
            $this->bustPdfCache((int) $player->id_player);
        });
    }

    public function applyPrimaryOnUpload(Player $player, PlayerProfileImage $image, bool $requestedPrimary): void
    {
        $existingCount = PlayerProfileImage::query()
            ->where('player_id', $player->id_player)
            ->where('id', '!=', $image->id)
            ->count();

        if ($existingCount === 0) {
            $image->update(['is_pdf_primary' => true]);

            return;
        }

        if ($requestedPrimary) {
            $this->setPdfPrimary($player, $image);

            return;
        }

        $image->update(['is_pdf_primary' => false]);
    }

    public function promoteSurvivorAfterDelete(Player $player): void
    {
        $player->load('profileImages');
        if ($player->profileImages->isEmpty()) {
            return;
        }

        PlayerProfileImage::query()
            ->where('player_id', $player->id_player)
            ->update(['is_pdf_primary' => false]);

        $next = $this->resolver->canonicalFirst($player->profileImages);
        if ($next !== null) {
            $next->update(['is_pdf_primary' => true]);
            $this->bustPdfCache((int) $player->id_player);
        }
    }

    public function wasResolvedPrimary(Player $player, PlayerProfileImage $image): bool
    {
        $resolved = $this->resolver->resolveForPdf($player);

        return $resolved !== null && (int) $resolved->id === (int) $image->id;
    }

    public function handleAfterDelete(Player $player, PlayerProfileImage $deleted): void
    {
        unset($deleted);
        $this->promoteSurvivorAfterDelete($player);
    }

    public function bustPdfCache(int $playerId): void
    {
        CachedPdfPlayerImage::query()
            ->where('player_id', $playerId)
            ->delete();
    }
}
