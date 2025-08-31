<?php

namespace Database\Factories;

use App\Models\EditionLottery;
use App\Models\Edition;
use App\Models\Division;
use App\Models\Prize;
use Illuminate\Database\Eloquent\Factories\Factory;

class EditionLotteryFactory extends Factory
{
    protected $model = EditionLottery::class;

    public function definition()
    {
        return [
            'edition_id' => Edition::factory(),
            'orderliness' => $this->faker->numberBetween(0, 99),
            'division_id' => $this->faker->optional()->randomElement([Division::factory(), null]),
            'prize_id' => $this->faker->optional()->randomElement([Prize::factory(), null]),
            'total_draws_nr' => $this->faker->numberBetween(1, 10),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
