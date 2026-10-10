<?php

declare(strict_types=1);

namespace App\Services\RegistrationPayment;

use App\Enums\Registration\PaymentProofReviewStatus;
use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\EventRegistrationPaymentProof;
use App\Models\PadelEvent;
use App\Models\PlayerStudentProof;
use App\Services\Pricing\PricingService;
use App\Services\Push\ExpoPushService;
use InvalidArgumentException;

class RegistrationService
{
    public function __construct(
        private readonly ExpoPushService $expoPushService,
    ) {
    }

    public function amountDue(EventRegistration $registration): int
    {
        return $registration->amountDue();
    }

    /**
     * @param  array{event_id: int, player_id: int, event_division_id: int, is_minor?: bool, is_student?: bool, payment?: int}  $data
     */
    public function createForApp(array $data, bool $attachCheckout = true): EventRegistration
    {
        $event = PadelEvent::query()->findOrFail($data['event_id']);
        $playerId = (int) $data['player_id'];
        if (! array_key_exists('event_division_id', $data) || $data['event_division_id'] === null) {
            throw new RegistrationValidationException([
                'event_division_id' => ['An edition division is required.'],
            ]);
        }
        $eventDivisionId = (int) $data['event_division_id'];

        if (EventRegistration::hasDeclinedForEdition((int) $event->id_event, $playerId)) {
            throw new InvalidArgumentException('Player has been declined for this edition and cannot register again.');
        }

        $xor = EventRegistration::xorConflict((int) $event->id_event, $playerId, $eventDivisionId);
        if ($xor !== null) {
            throw new RegistrationValidationException($xor);
        }

        $this->assertDivisionHasCapacity($eventDivisionId);

        $division = EventDivision::query()->findOrFail($eventDivisionId);
        if ((int) $division->event_id !== (int) $event->id_event) {
            throw new RegistrationValidationException([
                'event_division_id' => ['Division does not belong to this edition.'],
            ]);
        }

        $isStudent = PlayerStudentProof::hasActiveFor($playerId);
        $payment = (int) $division->entry_fee;
        $now = time();

        $attributes = [
            'event_id' => $event->id_event,
            'player_id' => $playerId,
            'event_division_id' => $eventDivisionId,
            'is_minor' => false,
            'is_student' => $isStudent,
            'payment' => $payment,
            'lifecycle_status' => RegistrationLifecycleStatus::Active,
            'payment_status' => RegistrationPaymentStatus::Unpaid,
            'payment_method' => null,
            'paid_at' => null,
            'payment_due_at' => $now + 86400,
        ];

        if ($payment === 0) {
            $attributes['payment_status'] = RegistrationPaymentStatus::Paid;
            $attributes['paid_at'] = $now;
            $attributes['payment_due_at'] = null;
        }

        $registration = EventRegistration::query()->create($attributes);

        if ($attachCheckout) {
            /** @var CheckoutService $checkoutService */
            $checkoutService = app(CheckoutService::class);
            if ($payment === 0) {
                $checkoutService->attachCompletedStub($registration);
                app(PricingService::class)->finalizeOnPaid($registration);
            } else {
                $checkoutService->openOrRefreshForUnpaid((int) $event->id_event, $playerId);
                $this->commitAutoBenefits($registration);
            }
        }

        $event = $registration->payment_status === RegistrationPaymentStatus::Paid
            ? 'paid'
            : 'unpaid_created';
        $this->expoPushService->notifyRegistrationStatus(
            $playerId,
            $event,
            ['registration_id' => $registration->id_registration]
        );

        return $registration;
    }

    /**
     * @param  array{event_id: int, player_id: int, event_division_id: int, is_minor?: bool, is_student?: bool, payment?: int}  $data
     */
    public function createForManager(array $data): EventRegistration
    {
        $editionId = (int) $data['event_id'];
        $playerId = (int) $data['player_id'];
        if (! array_key_exists('event_division_id', $data) || $data['event_division_id'] === null) {
            throw new RegistrationValidationException([
                'event_division_id' => ['An edition division is required.'],
            ]);
        }
        $eventDivisionId = (int) $data['event_division_id'];

        if (EventRegistration::hasDeclinedForEdition($editionId, $playerId)) {
            throw new InvalidArgumentException('Player has been declined for this edition and cannot register again.');
        }

        $xor = EventRegistration::xorConflict($editionId, $playerId, $eventDivisionId);
        if ($xor !== null) {
            throw new RegistrationValidationException($xor);
        }

        $division = EventDivision::query()->findOrFail($eventDivisionId);
        $payment = array_key_exists('payment', $data)
            ? (int) $data['payment']
            : (int) $division->entry_fee;

        $registration = EventRegistration::query()->create([
            'event_id' => $editionId,
            'player_id' => $playerId,
            'event_division_id' => $eventDivisionId,
            'is_minor' => $data['is_minor'] ?? false,
            'is_student' => $data['is_student'] ?? false,
            'payment' => $payment,
            'lifecycle_status' => RegistrationLifecycleStatus::Active,
            'payment_status' => RegistrationPaymentStatus::Paid,
            'payment_method' => null,
            'paid_at' => time(),
            'payment_due_at' => null,
        ]);

        app(CheckoutService::class)->attachCompletedStub($registration);
        app(PricingService::class)->finalizeOnPaid($registration);

        return $registration;
    }

