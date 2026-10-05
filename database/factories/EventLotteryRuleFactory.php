<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventLotteryPrize;
use App\Models\EventLotteryRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventLotteryRuleFactory extends Factory
{
    protected $model = EventLotteryRule::class;

    public function definition()
    {
        return [
            'lottery_prize_id' => EventLotteryPrize::factory(),
            'rule_prize_quantity' => $this->faker->numberBetween(1, 5),
            'orderliness' => $this->faker->numberBetween(0, 99),
            'event_division_id' => null,
            'gender' => $this->faker->optional()->randomElement(['MALE', 'FEMALE']),
            'has_social_status' => $this->faker->optional()->randomElement(['STUDENT', 'ADULT']),
            'legal_age' => $this->faker->optional()->randomElement(['MINOR', 'MAJOR']),
            'is_younger_than' => null,
            'is_older_than' => null,
            'has_tshirt_size' => $this->faker->optional()->randomElement(['XS', 'S', 'M', 'L', 'XL', 'XXL']),
            'is_guest' => $this->faker->optional()->boolean(),
            'is_padel_first_timer' => $this->faker->optional()->boolean(),
            'is_event_first_timer' => $this->faker->optional()->boolean(),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
