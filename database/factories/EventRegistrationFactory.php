<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\PadelEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventRegistrationFactory extends Factory
{
    protected $model = EventRegistration::class;

    public function definition()
    {
        $eventDivision = EventDivision::factory()->create();

        return [
            'event_id' => $eventDivision->event_id,
            'player_user_id' => User::factory(),
            'event_division_id' => $eventDivision->id_event_division,
            'is_minor' => false,
            'is_student' => $this->faker->boolean(30),
            'payment' => $this->faker->numberBetween(35, 55),
            'lifecycle_status' => RegistrationLifecycleStatus::Active,
            'payment_status' => RegistrationPaymentStatus::Unpaid,
            'payment_method' => null,
            'paid_at' => null,
            'payment_due_at' => time() + 86400,
            'inserted_at' => $this->faker->numberBetween(time() - (3 * 365 * 24 * 60 * 60), time()),
        ];
    }

    public function forEvent(PadelEvent $event): static
    {
        return $this->state(fn () => [
            'event_id' => $event->id_event,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'payment_status' => RegistrationPaymentStatus::Paid,
            'paid_at' => time(),
            'payment_due_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'lifecycle_status' => RegistrationLifecycleStatus::Cancelled,
            'cancelled_at' => time(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn () => [
            'lifecycle_status' => RegistrationLifecycleStatus::Declined,
            'declined_at' => time(),
        ]);
    }
}
