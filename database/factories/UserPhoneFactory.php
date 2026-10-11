<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPhone;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserPhoneFactory extends Factory
{
    protected $model = UserPhone::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'phone' => $this->faker->unique()->e164PhoneNumber(),
            'purposes' => ['contact'],
            'is_primary' => true,
            'verified_at' => $this->faker->optional()->numberBetween(time() - (365 * 24 * 60 * 60), time()),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
