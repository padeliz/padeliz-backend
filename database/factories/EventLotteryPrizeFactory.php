<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventLottery;
use App\Models\EventLotteryPrize;
use App\Models\Prize;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventLotteryPrizeFactory extends Factory
{
    protected $model = EventLotteryPrize::class;

    public function definition()
    {
        return [
            'lottery_id' => EventLottery::factory(),
            'prize_id' => Prize::factory(),
            'prize_quantity' => $this->faker->numberBetween(1, 10),
            'sponsor_id' => null,
            'display_order' => $this->faker->numberBetween(0, 20),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
