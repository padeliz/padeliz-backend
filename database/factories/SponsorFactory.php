<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Club;
use App\Models\Sponsor;
use Illuminate\Database\Eloquent\Factories\Factory;

class SponsorFactory extends Factory
{
    protected $model = Sponsor::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->company(),
            'club_id' => null,
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }

    public function forClub(Club $club)
    {
        return $this->state(function (array $attributes) use ($club) {
            return [
                'club_id' => $club->id_club,
                'name' => $club->name,
            ];
        });
    }
}
