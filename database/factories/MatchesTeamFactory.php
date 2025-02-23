<?php

namespace Database\Factories;

use App\Models\MatchesTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatchesTeamFactory extends Factory
{
    protected $model = MatchesTeam::class;

    public function definition()
    {
        return [
            'player_1' => $this->faker->name(),
            'player_2' => $this->faker->name(),
            'inserted_at' => $this->faker->unixTime(),
            'updated_at' => $this->faker->optional()->unixTime(),
        ];
    }
}
