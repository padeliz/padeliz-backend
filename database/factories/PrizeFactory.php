<?php

namespace Database\Factories;

use App\Models\Prize;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrizeFactory extends Factory
{
    protected $model = Prize::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'image' => $this->faker->optional()->imageUrl(),
            'box_1_text' => $this->faker->sentence(),
            'box_1_bg_color' => $this->faker->hexColor(),
            'box_1_text_color' => $this->faker->hexColor(),
            'box_2_text' => $this->faker->optional()->sentence(),
            'box_2_bg_color' => $this->faker->optional()->hexColor(),
            'box_2_text_color' => $this->faker->optional()->hexColor(),
        ];
    }
}
