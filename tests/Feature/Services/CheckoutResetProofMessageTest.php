<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\Registration\CheckoutStatus;
use App\Enums\Registration\PaymentProofReviewStatus;
use App\Enums\Registration\RegistrationPaymentMethod;
use App\Models\EventRegistrationCheckout;
use App\Models\EventRegistrationPaymentProof;
use App\Models\PadelEvent;
use App\Models\User;
use App\Services\RegistrationPayment\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

class CheckoutResetProofMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_payment_path_sets_proof_cancelled_by_first_name(): void
    {
        $player = User::factory()->playerOnly()->create([
            'first_name' => 'Andrei',
        ]);
        $event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(now()->addDays(20)->format('Y-m-d')),
        ]);
        $checkout = EventRegistrationCheckout::query()->create([
            'event_id' => $event->id_event,
            'player_user_id' => $player->id_user,
            'status' => CheckoutStatus::AwaitingProof,
            'total_amount' => 50,
            'payment_method' => RegistrationPaymentMethod::BankTransfer,
        ]);
        $proof = EventRegistrationPaymentProof::query()->create([
            'checkout_id' => $checkout->id_checkout,
            'proof_path' => 'proofs/test.png',
            'review_status' => PaymentProofReviewStatus::Pending,
        ]);

        app(CheckoutService::class)->resetPaymentPath($checkout->fresh());

        $this->assertDatabaseHas('event_registration_payment_proofs', [
            'id_payment_proof' => $proof->id_payment_proof,
            'review_status' => PaymentProofReviewStatus::Rejected->value,
            'review_message' => 'Proof cancelled by Andrei',
        ]);
    }

    public function test_reset_payment_path_falls_back_to_player_when_first_name_empty(): void
    {
        $player = User::factory()->playerOnly()->create([
            'first_name' => ' ',
        ]);
        $event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(now()->addDays(20)->format('Y-m-d')),
        ]);
        $checkout = EventRegistrationCheckout::query()->create([
            'event_id' => $event->id_event,
            'player_user_id' => $player->id_user,
            'status' => CheckoutStatus::AwaitingProof,
            'total_amount' => 50,
            'payment_method' => RegistrationPaymentMethod::BankTransfer,
        ]);
        $proof = EventRegistrationPaymentProof::query()->create([
            'checkout_id' => $checkout->id_checkout,
            'proof_path' => 'proofs/test.png',
            'review_status' => PaymentProofReviewStatus::Pending,
        ]);

        app(CheckoutService::class)->resetPaymentPath($checkout->fresh());

        $this->assertDatabaseHas('event_registration_payment_proofs', [
            'id_payment_proof' => $proof->id_payment_proof,
            'review_message' => 'Proof cancelled by Player',
        ]);
    }
}
