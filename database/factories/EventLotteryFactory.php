<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PadelEvent;
use App\Models\EventLottery;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventLotteryFactory extends Factory
{
    protected $model = EventLottery::class;

    public function definition()
    {
        return [
            'event_id' => PadelEvent::factory(),
            'name' => $this->faker->unique()->words(3, true),
            'display_order' => $this->faker->numberBetween(0, 20),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
