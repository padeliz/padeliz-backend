<?php

namespace Database\Factories;

use App\Models\Edition;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class EditionFactory extends Factory
{
    protected $model = Edition::class;

    public function definition()
    {
        return [
            'name' => '#' . $this->faker->unique()->numberBetween(1, 1000),
            'date' => $this->faker->dateTimeBetween('-1 year', '+3 months'),
            'location_id' => Location::factory(),
            'entry_fee' => 55,
            'students_entry_fee' => 45,
            'arriving_time' => Carbon::createFromTime($this->faker->numberBetween(12, 19), $this->faker->randomElement([00, 30])),
            'closing_time' => Carbon::createFromTime($this->faker->numberBetween(16, 21), $this->faker->randomElement([00, 30])),
        ];
    }

    public function forLocation(Location $location)
    {
        return $this->state(function (array $attributes) use ($location) {
            return [
                'location_id' => $location->id_location,
            ];
        });
    }
}
