<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Edition;
use App\Models\EditionLottery;
use App\Models\Prize;
use Illuminate\Database\Eloquent\Factories\Factory;

class EditionLotteryFactory extends Factory
{
    protected $model = EditionLottery::class;

    public function definition()
    {
        return [
            'edition_id' => Edition::factory(),
            'prize_id' => $this->faker->optional()->randomElement([Prize::factory(), null]),
            'prize_quantity' => $this->faker->numberBetween(1, 10),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
