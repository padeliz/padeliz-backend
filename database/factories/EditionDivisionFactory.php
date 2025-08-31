<?php

namespace Database\Factories;

use App\Models\EditionDivision;
use App\Models\Edition;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class EditionDivisionFactory extends Factory
{
    protected $model = EditionDivision::class;

    public function definition()
    {
        return [
            'edition_id' => Edition::factory(),
            'division_id' => Division::factory(),
            'players_limit' => $this->faker->numberBetween(8, 16),
            'court' => 'Court ' . $this->faker->numberBetween(1, 3),
            'playing_start_time' => Carbon::createFromTime($this->faker->numberBetween(12, 19), 30),
            'playing_end_time' => Carbon::createFromTime($this->faker->numberBetween(16, 21), 30),
            'player_opponents_nr' => $this->faker->numberBetween(5, 9),
            'player_matches_repeat' => $this->faker->numberBetween(1, 2),
            'include_scores' => $this->faker->boolean(80),
            'has_demonstrative_match' => $this->faker->boolean(20),
            'fixed_teams' => $this->faker->boolean(20),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
