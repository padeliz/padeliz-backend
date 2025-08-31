<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Club;
use App\Models\Location;
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
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
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
