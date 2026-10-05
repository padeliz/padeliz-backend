<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Eligibility;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class EligibilityFactory extends Factory
{
    protected $model = Eligibility::class;

    public function definition()
    {
        $now = time();

        return [
            'player_id' => Player::factory(),
            'event_ids' => null,
            'media_source' => null,
            'friends_source' => null,
            'racket_experience' => null,
            'phone' => $this->faker->phoneNumber(),
            'first_name' => $this->faker->firstName(),
            'middle_name' => null,
            'last_name' => $this->faker->lastName(),
            'year_of_birth' => $this->faker->numberBetween(1960, 2005),
            'birth_month' => $this->faker->numberBetween(1, 12),
            'birth_day' => $this->faker->numberBetween(1, 28),
            'being_student' => $this->faker->optional()->boolean(),
            'playtomic_id' => $this->faker->bothify('??#####'),
            'rankedin_id' => $this->faker->bothify('??######'),
            'instagram_username' => $this->faker->userName(),
            'facebook_username' => $this->faker->userName(),
            'submitted_at' => $now,
            'reviewed_at' => null,
            'ready_for_review' => 0,
            'review_status' => $this->faker->randomElement(['approved', 'pending', 'rejected', 'discarded']),
            'review_message' => null,
            'resubmitted_at' => null,
            'inserted_at' => $now,
            'updated_at' => null,
        ];
    }

    /**
     * Indicate that the eligibility is approved.
     */
    public function approved()
    {
        return $this->state(function (array $attributes) {
            return [
                'review_status' => 'approved',
                'reviewed_at' => time(),
            ];
        });
    }

    /**
     * Indicate that the eligibility is rejected.
     */
    public function rejected()
    {
        return $this->state(function (array $attributes) {
            return [
                'review_status' => 'rejected',
                'reviewed_at' => time(),
                'review_message' => $this->faker->sentence(),
            ];
        });
    }

    /**
     * Pending review (player may upload documents).
     */
    public function pending(): self
    {
        return $this->state(fn () => [
            'review_status' => 'pending',
            'reviewed_at' => null,
            'review_message' => null,
        ]);
    }
}
