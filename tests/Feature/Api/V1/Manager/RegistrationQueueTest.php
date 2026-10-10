<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Enums\Registration\CheckoutStatus;
use App\Enums\Registration\PaymentAttemptStatus;
use App\Enums\Registration\PaymentProofReviewStatus;
use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\EventRegistrationCheckout;
use App\Models\EventRegistrationPaymentAttempt;
use App\Models\EventRegistrationPaymentProof;
use App\Models\PadelEvent;
use App\Models\Player;
use App\Services\Cache\PadelizCache;
use App\Services\RegistrationPayment\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\UsesRedisTaggedCache;
use Tests\TestCase;

class RegistrationQueueTest extends TestCase
{
    use RefreshDatabase;
    use UsesRedisTaggedCache;

    private Player $manager;

    private PadelEvent $event;

    private EventDivision $eventDivision;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forceRedisTaggedCache();

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        $this->event = PadelEvent::factory()->create([
            'date' => now()->addDays(30)->format('Y-m-d'),
        ]);
        $division = Division::factory()->create();
        $this->eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'division_title' => $division->name,
            'players_limit' => 50,
        ]);
    }

    public function test_checkout_queue_awaiting_proof_one_row_per_checkout(): void
    {
        Sanctum::actingAs($this->manager);

        $match = $this->makeRegistration([
            'payment_status' => RegistrationPaymentStatus::AwaitingProofReview,
        ], CheckoutStatus::AwaitingProof);
        $this->makeProof($match);

        $cancelled = $this->makeRegistration([
            'payment_status' => RegistrationPaymentStatus::AwaitingProofReview,
            'lifecycle_status' => RegistrationLifecycleStatus::Cancelled,
            'cancelled_at' => time(),
        ], CheckoutStatus::AwaitingProof);
        $this->makeProof($cancelled);

        $unpaid = $this->makeRegistration([
            'payment_status' => RegistrationPaymentStatus::Unpaid,
        ], CheckoutStatus::Open);

        $response = $this->getJson('/api/v1/manager/registration-checkouts?queue=awaiting_proof');
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id_checkout')->all();
        $matchCheckoutId = $this->checkoutId($match);
        $this->assertContains($matchCheckoutId, $ids);
        $this->assertNotContains($this->checkoutId($cancelled), $ids);
        $this->assertNotContains($this->checkoutId($unpaid), $ids);
        $this->assertCount(1, $ids);
    }

    public function test_checkout_queue_refund_pending_includes_cancelled(): void
    {
        Sanctum::actingAs($this->manager);

        $match = $this->makeRegistration([
            'payment_status' => RegistrationPaymentStatus::RefundPending,
            'lifecycle_status' => RegistrationLifecycleStatus::Cancelled,
            'cancelled_at' => time(),
        ], CheckoutStatus::Completed);

        $refunded = $this->makeRegistration([
            'payment_status' => RegistrationPaymentStatus::Refunded,
            'lifecycle_status' => RegistrationLifecycleStatus::Cancelled,
            'cancelled_at' => time(),
        ], CheckoutStatus::Completed);

        $response = $this->getJson('/api/v1/manager/registration-checkouts?queue=refund_pending');
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id_checkout')->all();
        $this->assertContains($this->checkoutId($match), $ids);
        $this->assertNotContains($this->checkoutId($refunded), $ids);
    }

    public function test_checkout_queue_abandoned_netopia(): void
    {
        Sanctum::actingAs($this->manager);

        $withPending = $this->makeRegistration(['payment_status' => RegistrationPaymentStatus::Unpaid], CheckoutStatus::Open);
        $this->makeAttempt($withPending, PaymentAttemptStatus::Pending);

        $startedOnly = $this->makeRegistration(['payment_status' => RegistrationPaymentStatus::Unpaid], CheckoutStatus::Open);
        $this->makeAttempt($startedOnly, PaymentAttemptStatus::Started);

        $response = $this->getJson('/api/v1/manager/registration-checkouts?queue=abandoned_netopia');
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id_checkout')->all();
        $this->assertContains($this->checkoutId($withPending), $ids);
        $this->assertNotContains($this->checkoutId($startedOnly), $ids);
    }

    public function test_registration_list_queue_still_filters_registrations(): void
    {
        Sanctum::actingAs($this->manager);

        $match = $this->makeRegistration([
            'payment_status' => RegistrationPaymentStatus::AwaitingProofReview,
        ], CheckoutStatus::AwaitingProof);

        $response = $this->getJson('/api/v1/manager/event-registrations?queue=awaiting_proof');
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id_registration')->all();
        $this->assertContains($match->id_registration, $ids);
    }

    public function test_invalid_queue_returns_422(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/manager/event-registrations?queue=not_a_queue')
            ->assertStatus(422);
        $this->getJson('/api/v1/manager/registration-checkouts?queue=not_a_queue')
            ->assertStatus(422);
    }

    public function test_checkout_index_without_queue_or_player_filter_returns_422(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/manager/registration-checkouts')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['queue']);
    }

    public function test_checkout_index_by_player_does_not_require_queue(): void
    {
        Sanctum::actingAs($this->manager);

        $open = $this->makeRegistration(['payment_status' => RegistrationPaymentStatus::Unpaid], CheckoutStatus::Open);
        $playerId = (int) $open->player_id;
        $secondDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => Division::factory()->create()->id_division,
            'division_title' => 'Second division',
            'players_limit' => 50,
        ]);
        $completed = $this->makeRegistration([
            'player_id' => $playerId,
            'event_division_id' => $secondDivision->id_event_division,
            'payment_status' => RegistrationPaymentStatus::Paid,
        ], CheckoutStatus::Completed);
        $otherPlayer = $this->makeRegistration();

        $response = $this->getJson('/api/v1/manager/registration-checkouts?filters[player_id]=' . $playerId);
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id_checkout')->all();
        $this->assertEqualsCanonicalizing(
            [$this->checkoutId($open), $this->checkoutId($completed)],
            $ids
        );
        $this->assertNotContains($this->checkoutId($otherPlayer), $ids);
        $this->assertSame(
            [$playerId],
            collect($response->json('data'))->pluck('player_id')->unique()->values()->all()
        );
    }

    public function test_checkout_index_by_player_skips_queue_triage_even_when_queue_is_sent(): void
    {
        Sanctum::actingAs($this->manager);

        $unpaid = $this->makeRegistration(['payment_status' => RegistrationPaymentStatus::Unpaid], CheckoutStatus::Open);

        $response = $this->getJson(
            '/api/v1/manager/registration-checkouts?queue=awaiting_proof&filters[player_id]=' . $unpaid->player_id
        );
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id_checkout')->all();
        $this->assertContains($this->checkoutId($unpaid), $ids);
    }

    public function test_checkout_index_by_player_with_no_checkouts_returns_empty_list(): void
    {
        Sanctum::actingAs($this->manager);

        $player = Player::factory()->create();

        $this->getJson('/api/v1/manager/registration-checkouts?filters[player_id]=' . $player->id_player)
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_checkout_index_rejects_non_numeric_player_filter(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/manager/registration-checkouts?filters[player_id]=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filters.player_id']);
    }

    public function test_index_without_queue_still_filters_by_event_id(): void
    {
        Sanctum::actingAs($this->manager);

        $inEdition = $this->makeRegistration();
        $otherEdition = PadelEvent::factory()->create([
            'date' => now()->addDays(40)->format('Y-m-d'),
        ]);
        $otherDivision = EventDivision::factory()->create([
            'event_id' => $otherEdition->id_event,
        ]);
        $other = EventRegistration::factory()->create([
            'event_id' => $otherEdition->id_event,
            'event_division_id' => $otherDivision->id_event_division,
            'lifecycle_status' => RegistrationLifecycleStatus::Active,
            'payment_status' => RegistrationPaymentStatus::Unpaid,
        ]);
        app(CheckoutService::class)->attachCompletedStub($other);

        $response = $this->getJson(
            '/api/v1/manager/event-registrations?filters[event_id]=' . $this->event->id_event
        );
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id_registration')->all();
        $this->assertContains($inEdition->id_registration, $ids);
        $this->assertNotContains($other->id_registration, $ids);
    }

    public function test_registration_queue_counts_are_checkout_grain(): void
    {
        Sanctum::actingAs($this->manager);

        $a = $this->makeRegistration(['payment_status' => RegistrationPaymentStatus::AwaitingProofReview], CheckoutStatus::AwaitingProof);
        $this->makeProof($a);
        $b = $this->makeRegistration(['payment_status' => RegistrationPaymentStatus::AwaitingProofReview], CheckoutStatus::AwaitingProof);
        $this->makeProof($b);
        $this->makeRegistration([
            'payment_status' => RegistrationPaymentStatus::RefundPending,
            'lifecycle_status' => RegistrationLifecycleStatus::Cancelled,
            'cancelled_at' => time(),
        ], CheckoutStatus::Completed);

        $abandoned = $this->makeRegistration(['payment_status' => RegistrationPaymentStatus::Unpaid], CheckoutStatus::Open);
        $this->makeAttempt($abandoned, PaymentAttemptStatus::Failed);

        $this->getJson('/api/v1/manager/registration-queue-counts')
            ->assertStatus(200)
            ->assertJsonPath('data.awaiting_proof', 2)
            ->assertJsonPath('data.refund_pending', 1)
            ->assertJsonPath('data.abandoned_netopia', 1);
    }

    public function test_counts_cache_invalidates_after_proof_review(): void
    {
        Sanctum::actingAs($this->manager);

        $registration = $this->makeRegistration([
            'payment_status' => RegistrationPaymentStatus::AwaitingProofReview,
            'payment' => 50,
        ], CheckoutStatus::AwaitingProof);
        $proof = $this->makeProof($registration);

        $this->getJson('/api/v1/manager/registration-queue-counts')
            ->assertJsonPath('data.awaiting_proof', 1);

        $this->postJson('/api/v1/manager/payment-proofs/' . $proof->id_payment_proof . '/approve')
            ->assertStatus(200);

        $this->getJson('/api/v1/manager/registration-queue-counts')
            ->assertJsonPath('data.awaiting_proof', 0);
    }

    public function test_show_includes_payment_attempt_error_message(): void
    {
        Sanctum::actingAs($this->manager);

        $registration = $this->makeRegistration();
        $attempt = $this->makeAttempt($registration, PaymentAttemptStatus::Failed);
        $attempt->error_message = 'Card declined by issuer';
        $attempt->save();

        $this->getJson('/api/v1/manager/event-registrations/' . $registration->id_registration)
            ->assertStatus(200)
            ->assertJsonPath('data.payment_attempts.0.error_message', 'Card declined by issuer');
    }

    public function test_counts_endpoint_uses_mgr_list_tag(): void
    {
        Sanctum::actingAs($this->manager);

        $cache = app(PadelizCache::class);
        $cache->flushApplicationTags();

        $this->getJson('/api/v1/manager/registration-queue-counts')->assertStatus(200);

        $this->assertTrue($cache->has(
            'mgr:registration-queue-counts',
            ['mgr-list:event-registrations']
        ));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRegistration(
        array $overrides = [],
        CheckoutStatus $checkoutStatus = CheckoutStatus::Open,
    ): EventRegistration {
        $registration = EventRegistration::factory()->create(array_merge([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_id' => Player::factory()->create()->id_player,
            'lifecycle_status' => RegistrationLifecycleStatus::Active,
            'payment_status' => RegistrationPaymentStatus::Unpaid,
            'payment' => 50,
        ], $overrides));

        $checkout = EventRegistrationCheckout::query()->create([
            'event_id' => $registration->event_id,
            'player_id' => $registration->player_id,
            'status' => $checkoutStatus,
            'total_amount' => $registration->amountDue(),
            'payment_method' => $registration->payment_method,
            'paid_at' => $registration->paid_at,
        ]);

        $registration->checkoutItems()->create([
            'checkout_id' => $checkout->id_checkout,
            'registration_id' => $registration->id_registration,
            'amount' => $registration->amountDue(),
            'item_status' => 'active',
        ]);

        return $registration->fresh();
    }

    private function checkoutId(EventRegistration $registration): int
    {
        return (int) $registration->checkoutItems()->value('checkout_id');
    }

    private function makeAttempt(
        EventRegistration $registration,
        PaymentAttemptStatus $status,
    ): EventRegistrationPaymentAttempt {
        return EventRegistrationPaymentAttempt::query()->create([
            'checkout_id' => $this->checkoutId($registration),
            'amount' => 50,
            'currency' => 'RON',
            'status' => $status,
            'order_id' => 'PAD-C' . $this->checkoutId($registration) . '-' . uniqid(),
        ]);
    }

    private function makeProof(EventRegistration $registration): EventRegistrationPaymentProof
    {
        return EventRegistrationPaymentProof::query()->create([
            'checkout_id' => $this->checkoutId($registration),
            'proof_path' => 'registration-payment-proofs/test.jpg',
            'review_status' => PaymentProofReviewStatus::Pending,
        ]);
    }
}
