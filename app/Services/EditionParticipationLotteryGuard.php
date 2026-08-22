<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EditionLotteryLucky;

final class EditionParticipationLotteryGuard
{
    public function hasLotteryLuckiesForEdition(int $editionId): bool
    {
        if ($editionId <= 0) {
            return false;
        }

        return EditionLotteryLucky::query()
            ->whereHas('drawnParticipation', function ($q) use ($editionId) {
                $q->where('edition_id', $editionId);
            })
            ->exists();
    }
}
