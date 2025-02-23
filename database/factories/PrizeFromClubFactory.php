<?php

namespace Database\Factories;

use App\Models\PrizeFromClub;
use App\Models\Club;
use App\Models\Prize;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrizeFromClubFactory extends Factory
{
    protected $model = PrizeFromClub::class;

    public function definition()
    {
        return [
            'club_id' => Club::factory(),
            'prize_id' => Prize::factory(),
            'image' => $this->faker->optional()->imageUrl(),
            'box_1_text' => $this->faker->optional()->sentence(),
            'box_1_bg_color' => $this->faker->optional()->hexColor(),
            'box_1_text_color' => $this->faker->optional()->hexColor(),
            'box_2_text' => $this->faker->optional()->sentence(),
            'box_2_bg_color' => $this->faker->optional()->hexColor(),
            'box_2_text_color' => $this->faker->optional()->hexColor(),
        ];
    }
}
