<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;

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
            'google_avatar' => 'https://i.pravatar.cc/300?u=' . $this->faker->uuid,
            'skill_division' => $this->faker->randomFloat(1, 1, 5),
            'first_name' => $this->faker->optional()->firstName,
            'middle_name' => $this->faker->optional()->firstName,
            'last_name' => $this->faker->optional()->lastName,
            'year_of_birth' => $this->faker->optional()->year,
            'being_student' => $this->faker->boolean,
            'playtomic_id' => $this->faker->optional()->bothify('??#####'),
            'rankedin_id' => $this->faker->optional()->bothify('??######'),
            'instagram_username' => $this->faker->optional()->userName,
            'facebook_username' => $this->faker->optional()->userName,
            'user_types' => ['player'], // Default to player
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
            'updated_at' => $this->faker->optional()->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure()
    {
        return $this->afterCreating(function (Player $player) {
            // Skip image creation in testing environment
            if (app()->environment('testing')) {
                return;
            }

            // Create between 0 and 3 profile images
            $numImages = rand(0, 3);

            if ($numImages > 0) {
                // Create profile directory
                $profilePath = $player->getUploadPath('images');
                File::makeDirectory($profilePath, 0755, true, true);
            }

            for ($i = 0; $i < $numImages; $i++) {
                $profileUrl = 'https://i.pravatar.cc/300?u=' . $player->id_player . '-' . $i;
                $newProfilePath = $profilePath . '/image-' . strtolower(str_replace(' ', '-', $player->name)) . '-' . $i . '.jpg';

                $imageContent = file_get_contents($profileUrl);
                if ($imageContent !== false) {
                    File::put($newProfilePath, $imageContent);
                }
            }
        });
    }

    /**
     * Indicate that the player is a manager.
     */
    public function manager()
    {
        return $this->state(function (array $attributes) {
            return [
                'user_types' => ['player', 'manager'],
            ];
        });
    }

    /**
     * Indicate that the player is an admin.
     */
    public function admin()
    {
        return $this->state(function (array $attributes) {
            return [
                'user_types' => ['player', 'manager', 'admin'],
            ];
        });
    }

    /**
     * Indicate that the player is only a player (no manager/admin).
     */
    public function playerOnly()
    {
        return $this->state(function (array $attributes) {
            return [
                'user_types' => ['player'],
            ];
        });
    }

    /**
     * Indicate that the player is only a manager (no player/admin).
     */
    public function managerOnly()
    {
        return $this->state(function (array $attributes) {
            return [
                'user_types' => ['manager'],
            ];
        });
    }
}