    public function assertFeesEditable(EventRegistration $registration): void
    {
        if ($registration->feesAreLocked()) {
            throw new InvalidArgumentException('Payment cannot be changed after the registration is paid.');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function applyFeeChanges(EventRegistration $registration, array $attributes): EventRegistration
    {
        $payment = array_key_exists('payment', $attributes)
            ? (int) $attributes['payment']
            : (int) $registration->payment;

        $paymentChanging = array_key_exists('payment', $attributes)
            && (int) $attributes['payment'] !== (int) $registration->payment;

        if ($paymentChanging && $registration->feesAreLocked()) {
            throw new InvalidArgumentException('Payment cannot be changed after the registration is paid.');
        }

        if (array_key_exists('payment', $attributes)) {
            $registration->payment = $payment;

            $item = $registration->latestCheckoutItem;
            if ($item !== null && ! $registration->feesAreLocked()) {
                $item->base_amount_ron = $payment;
                $item->save();
                app(PricingService::class)->resyncItemAmount($item);
            }

            if (
                $registration->lifecycle_status === RegistrationLifecycleStatus::Active
                && in_array($registration->payment_status, [
                    RegistrationPaymentStatus::Unpaid,
                    RegistrationPaymentStatus::AwaitingProofReview,
                ], true)
                && $registration->amountDue() === 0
            ) {
                $this->markPaid($registration, null);
            }
        }

        return $registration;
    }

    public function markPaid(EventRegistration $registration, ?string $paymentMethod): void
    {
        $registration->payment_status = RegistrationPaymentStatus::Paid;
        $registration->paid_at = time();
        $registration->payment_due_at = null;
        if ($paymentMethod !== null) {
            $registration->payment_method = $paymentMethod;
        }
        $registration->save();

        $this->rejectPendingProofs($registration);

        app(PricingService::class)->finalizeOnPaid($registration);

        $this->expoPushService->notifyRegistrationStatus(
            (int) $registration->player_id,
            'paid',
            ['registration_id' => $registration->id_registration]
        );
    }

    public function rejectPendingProofs(EventRegistration $registration): void
    {
        $checkoutIds = $registration->checkoutItems()->pluck('checkout_id')->all();
        if ($checkoutIds === []) {
            return;
        }

        EventRegistrationPaymentProof::query()
            ->whereIn('checkout_id', $checkoutIds)
            ->where('review_status', PaymentProofReviewStatus::Pending->value)
            ->update([
                'review_status' => PaymentProofReviewStatus::Rejected->value,
                'review_message' => 'Superseded by payment confirmation',
                'reviewed_at' => time(),
                'updated_at' => time(),
            ]);
    }

    public function assertDivisionHasCapacity(int $eventDivisionId): void
    {
        $eventDivision = EventDivision::query()->findOrFail($eventDivisionId);
        $currentPlayers = EventRegistration::query()
            ->active()
            ->where('event_division_id', $eventDivisionId)
            ->count();

        if ($currentPlayers >= $eventDivision->players_limit) {
            throw new RegistrationValidationException([
                'event_division_id' => ['This division is already full'],
            ]);
        }
    }

    public function assertDivisionHasCapacityLocked(int $eventDivisionId): void
    {
        $eventDivision = EventDivision::query()
            ->where('id_event_division', $eventDivisionId)
            ->lockForUpdate()
            ->firstOrFail();

        $currentPlayers = EventRegistration::query()
            ->active()
            ->where('event_division_id', $eventDivisionId)
            ->count();

        if ($currentPlayers >= $eventDivision->players_limit) {
            throw new RegistrationValidationException([
                'event_division_ids' => ['This division is already full'],
            ]);
        }
    }

    public function commitAutoBenefits(EventRegistration $registration): void
    {
        $registration->refresh();
        $item = $registration->latestCheckoutItem;
        if ($item === null) {
            return;
        }

        if ($registration->feesAreLocked()) {
            return;
        }

        $pricing = app(PricingService::class);
        $context = $pricing->buildContextForRegistration($registration);
        $payable = $pricing->commitSelections($item, $context, []);

        if (
            $payable === 0
            && $registration->lifecycle_status === RegistrationLifecycleStatus::Active
            && in_array($registration->payment_status, [
                RegistrationPaymentStatus::Unpaid,
                RegistrationPaymentStatus::AwaitingProofReview,
            ], true)
        ) {
            $this->markPaid($registration, null);
        }
    }

    public function assertPlayerPaymentWindowOpen(EventRegistration $registration): void
    {
        if ($registration->isPaymentOverdue()) {
            throw new InvalidArgumentException('The payment window for this registration has ended.');
        }
    }

    /**
     * Gate Netopia start / bank-proof upload. Late IPN is allowed when the attempt
     * was created before payment_due_at (pass $allowIfAttemptCreatedBeforeDue=true with attempt timestamp).
     */
    public function assertCheckoutPaymentWindowOpen(
        \App\Models\EventRegistrationCheckout $checkout,
        ?int $attemptCreatedAt = null
    ): void {
        $items = app(CheckoutService::class)->payableActiveItems($checkout);
        foreach ($items as $item) {
            $registration = $item->registration;
            if ($registration === null) {
                continue;
            }

            if (! $registration->isPaymentOverdue()) {
                continue;
            }

            if (
                $attemptCreatedAt !== null
                && $registration->payment_due_at !== null
                && $attemptCreatedAt < (int) $registration->payment_due_at
            ) {
                // Attempt started before the due cutoff — allow late completion (e.g. IPN).
                continue;
            }

            throw new InvalidArgumentException('The payment window for this registration has ended.');
        }
    }
}
