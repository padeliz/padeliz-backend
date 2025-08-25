<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Edition;
use App\Models\PlayerEligibility;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerEligibilityFactory extends Factory
{
    protected $model = PlayerEligibility::class;

    public function definition()
    {
        return [
            'edition_id' => Edition::factory(),
            'player_id' => Player::factory(),
            'name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'skill_division' => $this->faker->randomFloat(1, 1, 9.9),
            'first_name' => $this->faker->firstName(),
            'middle_name' => null,
            'last_name' => $this->faker->lastName(),
            'year_of_birth' => $this->faker->numberBetween(1960, 2005),
            'being_student' => $this->faker->boolean(),
            'playtomic_id' => $this->faker->bothify('??#####'),
            'rankedin_id' => $this->faker->bothify('??######'),
            'instagram_username' => $this->faker->userName(),
            'facebook_username' => $this->faker->userName(),
            'reviewed_at' => null,
            'review_status' => $this->faker->randomElement(['approved', 'pending', 'rejected']),
            'review_message' => null,
            'resubmitted_at' => null,
            'inserted_at' => time(),
            'updated_at' => time()
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
                'reviewed_at' => time()
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
                'review_message' => $this->faker->sentence()
            ];
        });
    }
}
