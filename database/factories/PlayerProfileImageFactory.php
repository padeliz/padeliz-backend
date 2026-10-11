<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
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
            'player_user_id' => User::factory(),
            'profile_path' => 'player-profile-images/1/profile/placeholder.png',
            'sort_order' => 0,
            'is_pdf_primary' => false,
            'inserted_at' => time(),
            'updated_at' => time(),
        ];
    }
}
