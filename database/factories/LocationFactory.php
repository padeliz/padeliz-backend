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
            'name' => substr($this->faker->unique()->words(2, true), 0, 30),
            'address' => substr($this->faker->address(), 0, 200),
            'total_indoors_courts' => $this->faker->numberBetween(3, 6),
            'total_outdoor_courts' => $this->faker->numberBetween(3, 6),
        ];
    }

    public function forClub(Club $club)
    {
        return $this->state(function (array $attributes) use ($club) {
            return [
                'club_id' => $club->id_club,
            ];
        });
    }
}
