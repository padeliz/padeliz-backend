<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerEmail;
use App\Models\PlayerIdentity;
use App\Models\PlayerPhone;
use App\Models\PlayerProfileImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition()
    {
        return [
            'skill_division' => $this->faker->randomFloat(1, 1, 5),
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional(0.2)->firstName(),
            'last_name' => $this->faker->lastName(),
            'year_of_birth' => $this->faker->optional()->year(),
            'being_student' => $this->faker->boolean(),
            'playtomic_id' => $this->faker->optional()->bothify('??#####'),
            'rankedin_id' => $this->faker->optional()->bothify('??######'),
            'instagram_username' => $this->faker->optional()->userName(),
            'facebook_username' => $this->faker->optional()->userName(),
            'user_types' => ['player'],
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
            // Contacts for auth/smoke tests (identity is opt-in via withGoogleIdentity()).
            PlayerEmail::factory()->create([
                'player_id' => $player->id_player,
                'email' => Str::slug($player->name, '.') . '.' . $player->id_player . '@example.test',
                'purposes' => ['login', 'contact'],
                'is_primary' => true,
            ]);

            PlayerPhone::factory()->create([
                'player_id' => $player->id_player,
                'is_primary' => true,
            ]);

            // Skip image creation in testing environment
            if (app()->environment('testing')) {
                return;
            }

            $disk = config('filesystems.user_uploads_disk', 'public');
            $numImages = \random_int(0, PlayerProfileImage::MAX_PER_PLAYER);

            for ($i = 0; $i < $numImages; $i++) {
                $relativePath = 'players/' . $player->getKey() . '/profile/' . Str::uuid()->toString() . '.jpg';
                Storage::disk($disk)->put($relativePath, ' ', ['visibility' => 'public']);

                PlayerProfileImage::create([
                    'player_id' => $player->id_player,
                    'path' => $relativePath,
                    'sort_order' => $i,
                ]);
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

    /**
     * Attach a Google identity linked to the player's primary email.
     */
    public function withGoogleIdentity(?string $providerSubject = null)
    {
        return $this->afterCreating(function (Player $player) use ($providerSubject) {
            $email = $player->emails()->where('is_primary', true)->first()
                ?? $player->emails()->first();

            PlayerIdentity::factory()->create([
                'player_id' => $player->id_player,
                'provider' => PlayerIdentity::PROVIDER_GOOGLE,
                'provider_subject' => $providerSubject ?? ('google_' . $player->id_player . '_' . Str::random(8)),
                'email_id' => $email?->id_player_email,
                'display_name' => $player->name,
            ]);
        });
    }
}
