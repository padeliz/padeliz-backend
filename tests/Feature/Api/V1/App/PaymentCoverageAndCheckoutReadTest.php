<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Enums\Registration\CheckoutStatus;
use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Enums\Registration\PaymentAttemptStatus;
use App\Models\BankTransferSettings;
use App\Models\Division;
use App\Models\PadelEvent;
use App\Models\EventDivision;
use App\Models\EventRegistrationPaymentAttempt;
use App\Models\EventRegistrationPaymentProof;
use App\Models\Eligibility;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentCoverageAndCheckoutReadTest extends TestCase
{
    use RefreshDatabase;

    private Player $player;

    private PadelEvent $event;

    private EventDivision $divisionA;

    private EventDivision $divisionB;

    private Division $catalogDivisionA;

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
                    'amount' => 100,
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
        $this->catalogDivisionA = Division::factory()->create([
            'name' => 'Open A',
            'color' => '#112233',
        ]);
        $catalogB = Division::factory()->create([
            'name' => 'Open B',
            'color' => '#445566',
        ]);
        $this->divisionA = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $this->catalogDivisionA->id_division,
            'division_title' => 'Open A',
            'players_limit' => 10,
            'entry_fee' => 50,
        ]);
        $this->divisionB = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $catalogB->id_division,
            'division_title' => 'Open B',
            'players_limit' => 10,
            'entry_fee' => 50,
        ]);
        Eligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'approved',
            'being_student' => false,
        ]);
        BankTransferSettings::factory()->create();
    }

    public function test_netopia_start_stores_coverage_snapshot(): void
    {
        Sanctum::actingAs($this->player);
        $checkoutId = $this->createOpenCheckout();

        $response = $this->postJson("/api/v1/app/registration-checkouts/{$checkoutId}/payments/netopia/start")
            ->assertStatus(200);

        $coverage = $response->json('data.checkout.payment_attempts.0.coverage');
        $this->assertIsArray($coverage);
        $this->assertCount(2, $coverage);
        $titles = collect($coverage)->pluck('division_title')->sort()->values()->all();
        $this->assertSame(['Open A', 'Open B'], $titles);
        $this->assertContains('#112233', collect($coverage)->pluck('division_color')->all());
        $this->assertContains('#445566', collect($coverage)->pluck('division_color')->all());
    }

    public function test_bank_proof_stores_coverage_and_survives_exclude(): void
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

        $upload = $this->post("/api/v1/app/registration-checkouts/{$checkoutId}/payments/bank-proofs", [
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $coverageBefore = $upload->json('data.checkout.payment_proofs.0.coverage');
        $this->assertCount(2, $coverageBefore);

        $this->postJson("/api/v1/app/registration-checkouts/{$checkoutId}/exclude", [
            'registration_ids' => [$regA],
            'cancel_reason' => 'schedule_conflict',
        ])->assertStatus(200);

        $proof = EventRegistrationPaymentProof::query()
            ->where('checkout_id', $checkoutId)
            ->firstOrFail();
        $this->assertCount(2, $proof->coverage ?? []);
        $this->assertSame($coverageBefore, $proof->coverage);
    }

    public function test_list_and_show_checkouts_are_player_scoped(): void
    {
        Sanctum::actingAs($this->player);
        $checkoutId = $this->createOpenCheckout();

        $this->getJson("/api/v1/app/events/{$this->event->id_event}/registration-checkouts")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.checkouts')
            ->assertJsonPath('data.checkouts.0.id_checkout', $checkoutId);

        $this->getJson("/api/v1/app/registration-checkouts/{$checkoutId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id_checkout', $checkoutId);

        $other = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($other);

        $this->getJson("/api/v1/app/events/{$this->event->id_event}/registration-checkouts")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.checkouts');

        $this->getJson("/api/v1/app/registration-checkouts/{$checkoutId}")
            ->assertStatus(404);
    }

    public function test_checkout_payment_attempts_include_error_message(): void
    {
        Sanctum::actingAs($this->player);
        $checkoutId = $this->createOpenCheckout();

        EventRegistrationPaymentAttempt::query()->create([
            'checkout_id' => $checkoutId,
            'amount' => 100,
            'currency' => 'RON',
            'status' => PaymentAttemptStatus::Failed,
            'order_id' => 'PAD-C' . $checkoutId . '-err',
            'error_message' => 'Card declined by issuer',
            'inserted_at' => time(),
        ]);

        $this->getJson("/api/v1/app/registration-checkouts/{$checkoutId}")
            ->assertStatus(200)
            ->assertJsonPath('data.payment_attempts.0.error_message', 'Card declined by issuer');
    }

    public function test_exclude_requires_cancel_reason_and_persists_it(): void
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
        ])->assertStatus(422);

        $this->postJson("/api/v1/app/registration-checkouts/{$checkoutId}/exclude", [
            'registration_ids' => [$regA],
            'cancel_reason' => 'not_a_reason',
        ])->assertStatus(422);

        $this->postJson("/api/v1/app/registration-checkouts/{$checkoutId}/exclude", [
            'registration_ids' => [$regA],
            'cancel_reason' => 'injury_or_illness',
        ])->assertStatus(200)
            ->assertJsonPath('data.checkout.status', CheckoutStatus::Open->value);

        $this->assertDatabaseHas('event_registrations', [
            'id_registration' => $regA,
            'lifecycle_status' => RegistrationLifecycleStatus::Cancelled->value,
            'cancel_reason' => 'injury_or_illness',
        ]);
        $this->assertDatabaseHas('event_registrations', [
            'id_registration' => $regB,
            'payment_status' => RegistrationPaymentStatus::Unpaid->value,
        ]);
    }

    private function createOpenCheckout(): int
    {
        return (int) $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_ids' => [
                $this->divisionA->id_event_division,
                $this->divisionB->id_event_division,
            ],
        ])->json('data.checkout.id_checkout');
    }
}
