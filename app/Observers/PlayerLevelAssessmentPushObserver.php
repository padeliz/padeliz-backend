<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\PlayerLevel\AssessmentReviewStatus;
use App\Models\PlayerLevelAssessment;
use App\Services\Push\ExpoPushService;

class PlayerLevelAssessmentPushObserver
{
    public function __construct(
        private readonly ExpoPushService $expoPushService,
    ) {
    }

    /**
     * Players create and discard their own assessments, so only a manager's decision is pushed.
     */
    public function updated(PlayerLevelAssessment $assessment): void
    {
        if (! $assessment->wasChanged('review_status')) {
            return;
        }

        $status = $assessment->review_status;
        if (! in_array($status, [AssessmentReviewStatus::Approved, AssessmentReviewStatus::Rejected], true)) {
            return;
        }

        $this->expoPushService->notifyLevelAssessmentStatus(
            (int) $assessment->player_id,
            $status->value
        );
    }
}
