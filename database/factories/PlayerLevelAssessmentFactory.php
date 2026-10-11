<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlayerLevel\AssessmentReviewStatus;
use App\Models\User;
use App\Models\PlayerCustomFieldSubmission;
use App\Models\PlayerLevelAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerLevelAssessment>
 */
class PlayerLevelAssessmentFactory extends Factory
{
    protected $model = PlayerLevelAssessment::class;

    public function definition(): array
    {
        $player = User::factory();

        return [
            'player_user_id' => $player,
            'player_custom_field_submission_id' => PlayerCustomFieldSubmission::factory()->state([
                'player_user_id' => $player,
            ]),
            'submitted_at' => null,
            'resubmitted_at' => null,
            'ready_for_review' => false,
            'reviewed_at' => null,
            'review_status' => AssessmentReviewStatus::Pending,
            'review_message' => null,
            'inserted_at' => time(),
            'updated_at' => null,
        ];
    }

    public function forPlayer(User $player): static
    {
        return $this->state(fn (): array => [
            'player_user_id' => $player->id_user,
            'player_custom_field_submission_id' => PlayerCustomFieldSubmission::factory()->state([
                'player_user_id' => $player->id_user,
            ]),
        ]);
    }

    public function readyForReview(): static
    {
        return $this->state(fn (): array => [
            'ready_for_review' => true,
            'submitted_at' => time(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'ready_for_review' => true,
            'submitted_at' => time(),
            'reviewed_at' => time(),
            'review_status' => AssessmentReviewStatus::Approved,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'ready_for_review' => true,
            'submitted_at' => time(),
            'reviewed_at' => time(),
            'review_status' => AssessmentReviewStatus::Rejected,
            'review_message' => $this->faker->sentence(),
        ]);
    }

    public function discarded(): static
    {
        return $this->state(fn (): array => [
            'reviewed_at' => time(),
            'review_status' => AssessmentReviewStatus::Discarded,
        ]);
    }
}
