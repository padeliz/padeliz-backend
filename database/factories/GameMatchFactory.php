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
        return [
            'event_division_court_id' => EventDivisionCourt::factory(),
            'event_division_id' => fn (array $attributes) => EventDivisionCourt::query()
                ->whereKey($attributes['event_division_court_id'])
                ->value('event_division_id'),
            'match_kind' => $this->faker->randomElement(['REGULAR', 'FINAL', 'DEMONSTRATIVE']),
            'sequence_no' => $this->faker->numberBetween(1, 20),
            'scheduled_start' => null,
            'scheduled_end' => null,
            'points_target' => $this->faker->optional()->numberBetween(10, 24),
            'inserted_at' => time(),
        ];
    }

    public function forEventDivision(EventDivision $eventDivision): static
    {
        return $this->state(fn () => [
            'event_division_id' => $eventDivision->id_event_division,
            'event_division_court_id' => EventDivisionCourt::factory()->state([
                'event_division_id' => $eventDivision->id_event_division,
            ]),
        ]);
    }
}
