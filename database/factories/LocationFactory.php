<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition()
    {
        return [
            'club_id' => Club::factory(),
            'name' => substr($this->faker->company(), 0, 12) . ' ' . substr($this->faker->unique()->streetName(), 0, 12),
            'address' => substr($this->faker->address(), 0, 200),
            'total_indoors_courts' => $this->faker->numberBetween(3, 6),
            'total_outdoor_courts' => $this->faker->numberBetween(3, 6),
        ];
    }
}
