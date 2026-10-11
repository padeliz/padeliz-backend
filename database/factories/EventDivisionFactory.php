<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Division;
use App\Models\EventDivision;
use App\Models\PadelEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventDivisionFactory extends Factory
{
    protected $model = EventDivision::class;

    public function definition()
    {
        $division = Division::factory()->create();

        return [
            'event_id' => PadelEvent::factory(),
            'division_id' => $division->id_division,
            'division_title' => $division->name,
            'players_limit' => $this->faker->numberBetween(8, 16),
            'entry_fee' => 55,
            'skill_order' => 0,
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (EventDivision $eventDivision) {
            $event = null;
            if (! empty($eventDivision->event_id)) {
                $event = PadelEvent::query()->find($eventDivision->event_id);
            }

            $starts = (int) ($eventDivision->playing_starts_at ?? ($event?->event_starts_at ?? (time() + 3600)));
            $ends = (int) ($eventDivision->playing_ends_at ?? ($event?->event_ends_at ?? ($starts + 4 * 3600)));
            if ($ends <= $starts) {
                $ends = $starts + 3600;
            }
            $eventDivision->playing_starts_at = $starts;
            $eventDivision->playing_ends_at = $ends;

            if (! array_key_exists('check_in_at', $eventDivision->getAttributes())) {
                $eventDivision->check_in_at = (int) ($event?->event_starts_at ?? $starts);
            }
        });
    }
}
