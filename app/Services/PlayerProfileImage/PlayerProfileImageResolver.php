<?php

declare(strict_types=1);

namespace App\Services\PlayerProfileImage;

use App\Models\User;
use App\Models\PlayerProfileImage;
use Illuminate\Support\Collection;

final class PlayerProfileImageResolver
{
    public function resolveForPdf(User $player): ?PlayerProfileImage
    {
        $player->loadMissing('profileImages');
        $images = $player->profileImages;

        if ($images->isEmpty()) {
            return null;
        }

        $flagged = $images->where('is_pdf_primary', true);
        $pool = $flagged->isNotEmpty() ? $flagged : $images;

        return $this->canonicalFirst($pool);
    }

    /**
     * @param  Collection<int, PlayerProfileImage>  $images
     */
    public function canonicalFirst(Collection $images): ?PlayerProfileImage
    {
        if ($images->isEmpty()) {
            return null;
        }

        return $images->sortBy([
            ['sort_order', 'asc'],
            ['id', 'asc'],
        ])->first();
    }
}
