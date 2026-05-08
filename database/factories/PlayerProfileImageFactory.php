<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerProfileImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerProfileImage>
 */
class PlayerProfileImageFactory extends Factory
{
    protected $model = PlayerProfileImage::class;

    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'path' => 'players/1/profile/placeholder.png',
            'sort_order' => 0,
            'inserted_at' => time(),
            'updated_at' => time(),
        ];
    }
}
