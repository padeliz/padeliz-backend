<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlayerLevel\AssessmentReviewStatus;
use App\Models\Player;
use App\Models\PlayerStudentProof;
use App\Models\PlayerStudentProofFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PlayerStudentProof>
 */
class PlayerStudentProofFactory extends Factory
{
    protected $model = PlayerStudentProof::class;

    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'student_until' => Carbon::now()->addMonths(6)->format('Y-m-d'),
            'sort_order' => 0,
            'ready_for_review' => false,
            'reviewed_at' => null,
            'review_status' => AssessmentReviewStatus::Pending,
            'review_message' => null,
            'submitted_at' => null,
            'inserted_at' => time(),
            'updated_at' => null,
        ];
    }

    public function withFiles(int $count = 1): static
    {
        return $this->has(
            PlayerStudentProofFile::factory()
                ->count($count)
                ->sequence(fn (Sequence $sequence): array => ['sort_order' => $sequence->index]),
            'files'
        );
    }

    public function approved(?string $studentUntil = null): static
    {
        return $this->state(fn (): array => [
            'ready_for_review' => true,
            'submitted_at' => time(),
            'reviewed_at' => time(),
            'review_status' => AssessmentReviewStatus::Approved,
            'student_until' => $studentUntil ?? Carbon::now()->addMonths(6)->format('Y-m-d'),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'ready_for_review' => true,
            'submitted_at' => time(),
            'reviewed_at' => time(),
            'review_status' => AssessmentReviewStatus::Approved,
            'student_until' => Carbon::now()->subDay()->format('Y-m-d'),
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
}
