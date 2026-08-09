<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerEmailFactory extends Factory
{
    protected $model = PlayerEmail::class;

    public function definition()
    {
        return [
            'player_id' => Player::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'purposes' => ['login', 'contact'],
            'is_primary' => true,
            'verified_at' => $this->faker->optional()->numberBetween(time() - (365 * 24 * 60 * 60), time()),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
