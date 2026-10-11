<?php

declare(strict_types=1);

namespace App\Services\PlayerProfileImage;

use App\Models\CachedPdfPairImage;
use App\Models\CachedPdfPlayerImage;
use App\Models\User;
use App\Models\PlayerProfileImage;
use Illuminate\Support\Facades\DB;

final class PlayerProfileImagePrimaryService
{
    public function __construct(
        private readonly PlayerProfileImageResolver $resolver,
    ) {
    }

    public function setPdfPrimary(User $player, PlayerProfileImage $image): void
    {
        if ((int) $image->player_user_id !== (int) $player->id_user) {
            throw new \InvalidArgumentException('Profile image does not belong to this player.');
        }

        DB::transaction(function () use ($player, $image): void {
            PlayerProfileImage::query()
                ->where('player_user_id', $player->id_user)
                ->where('id', '!=', $image->id)
                ->update(['is_pdf_primary' => false]);

            $image->update(['is_pdf_primary' => true]);
            $this->bustPdfCache((int) $player->id_user);
        });
    }

    public function applyPrimaryOnUpload(User $player, PlayerProfileImage $image, bool $requestedPrimary): void
    {
        $existingCount = PlayerProfileImage::query()
            ->where('player_user_id', $player->id_user)
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

    public function promoteSurvivorAfterDelete(User $player): void
    {
        $player->load('profileImages');
        if ($player->profileImages->isEmpty()) {
            $this->bustPdfCache((int) $player->id_user);

            return;
        }

        PlayerProfileImage::query()
            ->where('player_user_id', $player->id_user)
            ->update(['is_pdf_primary' => false]);

        $next = $this->resolver->canonicalFirst($player->profileImages);
        if ($next !== null) {
            $next->update(['is_pdf_primary' => true]);
            $this->bustPdfCache((int) $player->id_user);
        }
    }

    public function wasResolvedPrimary(User $player, PlayerProfileImage $image): bool
    {
        $resolved = $this->resolver->resolveForPdf($player);

        return $resolved !== null && (int) $resolved->id === (int) $image->id;
    }

    public function handleAfterDelete(User $player, PlayerProfileImage $deleted): void
    {
        unset($deleted);
        $this->promoteSurvivorAfterDelete($player);
    }

    public function bustPdfCache(int $playerId): void
    {
        CachedPdfPlayerImage::query()
            ->where('player_user_id', $playerId)
            ->delete();

        CachedPdfPairImage::query()
            ->where(function ($query) use ($playerId): void {
                $query->where('player_1_user_id', $playerId)
                    ->orWhere('player_2_user_id', $playerId);
            })
            ->delete();
    }
}
