<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventDivision;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventParticipationFactory extends Factory
{
    protected $model = EventParticipation::class;

    public function definition()
    {
        $eventDivision = EventDivision::factory()->create();

        return [
            'event_id' => $eventDivision->event_id,
            'player_id' => Player::factory(),
            'event_division_id' => $eventDivision->id_event_division,
            'sort_order' => 0,
            'is_collecting_points' => true,
            'absence_reason' => $this->faker->optional()->randomElement(['SICK', 'FORGOT', 'EXAMS', 'RECONSIDERED']),
            'ban_reason' => $this->faker->optional()->randomElement(['LEFT SUDDENLY', 'AGGRESSIVE PLAYER', 'LIBIDINOUS', '']),
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }

    public function forEvent(PadelEvent $event): static
    {
        return $this->state(fn () => [
            'event_id' => $event->id_event,
        ]);
    }
}
