<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerIdentityFactory extends Factory
{
    protected $model = PlayerIdentity::class;

    public function definition()
    {
        return [
            'player_id' => Player::factory(),
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => $this->faker->unique()->uuid(),
            'email_id' => null,
            'phone_id' => null,
            'display_name' => $this->faker->name(),
            'avatar_url' => $this->faker->optional()->imageUrl(),
            'linked_at' => time(),
            'last_login_at' => $this->faker->optional()->numberBetween(time() - (30 * 24 * 60 * 60), time()),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
