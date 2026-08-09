<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Court;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition()
    {
        return [
            'location_id' => Location::factory(),
            'name' => 'Court ' . $this->faker->unique()->numberBetween(1, 99),
            'is_indoor' => $this->faker->boolean(70),
            'display_order' => $this->faker->numberBetween(0, 10),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
