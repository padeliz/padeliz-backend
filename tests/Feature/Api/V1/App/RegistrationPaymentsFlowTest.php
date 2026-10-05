<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\BankTransferSettings;
use App\Models\Division;
use App\Models\PadelEvent;
use App\Models\EventDivision;
use App\Models\Eligibility;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationPaymentsFlowTest extends TestCase
{
    use RefreshDatabase;

    private Player $player;

    private PadelEvent $event;

    private EventDivision $eventDivision;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        Http::fake([
            'https://secure.sandbox.netopia-payments.com/*' => Http::response([
                'error' => [
                    'code' => '101',
                    'message' => 'Redirect user to payment page',
                ],
                'payment' => [
                    'ntpID' => '3040894',
                    'amount' => 55,
                    'status' => 1,
                    'currency' => 'RON',
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

        $this->player = Player::factory()->playerOnly()->create();
        $this->event = PadelEvent::factory()->create([
            'date' => now()->addDays(30)->format('Y-m-d'),
            'registration_allowed_until' => time() + 86400,
            'cancellation_allowed_until' => time() + 86400,
        ]);
        $division = Division::factory()->create();
        $this->eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'division_title' => $division->name,
            'players_limit' => 10,
        ]);
        Eligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'approved',
            'being_student' => false,
        ]);
        BankTransferSettings::factory()->create();
    }

    public function test_app_create_is_unpaid_and_zero_amount_is_paid(): void
    {
        Sanctum::actingAs($this->player);

        $unpaid = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);
        $unpaid->assertStatus(201)
            ->assertJsonPath('data.lifecycle_status', 'active')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.payment', 55);
        $this->assertNotNull($unpaid->json('data.payment_due_at'));
        $this->assertGreaterThan(time(), (int) $unpaid->json('data.payment_due_at'));

        $otherDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'players_limit' => 10,
            'entry_fee' => 0,
        ]);

        // Same player cannot mix edition-wide; use new player for zero fee
        $player2 = Player::factory()->playerOnly()->create();
        Eligibility::factory()->create([
            'player_id' => $player2->id_player,
            'review_status' => 'approved',
        ]);
        Sanctum::actingAs($player2);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $otherDivision->id_event_division,
        ])->assertStatus(201)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.amount_due', 0)
            ->assertJsonPath('data.payment_due_at', null);
    }

    public function test_netopia_start_and_ipn_marks_paid(): void
    {
        Sanctum::actingAs($this->player);
        $id = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->json('data.id_registration');

        $start = $this->postJson("/api/v1/app/event-registrations/{$id}/payments/netopia/start");
        $start->assertStatus(200)
            ->assertJsonPath('data.attempt.redirect_url', 'https://secure-sandbox.netopia-payments.com/ui/card?p=test');
        $orderId = $start->json('data.attempt.order_id');

        $this->assertDatabaseHas('event_registration_payment_attempts', [
            'checkout_id' => $start->json('data.attempt.checkout_id'),
            'ntp_id' => '3040894',
            'redirect_url' => 'https://secure-sandbox.netopia-payments.com/ui/card?p=test',
        ]);
        $this->assertStringStartsWith('PAD-C', (string) $orderId);

        $this->postJson('/api/v1/webhooks/netopia/ipn', [
            'posSignature' => 'TEST-POS-SIGNATURE',
            'orderID' => $orderId,
            'ntpID' => '3040894',
            'status' => 3,
            'error' => ['code' => '00', 'message' => 'Approved'],
        ])->assertStatus(200);

        $this->assertDatabaseHas('event_registrations', [
            'id_registration' => $id,
            'payment_status' => RegistrationPaymentStatus::Paid->value,
            'payment_method' => 'netopia',
        ]);
    }

    public function test_bank_proof_upload_and_manager_approve(): void
    {
        Sanctum::actingAs($this->player);
        $id = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->json('data.id_registration');

        $upload = $this->post("/api/v1/app/event-registrations/{$id}/payments/bank-proofs", [
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ], ['Accept' => 'application/json']);
        $upload->assertStatus(201)->assertJsonPath('data.registration.payment_status', 'awaiting_proof_review');
        $proofId = $upload->json('data.proof.id_payment_proof');

        $manager = Player::factory()->manager()->create();
        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/manager/payment-proofs/{$proofId}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_declined_blocks_rejoin_cancelled_allows_rejoin(): void
    {
        $manager = Player::factory()->manager()->create();
        Sanctum::actingAs($this->player);
        $id = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->json('data.id_registration');

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/manager/event-registrations/{$id}/decline", [
            'decline_reason' => 'payment_not_completed',
        ])->assertStatus(200)->assertJsonPath('data.lifecycle_status', 'declined');

        Sanctum::actingAs($this->player);
        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(422);

        $player2 = Player::factory()->playerOnly()->create();
        Eligibility::factory()->create(['player_id' => $player2->id_player, 'review_status' => 'approved']);
        Sanctum::actingAs($player2);
        $id2 = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->json('data.id_registration');

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/manager/event-registrations/{$id2}/cancel", [
            'cancel_reason' => 'change_of_plans',
        ])->assertStatus(200);

        Sanctum::actingAs($player2);
        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);
    }

    public function test_device_token_register_and_bank_settings_read(): void
    {
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/device-tokens', [
            'token' => 'ExponentPushToken[test]',
            'platform' => 'android',
        ])->assertStatus(201);

        $this->assertDatabaseHas('player_device_tokens', [
            'player_id' => $this->player->id_player,
            'token' => 'ExponentPushToken[test]',
        ]);

        $this->getJson('/api/v1/app/bank-transfer-settings?checkout_id=12')
            ->assertStatus(200)
            ->assertJsonPath('data.payment_reference', 'PAD-12');
    }

    public function test_late_ipn_after_cancel_sets_refund_pending(): void
    {
        Sanctum::actingAs($this->player);
        $id = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->json('data.id_registration');

        $orderId = $this->postJson("/api/v1/app/event-registrations/{$id}/payments/netopia/start")
            ->json('data.attempt.order_id');

        $this->deleteJson("/api/v1/app/event-registrations/{$id}", [
            'cancel_reason' => 'change_of_plans',
        ])->assertStatus(200);

        $this->postJson('/api/v1/webhooks/netopia/ipn', [
            'posSignature' => 'TEST-POS-SIGNATURE',
            'orderID' => $orderId,
            'ntpID' => 'ntp-late',
            'status' => 3,
            'error' => ['code' => '00', 'message' => 'Approved'],
        ])->assertStatus(200);

        $this->assertDatabaseHas('event_registrations', [
            'id_registration' => $id,
            'lifecycle_status' => RegistrationLifecycleStatus::Cancelled->value,
            'payment_status' => RegistrationPaymentStatus::Refunded->value,
        ]);
        $this->assertDatabaseHas('event_registration_payment_refunds', [
            'status' => 'succeeded',
        ]);
    }
}
