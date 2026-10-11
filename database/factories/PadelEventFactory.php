<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\PadelEvent;
use App\Services\EventDeadlineTimestamps;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class PadelEventFactory extends Factory
{
    protected $model = PadelEvent::class;

    public function definition()
    {
        $tz = (string) config('app.timezone', 'Europe/Bucharest');
        $day = $this->faker->dateTimeBetween('-1 year', '+3 months')->format('Y-m-d');
        $startHour = $this->faker->numberBetween(12, 18);
        $endHour = min(23, $startHour + $this->faker->numberBetween(2, 4));
        $startsAt = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%s %02d:%02d:00', $day, $startHour, $this->faker->randomElement([0, 30])),
            $tz
        )->timestamp;
        $endsAt = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%s %02d:%02d:00', $day, $endHour, $this->faker->randomElement([0, 30])),
            $tz
        )->timestamp;
        if ($endsAt <= $startsAt) {
            $endsAt = $startsAt + 3600;
        }

        return [
            'name' => '#' . $this->faker->unique()->numberBetween(1, 1000000),
            'description' => '<p>Update this description.</p>',
            'event_starts_at' => $startsAt,
            'event_ends_at' => $endsAt,
            'location_id' => Location::factory(),
            'finalized_at' => null,
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PadelEvent $event) {
            $startsAt = (int) $event->event_starts_at;
            $cap = max(0, $startsAt - 1);

            if ($event->registration_allowed_until === null) {
                $event->registration_allowed_until = EventDeadlineTimestamps::defaultDeadline($startsAt);
            }
            if ($event->cancellation_allowed_until === null) {
                $event->cancellation_allowed_until = $event->registration_allowed_until;
            }

            $registration = min((int) $event->registration_allowed_until, $cap);
            $cancellation = min((int) $event->cancellation_allowed_until, $registration);
            $event->registration_allowed_until = max(0, $registration);
            $event->cancellation_allowed_until = max(0, $cancellation);
        });
    }

    public function forLocation(Location $location)
    {
        return $this->state(function (array $attributes) use ($location) {
            return [
                'location_id' => $location->id_location,
            ];
        });
    }
}
