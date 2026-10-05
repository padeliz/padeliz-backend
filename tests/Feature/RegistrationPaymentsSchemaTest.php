<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationPaymentsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_payment_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('events', 'registration_allowed_until'));
        $this->assertTrue(Schema::hasColumn('events', 'cancellation_allowed_until'));
        $this->assertFalse(Schema::hasColumn('events', 'registration_cancel_until'));
        $this->assertTrue(Schema::hasColumn('event_registrations', 'lifecycle_status'));
        $this->assertTrue(Schema::hasColumn('event_registrations', 'payment_status'));
        $this->assertTrue(Schema::hasColumn('event_registrations', 'payment_method'));
        $this->assertTrue(Schema::hasColumn('event_registrations', 'paid_at'));
        $this->assertTrue(Schema::hasColumn('event_registrations', 'cancel_reason'));
        $this->assertTrue(Schema::hasColumn('event_registrations', 'decline_reason'));
        $this->assertTrue(Schema::hasColumn('event_registrations', 'active_player_for_division'));
        $this->assertTrue(Schema::hasColumn('event_registrations', 'active_player_for_event'));

        $this->assertTrue(Schema::hasTable('event_registration_payment_attempts'));
        $this->assertTrue(Schema::hasColumn('event_registration_payment_attempts', 'checkout_id'));
        $this->assertFalse(Schema::hasColumn('event_registration_payment_attempts', 'registration_id'));
        $this->assertTrue(Schema::hasTable('event_registration_payment_proofs'));
        $this->assertTrue(Schema::hasColumn('event_registration_payment_proofs', 'checkout_id'));
        $this->assertFalse(Schema::hasColumn('event_registration_payment_proofs', 'registration_id'));
        $this->assertTrue(Schema::hasTable('event_registration_checkouts'));
        $this->assertTrue(Schema::hasTable('event_registration_checkout_items'));
        $this->assertTrue(Schema::hasTable('event_registration_payment_refunds'));
        $this->assertTrue(Schema::hasTable('bank_transfer_settings'));
        $this->assertTrue(Schema::hasTable('player_device_tokens'));
    }

    public function test_active_uniqueness_allows_rejoin_after_cancel(): void
    {
        $division = EventDivision::factory()->create();
        $player = Player::factory()->create();

        $first = EventRegistration::factory()->create([
            'event_id' => $division->event_id,
            'event_division_id' => $division->id_event_division,
            'player_id' => $player->id_player,
            'lifecycle_status' => RegistrationLifecycleStatus::Active,
            'payment_status' => RegistrationPaymentStatus::Unpaid,
        ]);

        $first->update([
            'lifecycle_status' => RegistrationLifecycleStatus::Cancelled,
            'cancelled_at' => time(),
        ]);

        $second = EventRegistration::factory()->create([
            'event_id' => $division->event_id,
            'event_division_id' => $division->id_event_division,
            'player_id' => $player->id_player,
            'lifecycle_status' => RegistrationLifecycleStatus::Active,
            'payment_status' => RegistrationPaymentStatus::Unpaid,
        ]);

        $this->assertNotSame($first->id_registration, $second->id_registration);
        $this->assertSame(2, EventRegistration::query()->where('player_id', $player->id_player)->count());
    }

    public function test_factory_defaults_to_active_unpaid(): void
    {
        $registration = EventRegistration::factory()->create();

        $this->assertSame(RegistrationLifecycleStatus::Active, $registration->lifecycle_status);
        $this->assertSame(RegistrationPaymentStatus::Unpaid, $registration->payment_status);
    }
}
