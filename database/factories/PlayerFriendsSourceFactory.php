<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerFriendsSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerFriendsSource>
 */
class PlayerFriendsSourceFactory extends Factory
{
    protected $model = PlayerFriendsSource::class;

    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'friend_player_id' => null,
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->numerify('+40#########'),
            'inserted_at' => time(),
            'updated_at' => null,
        ];
    }

    public function linkedTo(Player $friend): static
    {
        return $this->state(fn (): array => [
            'friend_player_id' => $friend->id_player,
            'full_name' => $friend->name,
        ]);
    }
}
