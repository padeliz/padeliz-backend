<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->name(),
            'inserted_at' => $this->faker->unixTime(),
            'updated_at' => $this->faker->optional()->unixTime(),
        ];
    }
}
