<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Edition;
use App\Models\EditionLottery;
use Illuminate\Database\Eloquent\Factories\Factory;

class EditionLotteryFactory extends Factory
{
    protected $model = EditionLottery::class;

    public function definition()
    {
        return [
            'edition_id' => Edition::factory(),
            'name' => $this->faker->unique()->words(3, true),
            'display_order' => $this->faker->numberBetween(0, 20),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
