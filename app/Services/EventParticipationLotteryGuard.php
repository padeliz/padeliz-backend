<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventLotteryLucky;

final class EventParticipationLotteryGuard
{
    public function hasLotteryLuckiesForEdition(int $editionId): bool
    {
        if ($editionId <= 0) {
            return false;
        }

        return EventLotteryLucky::query()
            ->whereHas('drawnParticipation', function ($q) use ($editionId) {
                $q->where('event_id', $editionId);
            })
            ->exists();
    }
}
