<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserEmailFactory extends Factory
{
    protected $model = UserEmail::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'purposes' => ['login', 'contact'],
            'is_primary' => true,
            'verified_at' => $this->faker->optional()->numberBetween(time() - (365 * 24 * 60 * 60), time()),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
