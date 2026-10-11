<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Enums\Registration\CheckoutItemStatus;
use App\Enums\Registration\CheckoutStatus;
use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\PlayerLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

class MultiDivisionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $player;

    private PadelEvent $event;

    private EventDivision $divisionA;

    private EventDivision $divisionB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        Http::fake([
            'https://secure.sandbox.netopia-payments.com/payment/card/start' => Http::response([
                'payment' => [
                    'ntpID' => 'ntp-1',
                    'paymentURL' => 'https://secure-sandbox.netopia-payments.com/ui/card?p=test',
                ],
            ], 200),
            'https://secure.sandbox.netopia-payments.com/payment/card/refund' => Http::response([
                'refundID' => 'ref-1',
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
        $this->divisionA = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => Division::factory()->create()->id_division,
            'players_limit' => 10,
            'entry_fee' => 50,
        ]);
        $this->divisionB = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => Division::factory()->create()->id_division,
            'players_limit' => 10,
            'entry_fee' => 50,
        ]);
        PlayerLevel::factory()->level('4.50')->create(['player_user_id' => $this->player->id_user]);
    }

    public function test_multi_create_opens_one_checkout_with_fee_items(): void
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_ids' => [
                $this->divisionA->id_event_division,
                $this->divisionB->id_event_division,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data.registrations')
            ->assertJsonPath('data.checkout.total_amount', 100)
            ->assertJsonPath('data.checkout.status', 'open');

        $this->assertDatabaseCount('event_registration_checkouts', 1);
        $this->assertDatabaseCount('event_registration_checkout_items', 2);
    }

    public function test_capacity_fail_rolls_back_multi_create(): void
    {
        Sanctum::actingAs($this->player);
        $this->divisionB->update(['players_limit' => 0]);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_ids' => [
                $this->divisionA->id_event_division,
                $this->divisionB->id_event_division,
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('event_registrations', 0);
        $this->assertDatabaseCount('event_registration_checkouts', 0);
    }

    public function test_netopia_ipn_pays_all_payable_items(): void
    {
        Sanctum::actingAs($this->player);
        $checkoutId = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_ids' => [
                $this->divisionA->id_event_division,
                $this->divisionB->id_event_division,
            ],
        ])->json('data.checkout.id_checkout');

        $orderId = $this->postJson("/api/v1/app/registration-checkouts/{$checkoutId}/payments/netopia/start")
            ->assertStatus(200)
            ->json('data.attempt.order_id');

        $this->assertStringStartsWith('PAD-C' . $checkoutId . '-', $orderId);

        $this->postJson('/api/v1/webhooks/netopia/ipn', [
            'posSignature' => 'TEST-POS-SIGNATURE',
            'orderID' => $orderId,
            'ntpID' => 'ntp-1',
            'status' => 3,
            'error' => ['code' => '00'],
        ])->assertStatus(200);

        $this->assertSame(2, EventRegistration::query()->where('payment_status', 'paid')->count());
        $this->assertDatabaseHas('event_registration_checkouts', [
            'id_checkout' => $checkoutId,
            'status' => CheckoutStatus::Completed->value,
        ]);
    }

    public function test_exclude_while_awaiting_resets_remaining(): void
    {
        Sanctum::actingAs($this->player);
        $payload = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_ids' => [
                $this->divisionA->id_event_division,
                $this->divisionB->id_event_division,
            ],
        ])->json('data');

        $checkoutId = $payload['checkout']['id_checkout'];
        $regA = $payload['registrations'][0]['id_registration'];
        $regB = $payload['registrations'][1]['id_registration'];

        $this->post("/api/v1/app/registration-checkouts/{$checkoutId}/payments/bank-proofs", [
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->postJson("/api/v1/app/registration-checkouts/{$checkoutId}/exclude", [
            'registration_ids' => [$regA],
            'cancel_reason' => 'change_of_plans',
        ])->assertStatus(200)
            ->assertJsonPath('data.checkout.status', 'open')
            ->assertJsonPath('data.checkout.total_amount', 50);

        $this->assertDatabaseHas('event_registrations', [
            'id_registration' => $regA,
            'lifecycle_status' => RegistrationLifecycleStatus::Cancelled->value,
        ]);
        $this->assertDatabaseHas('event_registrations', [
            'id_registration' => $regB,
            'payment_status' => RegistrationPaymentStatus::Unpaid->value,
        ]);
        $this->assertDatabaseHas('event_registration_checkout_items', [
            'registration_id' => $regA,
            'item_status' => CheckoutItemStatus::Cancelled->value,
        ]);
    }

    public function test_exclude_all_unpaid_does_not_throw_binding_resolution_exception(): void
    {
        Sanctum::actingAs($this->player);
        $payload = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_ids' => [
                $this->divisionA->id_event_division,
                $this->divisionB->id_event_division,
            ],
        ])->json('data');

        $checkoutId = $payload['checkout']['id_checkout'];
        $regIds = collect($payload['registrations'])->pluck('id_registration')->all();

        $this->postJson("/api/v1/app/registration-checkouts/{$checkoutId}/exclude", [
            'registration_ids' => $regIds,
            'cancel_reason' => 'change_of_plans',
        ])->assertStatus(200)
            ->assertJsonPath('data.checkout.status', 'open');

        foreach ($regIds as $regId) {
            $this->assertDatabaseHas('event_registrations', [
                'id_registration' => $regId,
                'lifecycle_status' => RegistrationLifecycleStatus::Cancelled->value,
            ]);
        }
    }

    public function test_paid_netopia_cancel_partial_refunds_line(): void
    {
        Sanctum::actingAs($this->player);
        $payload = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_ids' => [
                $this->divisionA->id_event_division,
                $this->divisionB->id_event_division,
            ],
        ])->json('data');
        $checkoutId = $payload['checkout']['id_checkout'];
        $regA = $payload['registrations'][0]['id_registration'];
        $regB = $payload['registrations'][1]['id_registration'];

        $orderId = $this->postJson("/api/v1/app/registration-checkouts/{$checkoutId}/payments/netopia/start")
            ->json('data.attempt.order_id');
        $this->postJson('/api/v1/webhooks/netopia/ipn', [
            'posSignature' => 'TEST-POS-SIGNATURE',
            'orderID' => $orderId,
            'ntpID' => 'ntp-1',
            'status' => 3,
            'error' => ['code' => '00'],
        ])->assertStatus(200);

        $this->deleteJson("/api/v1/app/event-registrations/{$regA}", [
            'cancel_reason' => 'change_of_plans',
        ])->assertStatus(200);

        $this->assertDatabaseHas('event_registrations', [
            'id_registration' => $regA,
            'lifecycle_status' => 'cancelled',
            'payment_status' => 'refunded',
        ]);
        $this->assertDatabaseHas('event_registrations', [
            'id_registration' => $regB,
            'lifecycle_status' => 'active',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('event_registration_payment_refunds', [
            'amount' => 50,
            'status' => 'succeeded',
        ]);
    }

    public function test_add_while_awaiting_rejected(): void
    {
        Sanctum::actingAs($this->player);
        $checkoutId = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_ids' => [$this->divisionA->id_event_division],
        ])->json('data.checkout.id_checkout');

        $this->post("/api/v1/app/registration-checkouts/{$checkoutId}/payments/bank-proofs", [
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_ids' => [$this->divisionB->id_event_division],
        ])->assertStatus(422);
    }

    public function test_after_completed_new_unpaid_opens_new_checkout(): void
    {
        Sanctum::actingAs($this->player);
        $first = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_ids' => [$this->divisionA->id_event_division],
        ])->json('data');
        $checkoutId = $first['checkout']['id_checkout'];
        $orderId = $this->postJson("/api/v1/app/registration-checkouts/{$checkoutId}/payments/netopia/start")
            ->json('data.attempt.order_id');
        $this->postJson('/api/v1/webhooks/netopia/ipn', [
            'posSignature' => 'TEST-POS-SIGNATURE',
            'orderID' => $orderId,
            'ntpID' => 'ntp-1',
            'status' => 3,
            'error' => ['code' => '00'],
        ]);

        $second = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_ids' => [$this->divisionB->id_event_division],
        ])->assertStatus(201)->json('data');

        $this->assertNotSame($checkoutId, $second['checkout']['id_checkout']);
        $this->assertSame('open', $second['checkout']['status']);
        $this->assertDatabaseCount('event_registration_checkouts', 2);
    }
}
