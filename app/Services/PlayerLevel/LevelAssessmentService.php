<?php

declare(strict_types=1);

namespace App\Services\PlayerLevel;

use App\Enums\PlayerCustomFieldValueReviewStatus;
use App\Enums\PlayerLevel\AssessmentReviewStatus;
use App\Models\User;
use App\Models\PlayerCustomFieldSubmission;
use App\Models\PlayerCustomFieldValue;
use App\Models\PlayerLevel;
use App\Models\PlayerLevelAssessment;
use App\Models\PlayerProfileImage;
use App\Services\PlayerProfileImage\PlayerProfileImagePrimaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Players own the creation of assessments; managers only ever review an existing one.
 */
final class LevelAssessmentService
{
    public function __construct(
        private readonly PlayerLevelService $playerLevels,
    ) {
    }

    /**
     * One pending assessment per player is enforced by a generated unique index.
     */
    public function ensurePending(User $player, string $submittedVia = PlayerCustomFieldSubmission::SUBMITTED_VIA_APP): PlayerLevelAssessment
    {
        $pending = $this->getPending((int) $player->id_user);
        if ($pending !== null) {
            return $pending;
        }

        return DB::transaction(function () use ($player, $submittedVia): PlayerLevelAssessment {
            $submission = PlayerCustomFieldSubmission::query()->create([
                'player_user_id' => $player->id_user,
                'submitted_via' => $submittedVia,
                'submitted_by_player_user_id' => $player->id_user,
            ]);

            return PlayerLevelAssessment::query()->create([
                'player_user_id' => $player->id_user,
                'player_custom_field_submission_id' => $submission->id_player_custom_field_submission,
                'ready_for_review' => false,
                'review_status' => AssessmentReviewStatus::Pending,
            ]);
        });
    }

    public function getPending(int $playerId): ?PlayerLevelAssessment
    {
        return PlayerLevelAssessment::query()
            ->where('player_user_id', $playerId)
            ->where('review_status', AssessmentReviewStatus::Pending->value)
            ->first();
    }

    /**
     * Hands the assessment over to the manager queue; the second time around counts as a resubmit.
     */
    public function markReady(PlayerLevelAssessment $assessment): PlayerLevelAssessment
    {
        $this->assertPending($assessment);

        $now = time();
        $assessment->ready_for_review = true;
        if ($assessment->submitted_at === null) {
            $assessment->submitted_at = $now;
        } else {
            $assessment->resubmitted_at = $now;
        }
        $assessment->save();

        return $assessment;
    }

    /**
     * Pulls the assessment back out of the review queue so the player can keep editing.
     */
    public function reopenPending(PlayerLevelAssessment $assessment): PlayerLevelAssessment
    {
        $this->assertPending($assessment);

        $assessment->ready_for_review = false;
        $assessment->save();

        return $assessment;
    }

    public function discardPending(PlayerLevelAssessment $assessment, ?string $message = null): PlayerLevelAssessment
    {
        $this->assertPending($assessment);

        return DB::transaction(function () use ($assessment, $message): PlayerLevelAssessment {
            $player = User::query()->find((int) $assessment->player_user_id);
            $images = PlayerProfileImage::query()
                ->where('uploaded_via_level_assessment_id', $assessment->id_player_level_assessment)
                ->get();

            if ($player !== null && $images->isNotEmpty()) {
                $primaryService = app(PlayerProfileImagePrimaryService::class);
                $needsPromote = false;

                foreach ($images as $image) {
                    if ($primaryService->wasResolvedPrimary($player, $image)) {
                        $needsPromote = true;
                    }
                    $image->delete();
                }

                if ($needsPromote) {
                    $player->load('profileImages');
                    $primaryService->promoteSurvivorAfterDelete($player);
                }
            }

            return $this->close($assessment, AssessmentReviewStatus::Discarded, $message, null);
        });
    }

    public function approve(
        PlayerLevelAssessment $assessment,
        float|string $level,
        ?string $message = null,
        ?int $reviewedByManagerId = null,
    ): PlayerLevelAssessment {
        $this->assertPending($assessment);

        return DB::transaction(function () use ($assessment, $level, $message, $reviewedByManagerId): PlayerLevelAssessment {
            $this->close($assessment, AssessmentReviewStatus::Approved, $message, $reviewedByManagerId);
            $this->playerLevels->upsertForAssessment(
                (int) $assessment->player_user_id,
                (int) $assessment->id_player_level_assessment,
                $level
            );

            return $assessment;
        });
    }

    public function reject(
        PlayerLevelAssessment $assessment,
        ?string $message = null,
        ?int $reviewedByManagerId = null,
    ): PlayerLevelAssessment {
        $this->assertPending($assessment);

        return $this->close($assessment, AssessmentReviewStatus::Rejected, $message, $reviewedByManagerId);
    }

    public function levelFor(PlayerLevelAssessment $assessment): ?PlayerLevel
    {
        return PlayerLevel::query()
            ->where('player_level_assessment_id', $assessment->id_player_level_assessment)
            ->first();
    }

    /**
     * Closes the assessment and flips the submission's still-pending values with it. Values that
     * already left `pending` (e.g. manager-entered ones) are never touched. Only approve and reject
     * carry a reviewing manager; a player discard does not.
     */
    private function close(
        PlayerLevelAssessment $assessment,
        AssessmentReviewStatus $status,
        ?string $message,
        ?int $reviewedByManagerId,
    ): PlayerLevelAssessment {
        return DB::transaction(function () use ($assessment, $status, $message, $reviewedByManagerId): PlayerLevelAssessment {
            $now = time();

            $assessment->review_status = $status;
            $assessment->reviewed_at = $now;
            if ($message !== null) {
                $assessment->review_message = $message;
            }
            $assessment->save();

            PlayerCustomFieldValue::query()
                ->where('submission_id', $assessment->player_custom_field_submission_id)
                ->where('review_status', PlayerCustomFieldValueReviewStatus::Pending->value)
                ->update([
                    'review_status' => $this->valueStatusFor($status)->value,
                    'reviewed_at' => $now,
                    'reviewed_by_manager_user_id' => $reviewedByManagerId,
                ]);

            return $assessment;
        });
    }

    private function valueStatusFor(AssessmentReviewStatus $status): PlayerCustomFieldValueReviewStatus
    {
        return match ($status) {
            AssessmentReviewStatus::Approved => PlayerCustomFieldValueReviewStatus::Validated,
            AssessmentReviewStatus::Rejected => PlayerCustomFieldValueReviewStatus::Rejected,
            AssessmentReviewStatus::Discarded => PlayerCustomFieldValueReviewStatus::Discarded,
            AssessmentReviewStatus::Pending => PlayerCustomFieldValueReviewStatus::Pending,
        };
    }

    private function assertPending(PlayerLevelAssessment $assessment): void
    {
        if ($assessment->review_status !== AssessmentReviewStatus::Pending) {
            throw ValidationException::withMessages([
                'review_status' => ['This assessment has already been reviewed.'],
            ]);
        }
    }
}
