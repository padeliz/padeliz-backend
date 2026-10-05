<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\GameMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameMatchFactory extends Factory
{
    protected $model = GameMatch::class;

    public function definition()
    {
        $eventDivision = EventDivision::factory()->create();
        $eventDivisionCourt = EventDivisionCourt::factory()->create([
            'event_division_id' => $eventDivision->id_event_division,
        ]);

        return [
            'event_division_id' => $eventDivision->id_event_division,
            'event_division_court_id' => $eventDivisionCourt->id_event_division_court,
            'match_kind' => $this->faker->randomElement(['REGULAR', 'FINAL', 'DEMONSTRATIVE']),
            'sequence_no' => $this->faker->numberBetween(1, 20),
            'scheduled_start' => null,
            'scheduled_end' => null,
            'points_target' => $this->faker->optional()->numberBetween(10, 24),
            'inserted_at' => time(),
        ];
    }
}
