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
            'name' => substr($this->faker->company(), 0, 30),
            'city' => substr($this->faker->city(), 0, 30),
        ];
    }
}
