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
        $date = $this->faker->dateTimeBetween('-1 year', '+3 months')->format('Y-m-d');

        return [
            'name' => '#' . $this->faker->unique()->numberBetween(1, 1000000),
            'description' => '<p>Update this description.</p>',
            'cover_path' => 'events/factory/cover.png',
            'date' => $date,
            'location_id' => Location::factory(),
            'arriving_time' => Carbon::createFromTime($this->faker->numberBetween(12, 19), $this->faker->randomElement([00, 30]))->format('H:i:s'),
            'closing_time' => Carbon::createFromTime($this->faker->numberBetween(16, 21), $this->faker->randomElement([00, 30]))->format('H:i:s'),
            'finalized_at' => null,
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PadelEvent $event) {
            $dateYmd = $event->date instanceof \DateTimeInterface
                ? $event->date->format('Y-m-d')
                : substr((string) $event->date, 0, 10);
            // Signed INT columns cannot store post-Y2038 unix timestamps.
            $intMax = 2147483647;
            $cap = min(EventDeadlineTimestamps::startOfEditionDay($dateYmd), $intMax);

            if ($event->registration_allowed_until === null) {
                $event->registration_allowed_until = EventDeadlineTimestamps::defaultDeadline($dateYmd);
            }
            if ($event->cancellation_allowed_until === null) {
                $event->cancellation_allowed_until = $event->registration_allowed_until;
            }

            $registration = min((int) $event->registration_allowed_until, $cap, $intMax);
            $cancellation = min((int) $event->cancellation_allowed_until, $registration, $intMax);
            $event->registration_allowed_until = $registration;
            $event->cancellation_allowed_until = $cancellation;
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
