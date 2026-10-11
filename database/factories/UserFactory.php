<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserIdentity;
use App\Models\UserPhone;
use App\Models\PlayerProfileImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional(0.2)->firstName(),
            'last_name' => $this->faker->lastName(),
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
        return $this->afterCreating(function (User $player) {
            // Skip image creation in testing environment
            if (app()->environment('testing')) {
                return;
            }

            $disk = config('filesystems.user_uploads_disk', 'public');
            $numImages = \random_int(0, PlayerProfileImage::MAX_PER_PLAYER);

            for ($i = 0; $i < $numImages; $i++) {
                $image = PlayerProfileImage::create([
                    'player_user_id' => $player->id_user,
                    'profile_path' => '',
                    'sort_order' => $i,
                ]);

                $relativePath = 'player-profile-images/' . $image->getKey() . '/profile/' . Str::uuid()->toString() . '.jpg';
                Storage::disk($disk)->put($relativePath, ' ', ['visibility' => 'public']);
                $image->update(['profile_path' => $relativePath]);
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
     * Attach a primary login/contact email (opt-in).
     */
    public function withPrimaryEmail(?string $email = null)
    {
        return $this->afterCreating(function (User $player) use ($email) {
            if ($player->emails()->where('is_primary', true)->exists()) {
                return;
            }

            UserEmail::factory()->create([
                'user_id' => $player->id_user,
                'email' => $email ?? (Str::slug($player->name, '.') . '.' . $player->id_user . '@example.test'),
                'purposes' => ['login', 'contact'],
                'is_primary' => true,
            ]);
        });
    }

    /**
     * Attach a primary phone (opt-in).
     */
    public function withPrimaryPhone()
    {
        return $this->afterCreating(function (User $player) {
            if ($player->phones()->where('is_primary', true)->exists()) {
                return;
            }

            UserPhone::factory()->create([
                'user_id' => $player->id_user,
                'is_primary' => true,
            ]);
        });
    }

    /**
     * Attach primary email and phone (opt-in).
     */
    public function withContacts()
    {
        return $this->withPrimaryEmail()->withPrimaryPhone();
    }

    /**
     * Attach a Google identity linked to the player's primary email.
     * Creates a primary email when the player has none.
     */
    public function withGoogleIdentity(?string $providerSubject = null)
    {
        return $this->afterCreating(function (User $player) use ($providerSubject) {
            $email = $player->emails()->where('is_primary', true)->first()
                ?? $player->emails()->first();

            if ($email === null) {
                $email = UserEmail::factory()->create([
                    'user_id' => $player->id_user,
                    'email' => Str::slug($player->name, '.') . '.' . $player->id_user . '@example.test',
                    'purposes' => ['login', 'contact'],
                    'is_primary' => true,
                ]);
            }

            UserIdentity::factory()->create([
                'user_id' => $player->id_user,
                'provider' => UserIdentity::PROVIDER_GOOGLE,
                'provider_subject' => $providerSubject ?? ('google_' . $player->id_user . '_' . Str::random(8)),
                'email_id' => $email->id_user_email,
                'display_name' => $player->name,
            ]);
        });
    }
}
