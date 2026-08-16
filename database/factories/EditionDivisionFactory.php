<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class EditionDivisionFactory extends Factory
{
    protected $model = EditionDivision::class;

    public function definition()
    {
        $division = Division::factory()->create();

        return [
            'edition_id' => Edition::factory(),
            'division_id' => $division->id_division,
            'division_title' => $division->name,
            'players_limit' => $this->faker->numberBetween(8, 16),
            'playing_start_time' => Carbon::createFromTime($this->faker->numberBetween(12, 19), 30),
            'playing_end_time' => Carbon::createFromTime($this->faker->numberBetween(16, 21), 30),
            'player_opponents_nr' => $this->faker->numberBetween(5, 9),
            'player_matches_repeat' => $this->faker->numberBetween(1, 2),
            'include_scores' => $this->faker->boolean(80),
            'has_demonstrative_match' => $this->faker->boolean(20),
            'allow_replacements' => $this->faker->boolean(80),
            'points_per_match' => $this->faker->optional()->numberBetween(1, 3),
            'final_points_target' => 24,
            'include_final' => $this->faker->boolean(30),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }
}
