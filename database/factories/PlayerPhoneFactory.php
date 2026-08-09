<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerPhone;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerPhoneFactory extends Factory
{
    protected $model = PlayerPhone::class;

    public function definition()
    {
        return [
            'player_id' => Player::factory(),
            'phone' => $this->faker->unique()->e164PhoneNumber(),
            'purposes' => ['contact'],
            'is_primary' => true,
            'verified_at' => $this->faker->optional()->numberBetween(time() - (365 * 24 * 60 * 60), time()),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
