<?php

namespace Database\Factories;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClubFactory extends Factory
{
    protected $model = Club::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->company(),
            'city' => $this->faker->randomElement(['Bucharest', 'Cluj', 'Timisoara', 'Iasi']),
        ];
    }
}
