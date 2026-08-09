<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MatchesTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatchesTeamFactory extends Factory
{
    protected $model = MatchesTeam::class;

    public function definition()
    {
        return [
            'player_1' => $this->faker->unique()->firstName(),
            'player_2' => $this->faker->unique()->firstName(),
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
            'updated_at' => $this->faker->optional()->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
