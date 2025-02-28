<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'phone' => $this->faker->phoneNumber,
            'google_id' => $this->faker->unique()->uuid,
            'google_name' => $this->faker->name,
            'google_email' => $this->faker->email,
            'google_avatar' => $this->faker->imageUrl(300, 300, 'people'),
            'inserted_at' => now()->timestamp,
            'updated_at' => now()->timestamp,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Player $player) {
            // Create profile directories
            $profilePath = $player->getUploadPath('avatar');
            File::makeDirectory($profilePath, 0755, true, true);

            // Download profile from pravatar.cc
            $profileUrl = "https://i.pravatar.cc/300?u=" . $player->id_player;
            $newProfilePath = $profilePath . '/avatar-' . strtolower(str_replace(' ', '-', $player->name)) . '.jpg';

            $imageContent = file_get_contents($profileUrl);
            if ($imageContent !== false) {
                File::put($newProfilePath, $imageContent);
            }
        });
    }
}
