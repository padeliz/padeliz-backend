<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Eligibility;
use App\Services\Push\ExpoPushService;

class EligibilityPushObserver
{
    public function __construct(
        private readonly ExpoPushService $expoPushService,
    ) {
    }

    public function created(Eligibility $eligibility): void
    {
        if ($eligibility->review_status !== null) {
            $this->expoPushService->notifyEligibilityStatus(
                (int) $eligibility->player_id,
                (string) $eligibility->review_status
            );
        }
    }

    public function updated(Eligibility $eligibility): void
    {
        if ($eligibility->wasChanged('review_status') && $eligibility->review_status !== null) {
            $this->expoPushService->notifyEligibilityStatus(
                (int) $eligibility->player_id,
                (string) $eligibility->review_status
            );
        }
    }
}
