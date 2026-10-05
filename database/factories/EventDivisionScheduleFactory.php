<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventDivision;
use App\Models\EventDivisionSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventDivisionScheduleFactory extends Factory
{
    protected $model = EventDivisionSchedule::class;

    public function definition()
    {
        return [
            'event_division_id' => EventDivision::factory(),
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
