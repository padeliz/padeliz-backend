<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MediaSource;
use App\Models\Player;
use App\Models\PlayerMediaSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerMediaSource>
 */
class PlayerMediaSourceFactory extends Factory
{
    protected $model = PlayerMediaSource::class;

    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'media_source_id' => MediaSource::factory(),
            'inserted_at' => time(),
            'updated_at' => null,
        ];
    }
}
