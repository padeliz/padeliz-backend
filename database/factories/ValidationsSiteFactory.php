<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ValidationsSite;
use Illuminate\Database\Eloquent\Factories\Factory;

class ValidationsSiteFactory extends Factory
{
    protected $model = ValidationsSite::class;

    public function definition()
    {
        return [
            'error' => $this->faker->unique()->words(3, true),
            'vars' => $this->faker->optional()->numberBetween(0, 10),
            'message_en' => $this->faker->paragraph(),
        ];
    }
}
