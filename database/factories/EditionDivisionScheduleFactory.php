<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EditionDivision;
use App\Models\EditionDivisionSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class EditionDivisionScheduleFactory extends Factory
{
    protected $model = EditionDivisionSchedule::class;

    public function definition()
    {
        return [
            'edition_division_id' => EditionDivision::factory(),
            'player_opponents_nr' => $this->faker->numberBetween(5, 9),
            'player_matches_repeat' => $this->faker->numberBetween(1, 2),
            'include_scores' => true,
            'has_demonstrative_match' => false,
            'allow_replacements' => true,
            'points_per_match' => null,
            'final_points_target' => 24,
            'include_final' => false,
            'template_version' => null,
            'adjust' => 0,
            'inserted_at' => time(),
        ];
    }
}
