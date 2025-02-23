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
            'name' => $this->faker->unique()->company() . ' ' . $this->faker->unique()->streetName(),
            'address' => $this->faker->address(),
            'total_indoors_courts' => $this->faker->numberBetween(3, 6),
            'total_outdoor_courts' => $this->faker->numberBetween(3, 6),
        ];
    }
}
