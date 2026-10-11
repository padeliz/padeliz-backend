<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventParticipation;
use App\Models\EventRegistration;
use App\Models\EventRegistrationPaymentAttempt;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\UserEmail;
use App\Models\PlayerLevel;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

class BenefitsReferralAndPaymentWindowTest extends TestCase
{
    use RefreshDatabase;

    private User $player;

    private PadelEvent $event;

    private EventDivision $eventDivision;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'https://secure.sandbox.netopia-payments.com/*' => Http::response([
                'payment' => [
                    'ntpID' => '3040894',
                    'paymentURL' => 'https://secure-sandbox.netopia-payments.com/ui/card?p=test',
                ],
                'customerAction' => [],
            ], 200),
            'https://exp.host/*' => Http::response(['data' => []], 200),
        ]);

        config([
            'netopia.pos_signature' => 'TEST-POS-SIGNATURE',
            'netopia.api_key' => 'ApiKey_test',
            'netopia.is_live' => false,
            'netopia.notify_url' => 'https://example.test/api/v1/webhooks/netopia/ipn',
            'netopia.return_url' => 'https://example.test/api/v1/payments/netopia/return',
        ]);

        $this->player = User::factory()->playerOnly()->create();
        $this->event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(now()->addDays(30)->format('Y-m-d')),
            'registration_allowed_until' => time() + 86400,
            'cancellation_allowed_until' => time() + 86400,
        ]);
        $division = Division::factory()->create();
        $this->eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'division_title' => $division->name,
            'players_limit' => 10,
            'entry_fee' => 55,
        ]);
        PlayerLevel::factory()->level('4.50')->create(['player_user_id' => $this->player->id_user]);
    }

    public function test_unpaid_create_sets_payment_due_at_and_snapshots_division_fee(): void
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.payment', 55)
            ->assertJsonPath('data.payment_status', 'unpaid');

        $this->assertNotNull($response->json('data.payment_due_at'));
        $due = (int) $response->json('data.payment_due_at');
        $this->assertGreaterThan(time() + 86000, $due);
        $this->assertLessThanOrEqual(time() + 86400 + 5, $due);
    }

    public function test_free_division_is_paid_immediately_with_null_payment_due_at(): void
    {
        $this->eventDivision->update(['entry_fee' => 0]);
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payment_due_at', null);
    }

    public function test_player_netopia_blocked_after_payment_due(): void
    {
        Sanctum::actingAs($this->player);
        $created = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);

        $reg = EventRegistration::query()->findOrFail($created->json('data.id_registration'));
        $reg->payment_due_at = time() - 10;
        $reg->save();
        $checkoutId = $created->json('data.checkout_id');

        $this->postJson("/api/v1/app/registration-checkouts/{$checkoutId}/payments/netopia/start")
            ->assertStatus(422);
    }

    public function test_late_ipn_allowed_when_attempt_created_before_due(): void
    {
        Sanctum::actingAs($this->player);
        $created = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);

        $checkoutId = (int) $created->json('data.checkout_id');
        $start = $this->postJson("/api/v1/app/registration-checkouts/{$checkoutId}/payments/netopia/start")
            ->assertStatus(200);
        $orderId = (string) ($start->json('data.attempt.order_id') ?? '');

        $attempt = EventRegistrationPaymentAttempt::query()
            ->where('checkout_id', $checkoutId)
            ->latest('id_payment_attempt')
            ->first();
        $this->assertNotNull($attempt);
        if ($orderId === '') {
            $orderId = (string) $attempt->order_id;
        }

        $now = time();
        $attempt->inserted_at = $now - 120;
        $attempt->save();

        $reg = EventRegistration::query()->findOrFail($created->json('data.id_registration'));
        $reg->payment_due_at = $now - 30;
        $reg->save();

        $this->postJson('/api/v1/webhooks/netopia/ipn', [
            'orderID' => $orderId,
            'ntpID' => '3040894',
            'status' => 3,
            'error' => ['code' => '00'],
            'posSignature' => 'TEST-POS-SIGNATURE',
        ])->assertStatus(200);

        $this->assertSame(
            RegistrationPaymentStatus::Paid,
            $reg->fresh()->payment_status
        );
    }

    public function test_manager_mark_paid_still_works_after_payment_due(): void
    {
        Sanctum::actingAs($this->player);
        $created = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);

        $reg = EventRegistration::query()->findOrFail($created->json('data.id_registration'));
        $reg->payment_due_at = time() - 10;
        $reg->save();

        app(\App\Services\RegistrationPayment\RegistrationService::class)
            ->markPaid($reg, 'bank_transfer');

        $reg->refresh();
        $this->assertSame(RegistrationPaymentStatus::Paid, $reg->payment_status);
        $this->assertNull($reg->payment_due_at);
    }

    public function test_checkout_item_stores_base_amount_ron_from_division_fee(): void
    {
        Sanctum::actingAs($this->player);
        $id = (int) $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->json('data.id_registration');

        $this->assertDatabaseHas('event_registration_checkout_items', [
            'registration_id' => $id,
            'base_amount_ron' => 55,
            'amount' => 55,
        ]);
    }

    public function test_referral_code_requires_clean_participation_and_allowlist(): void
    {
        Sanctum::actingAs($this->player);

        $this->postJson("/api/v1/app/events/{$this->event->id_event}/referral-codes", [
            'invitees' => [['email' => 'friend@example.com']],
        ])->assertStatus(422);

        EventParticipation::factory()->create([
            'event_id' => PadelEvent::factory()->create(UnixEventFactory::eventBounds(now()->subDays(10)->format('Y-m-d')))->id_event,
            'player_user_id' => $this->player->id_user,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);

        $this->postJson("/api/v1/app/events/{$this->event->id_event}/referral-codes", [
            'invitees' => [['email' => 'friend@example.com']],
        ])->assertStatus(201)
            // Slots are consumed on pending/confirmed attribution, not on code create.
            ->assertJsonPath('data.remaining_slots', 6);
    }

    public function test_touchpoint_sets_discount_eligible_on_invitee_match(): void
    {
        $referrer = User::factory()->playerOnly()->create();
        EventParticipation::factory()->create([
            'event_id' => PadelEvent::factory()->create(UnixEventFactory::eventBounds(now()->subDays(5)->format('Y-m-d')))->id_event,
            'player_user_id' => $referrer->id_user,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);

        $code = app(ReferralService::class)->createCode($referrer, (int) $this->event->id_event, [
            ['email' => 'newbie@example.com'],
        ]);

        UserEmail::factory()->create([
            'user_id' => $this->player->id_user,
            'email' => 'newbie@example.com',
        ]);

        Sanctum::actingAs($this->player);
        $this->postJson('/api/v1/app/referral-touchpoints', [
            'event_id' => $this->event->id_event,
            'code' => $code->code,
        ])->assertStatus(201)
            ->assertJsonPath('data.discount_eligible', true);
    }
}
