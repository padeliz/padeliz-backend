<?php

declare(strict_types=1);

namespace App\Services\RegistrationPayment;

use App\Enums\Registration\CheckoutItemStatus;
use App\Enums\Registration\CheckoutStatus;
use App\Enums\Registration\PaymentProofReviewStatus;
use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentMethod;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\PadelEvent;
use App\Models\EventRegistration;
use App\Models\EventRegistrationCheckout;
use App\Models\EventRegistrationCheckoutItem;
use App\Models\EventRegistrationPaymentProof;
use App\Models\Eligibility;
use App\Services\Pricing\PricingService;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CheckoutService
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {
    }

    /**
     * @param  list<int>  $eventDivisionIds
     * @return array{registrations: list<EventRegistration>, checkout: EventRegistrationCheckout}
     */
    public function createManyForApp(int $editionId, int $playerId, array $eventDivisionIds, Eligibility $eligibility): array
    {
        if ($eventDivisionIds === []) {
            throw new RegistrationValidationException([
                'event_division_ids' => ['Select at least one edition division.'],
            ]);
        }

        if (in_array(null, $eventDivisionIds, true)) {
            throw new RegistrationValidationException([
                'event_division_ids' => ['An edition division is required for each registration.'],
            ]);
        }

        $specific = array_values(array_map('intval', $eventDivisionIds));
        if (count($specific) !== count(array_unique($specific))) {
            throw new RegistrationValidationException([
                'event_division_ids' => ['Duplicate divisions are not allowed.'],
            ]);
        }

        return DB::transaction(function () use ($editionId, $playerId, $specific, $eligibility) {
            PadelEvent::query()->lockForUpdate()->findOrFail($editionId);

            foreach ($specific as $divisionId) {
                $this->registrationService->assertDivisionHasCapacityLocked($divisionId);
            }

            $registrations = [];
            foreach ($specific as $divisionId) {
                $registrations[] = $this->registrationService->createForApp([
                    'event_id' => $editionId,
                    'player_id' => $playerId,
                    'event_division_id' => $divisionId,
                ], $eligibility, attachCheckout: false);
            }

            $hasUnpaid = collect($registrations)->contains(
                fn (EventRegistration $r) => $r->payment_status === RegistrationPaymentStatus::Unpaid
            );

            if (! $hasUnpaid) {
                $checkout = EventRegistrationCheckout::query()->create([
                    'event_id' => $editionId,
                    'player_id' => $playerId,
                    'status' => CheckoutStatus::Completed,
                    'total_amount' => 0,
                    'payment_method' => null,
                    'paid_at' => time(),
                ]);
                foreach ($registrations as $registration) {
                    EventRegistrationCheckoutItem::query()->create([
                        'checkout_id' => $checkout->id_checkout,
                        'registration_id' => $registration->id_registration,
                        'base_amount_ron' => (int) $registration->payment,
                        'amount' => 0,
                        'item_status' => CheckoutItemStatus::Active,
                    ]);
                    app(PricingService::class)->finalizeOnPaid($registration);
                }
            } else {
                $checkout = $this->openOrRefreshForUnpaid($editionId, $playerId);
                foreach ($registrations as $registration) {
                    if ($registration->payment_status === RegistrationPaymentStatus::Unpaid) {
                        $this->registrationService->commitAutoBenefits($registration, $eligibility);
                    }
                }
            }

            return [
                'registrations' => array_map(
                    static fn (EventRegistration $r) => $r->fresh() ?? $r,
                    $registrations
                ),
                'checkout' => $checkout->fresh(['items.registration.eventDivision']),
            ];
        });
    }

    public function attachCompletedStub(EventRegistration $registration): EventRegistrationCheckout
    {
        $existing = EventRegistrationCheckoutItem::query()
            ->where('registration_id', $registration->id_registration)
            ->first();
        if ($existing !== null) {
            $existing->base_amount_ron = (int) $registration->payment;
            $existing->save();
            app(PricingService::class)->resyncItemAmount($existing);

            return EventRegistrationCheckout::query()->findOrFail($existing->checkout_id);
        }

        $amount = $this->registrationService->amountDue($registration);

        $checkout = EventRegistrationCheckout::query()->create([
            'event_id' => $registration->event_id,
            'player_id' => $registration->player_id,
            'status' => CheckoutStatus::Completed,
            'total_amount' => $amount,
            'payment_method' => $registration->payment_method,
            'paid_at' => $registration->paid_at ?? time(),
        ]);

        $item = EventRegistrationCheckoutItem::query()->create([
            'checkout_id' => $checkout->id_checkout,
            'registration_id' => $registration->id_registration,
            'base_amount_ron' => (int) $registration->payment,
            'amount' => $amount,
            'item_status' => $registration->lifecycle_status === RegistrationLifecycleStatus::Active
                ? CheckoutItemStatus::Active
                : CheckoutItemStatus::Cancelled,
        ]);
        app(PricingService::class)->resyncItemAmount($item);

        return $checkout->fresh(['items']);
    }

    public function openOrRefreshForUnpaid(int $editionId, int $playerId): EventRegistrationCheckout
    {
        return DB::transaction(function () use ($editionId, $playerId) {
            $checkout = EventRegistrationCheckout::query()
                ->where('event_id', $editionId)
                ->where('player_id', $playerId)
                ->open()
                ->lockForUpdate()
                ->first();

            $unpaidRegs = EventRegistration::query()
                ->active()
                ->where('event_id', $editionId)
                ->where('player_id', $playerId)
                ->whereIn('payment_status', [
                    RegistrationPaymentStatus::Unpaid->value,
                    RegistrationPaymentStatus::AwaitingProofReview->value,
                ])
                ->orderBy('id_registration')
                ->get();

            $paidZeroRegs = EventRegistration::query()
                ->active()
                ->where('event_id', $editionId)
                ->where('player_id', $playerId)
                ->where('payment_status', RegistrationPaymentStatus::Paid->value)
                ->where('payment', 0)
                ->whereDoesntHave('checkoutItems', function ($q) {
                    $q->whereHas('checkout', fn ($c) => $c->open());
                })
                ->orderBy('id_registration')
                ->get();

            // Free paid regs that aren't on an open checkout yet should join open checkout as paid items
            // or get completed stubs — handled below when building items from unpaid + free on same create.

            if ($unpaidRegs->isEmpty()) {
                if ($checkout !== null && $checkout->status === CheckoutStatus::AwaitingProof) {
                    throw new InvalidArgumentException('Cannot modify checkout while awaiting proof review.');
                }

                // All free / already paid — ensure completed checkout for active paid without open checkout
                $activePaid = EventRegistration::query()
                    ->active()
                    ->where('event_id', $editionId)
                    ->where('player_id', $playerId)
                    ->where('payment_status', RegistrationPaymentStatus::Paid->value)
                    ->get();

                foreach ($activePaid as $reg) {
                    $this->attachCompletedStub($reg);
                }

                if ($checkout !== null) {
                    return $this->recomputeTotals($checkout->fresh(['items']));
                }

                throw new InvalidArgumentException('No unpaid registrations to open a checkout.');
            }

            if ($checkout !== null && $checkout->status === CheckoutStatus::AwaitingProof) {
                throw new InvalidArgumentException('Cannot add divisions while awaiting proof review.');
            }

            if ($checkout === null) {
                $checkout = EventRegistrationCheckout::query()->create([
                    'event_id' => $editionId,
                    'player_id' => $playerId,
                    'status' => CheckoutStatus::Open,
                    'total_amount' => 0,
                    'payment_method' => null,
                    'paid_at' => null,
                ]);
                $checkout = EventRegistrationCheckout::query()
                    ->where('id_checkout', $checkout->id_checkout)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $existingRegistrationIds = EventRegistrationCheckoutItem::query()
                ->where('checkout_id', $checkout->id_checkout)
                ->pluck('registration_id')
                ->all();

            $pricing = app(PricingService::class);

            foreach ($unpaidRegs as $reg) {
                if (in_array($reg->id_registration, $existingRegistrationIds, true)) {
                    // Refresh only: keep locked redemptions; do not rediscover benefits.
                    $item = EventRegistrationCheckoutItem::query()
                        ->where('checkout_id', $checkout->id_checkout)
                        ->where('registration_id', $reg->id_registration)
                        ->where('item_status', CheckoutItemStatus::Active->value)
                        ->first();
                    if ($item !== null) {
                        $item->base_amount_ron = (int) $reg->payment;
                        $item->save();
                        $pricing->resyncItemAmount($item);
                        if ((int) $item->fresh()->amount === 0
                            && $reg->payment_status !== RegistrationPaymentStatus::Paid
                        ) {
                            $this->registrationService->markPaid($reg, null);
                        }
                    }
                    continue;
                }

                $item = EventRegistrationCheckoutItem::query()->create([
                    'checkout_id' => $checkout->id_checkout,
                    'registration_id' => $reg->id_registration,
                    'base_amount_ron' => (int) $reg->payment,
                    'amount' => (int) $reg->payment,
                    'item_status' => CheckoutItemStatus::Active,
                ]);
                $pricing->resyncItemAmount($item);
                if ((int) $item->fresh()->amount === 0) {
                    $this->registrationService->markPaid($reg, null);
                }
            }

            // Include free paid regs created in same batch that belong on this checkout
            foreach ($paidZeroRegs as $reg) {
                if (in_array($reg->id_registration, $existingRegistrationIds, true)) {
                    continue;
                }
                $linkedElsewhere = EventRegistrationCheckoutItem::query()
                    ->where('registration_id', $reg->id_registration)
                    ->exists();
                if ($linkedElsewhere) {
                    continue;
                }
                EventRegistrationCheckoutItem::query()->create([
                    'checkout_id' => $checkout->id_checkout,
                    'registration_id' => $reg->id_registration,
                    'base_amount_ron' => (int) $reg->payment,
                    'amount' => 0,
                    'item_status' => CheckoutItemStatus::Active,
                ]);
            }

            return $this->recomputeTotals($checkout->fresh(['items.registration']));
        });
    }

    public function lockCheckout(int $checkoutId): EventRegistrationCheckout
    {
        $checkout = EventRegistrationCheckout::query()
            ->where('id_checkout', $checkoutId)
            ->lockForUpdate()
            ->first();

        if ($checkout === null) {
            throw new InvalidArgumentException('Checkout not found.');
        }

        return $checkout;
    }

    public function payableActiveItems(EventRegistrationCheckout $checkout)
    {
        return $checkout->items()
            ->active()
            ->where('amount', '>', 0)
            ->with('registration')
            ->get()
            ->filter(function (EventRegistrationCheckoutItem $item) {
                $reg = $item->registration;
                if ($reg === null || $reg->lifecycle_status !== RegistrationLifecycleStatus::Active) {
                    return false;
                }

                return in_array($reg->payment_status, [
                    RegistrationPaymentStatus::Unpaid,
                    RegistrationPaymentStatus::AwaitingProofReview,
                ], true);
            })
            ->values();
    }

    public function payableTotal(EventRegistrationCheckout $checkout): int
    {
        return (int) $this->payableActiveItems($checkout)->sum(fn (EventRegistrationCheckoutItem $item) => (int) $item->amount);
    }

    /**
     * Immutable snapshot of payable divisions at payment/proof create time.
     *
     * @return list<array{
     *     registration_id: int,
     *     event_division_id: int|null,
     *     division_title: string,
     *     division_color: string,
     *     amount_ron: int
     * }>
     */
    public function coverageSnapshot(EventRegistrationCheckout $checkout): array
    {
        $items = $this->payableActiveItems($checkout);
        $items->loadMissing(['registration.eventDivision.division']);

        $coverage = [];
        foreach ($items as $item) {
            $reg = $item->registration;
            $eventDivision = $reg?->eventDivision;
            $division = $eventDivision?->division;

            $coverage[] = [
                'registration_id' => (int) $item->registration_id,
                'event_division_id' => $reg?->event_division_id !== null
                    ? (int) $reg->event_division_id
                    : null,
                'division_title' => (string) (
                    $eventDivision?->division_title
                    ?? $division?->name
                    ?? 'Division'
                ),
                'division_color' => (string) ($division?->color ?? '#888888'),
                'amount_ron' => (int) $item->amount,
            ];
        }

        return $coverage;
    }

    public function recomputeTotals(EventRegistrationCheckout $checkout): EventRegistrationCheckout
    {
        $total = $this->payableTotal($checkout);
        $checkout->total_amount = $total;

        $payable = $this->payableActiveItems($checkout);
        $allActive = $checkout->items()->active()->with('registration')->get();
        $allPaidOrFree = $allActive->every(function (EventRegistrationCheckoutItem $item) {
            $reg = $item->registration;
            if ($reg === null) {
                return false;
            }
            if ($reg->lifecycle_status !== RegistrationLifecycleStatus::Active) {
                return true;
            }

            return $reg->payment_status === RegistrationPaymentStatus::Paid
                || (int) $item->amount === 0;
        });

        if ($payable->isEmpty() && $allActive->isNotEmpty() && $allPaidOrFree) {
            $checkout->status = CheckoutStatus::Completed;
            $checkout->paid_at = $checkout->paid_at ?? time();
        } elseif ($payable->contains(fn (EventRegistrationCheckoutItem $item) => $item->registration?->payment_status === RegistrationPaymentStatus::AwaitingProofReview)) {
            $checkout->status = CheckoutStatus::AwaitingProof;
        } elseif ($payable->isNotEmpty()) {
            $checkout->status = CheckoutStatus::Open;
        }

        $checkout->save();

        return $checkout->fresh(['items.registration']);
    }

    public function markPayableItemsPaid(EventRegistrationCheckout $checkout, ?string $paymentMethod): void
    {
        foreach ($this->payableActiveItems($checkout) as $item) {
            $reg = $item->registration;
            if ($reg === null) {
                continue;
            }
            $this->registrationService->markPaid($reg, $paymentMethod);
        }

        $checkout->payment_method = $paymentMethod !== null
            ? RegistrationPaymentMethod::from($paymentMethod)
            : $checkout->payment_method;
        $checkout->status = CheckoutStatus::Completed;
        $checkout->paid_at = time();
        $checkout->total_amount = 0;
        $checkout->save();
    }

    public function markPayableItemsAwaitingProof(EventRegistrationCheckout $checkout): void
    {
        foreach ($this->payableActiveItems($checkout) as $item) {
            $reg = $item->registration;
            if ($reg === null) {
                continue;
            }
            $reg->payment_status = RegistrationPaymentStatus::AwaitingProofReview;
            $reg->payment_method = RegistrationPaymentMethod::BankTransfer;
            $reg->save();
        }

        $checkout->payment_method = RegistrationPaymentMethod::BankTransfer;
        $checkout->status = CheckoutStatus::AwaitingProof;
        $checkout->save();
    }

    public function resetPaymentPath(EventRegistrationCheckout $checkout): EventRegistrationCheckout
    {
        return DB::transaction(function () use ($checkout) {
            $checkout = $this->lockCheckout((int) $checkout->id_checkout);

            if ($checkout->status === CheckoutStatus::Completed) {
                throw new InvalidArgumentException('Cannot reset a completed checkout.');
            }

            $this->rejectPendingProofsForCheckout(
                $checkout,
                $this->proofCancelledByMessage($checkout)
            );

            foreach ($this->payableActiveItems($checkout) as $item) {
                $reg = $item->registration;
                if ($reg === null) {
                    continue;
                }
                if ((int) $item->amount === 0) {
                    continue;
                }
                $reg->payment_status = RegistrationPaymentStatus::Unpaid;
                $reg->payment_method = null;
                $reg->save();
            }

            $checkout->payment_method = null;
            $checkout->status = CheckoutStatus::Open;
            $checkout->save();

            return $this->recomputeTotals($checkout);
        });
    }

    /**
     * Exclude (cancel) selected registrations while awaiting/unpaid on open checkout, then reset remaining.
     *
     * @param  list<int>  $registrationIds
     */
    public function excludeRegistrations(
        EventRegistrationCheckout $checkout,
        array $registrationIds,
        RegistrationLifecycleService $lifecycleService,
        bool $byPlayer = true,
        ?\App\Enums\Registration\RegistrationCancelReason $cancelReason = null,
    ): EventRegistrationCheckout {
        return DB::transaction(function () use ($checkout, $registrationIds, $lifecycleService, $byPlayer, $cancelReason) {
            $checkout = $this->lockCheckout((int) $checkout->id_checkout);

            if (! $checkout->status->isOpen()) {
                throw new InvalidArgumentException('Checkout is not open.');
            }

            $ids = array_values(array_unique(array_map('intval', $registrationIds)));
            if ($ids === []) {
                throw new InvalidArgumentException('No registrations selected to exclude.');
            }

            $reason = $cancelReason ?? \App\Enums\Registration\RegistrationCancelReason::ChangeOfPlans;

            foreach ($ids as $registrationId) {
                $item = EventRegistrationCheckoutItem::query()
                    ->where('checkout_id', $checkout->id_checkout)
                    ->where('registration_id', $registrationId)
                    ->where('item_status', CheckoutItemStatus::Active->value)
                    ->first();
                if ($item === null) {
                    throw new InvalidArgumentException('Registration is not an active item on this checkout.');
                }

                $reg = EventRegistration::query()->findOrFail($registrationId);
                if ($byPlayer) {
                    $lifecycleService->cancelByPlayer(
                        $reg,
                        $reason,
                        skipCheckoutSideEffects: true
                    );
                } else {
                    $lifecycleService->cancelByManager(
                        $reg,
                        $reason,
                        skipCheckoutSideEffects: true
                    );
                }

                $item->item_status = CheckoutItemStatus::Cancelled;
                $item->save();
            }

            $this->rejectPendingProofsForCheckout($checkout, 'Checkout membership changed');

            foreach ($checkout->items()->active()->with('registration')->get() as $item) {
                $reg = $item->registration;
                if ($reg === null || $reg->lifecycle_status !== RegistrationLifecycleStatus::Active) {
                    continue;
                }
                if ((int) $item->amount === 0 && $reg->payment_status === RegistrationPaymentStatus::Paid) {
                    continue;
                }
                if (in_array($reg->payment_status, [
                    RegistrationPaymentStatus::Unpaid,
                    RegistrationPaymentStatus::AwaitingProofReview,
                ], true)) {
                    $reg->payment_status = RegistrationPaymentStatus::Unpaid;
                    $reg->payment_method = null;
                    $reg->save();
                }
            }

            $checkout->payment_method = null;
            $checkout->status = CheckoutStatus::Open;
            $checkout->save();

            $remainingUnpaid = EventRegistration::query()
                ->active()
                ->where('event_id', $checkout->event_id)
                ->where('player_id', $checkout->player_id)
                ->whereIn('payment_status', [
                    RegistrationPaymentStatus::Unpaid->value,
                    RegistrationPaymentStatus::AwaitingProofReview->value,
                ])
                ->exists();

            if (! $remainingUnpaid) {
                app(ReferralService::class)->clearPendingAttribution(
                    (int) $checkout->event_id,
                    (int) $checkout->player_id
                );
            }

            return $this->recomputeTotals($checkout->fresh(['items.registration']));
        });
    }

    public function softCancelItemForRegistration(EventRegistration $registration): void
    {
        EventRegistrationCheckoutItem::query()
            ->where('registration_id', $registration->id_registration)
            ->where('item_status', CheckoutItemStatus::Active->value)
            ->update([
                'item_status' => CheckoutItemStatus::Cancelled->value,
                'updated_at' => time(),
            ]);
    }

    public function openCheckoutForRegistration(EventRegistration $registration): ?EventRegistrationCheckout
    {
        $item = EventRegistrationCheckoutItem::query()
            ->where('registration_id', $registration->id_registration)
            ->where('item_status', CheckoutItemStatus::Active->value)
            ->whereHas('checkout', fn ($q) => $q->open())
            ->latest('id_checkout_item')
            ->first();

        if ($item === null) {
            return null;
        }

        return EventRegistrationCheckout::query()->find($item->checkout_id);
    }

    public function latestCheckoutForRegistration(EventRegistration $registration): ?EventRegistrationCheckout
    {
        $item = EventRegistrationCheckoutItem::query()
            ->where('registration_id', $registration->id_registration)
            ->latest('id_checkout_item')
            ->first();

        if ($item === null) {
            return null;
        }

        return EventRegistrationCheckout::query()->find($item->checkout_id);
    }

    public function rejectPendingProofsForCheckout(EventRegistrationCheckout $checkout, string $message): void
    {
        EventRegistrationPaymentProof::query()
            ->where('checkout_id', $checkout->id_checkout)
            ->where('review_status', PaymentProofReviewStatus::Pending->value)
            ->update([
                'review_status' => PaymentProofReviewStatus::Rejected->value,
                'review_message' => $message,
                'reviewed_at' => time(),
                'updated_at' => time(),
            ]);
    }

    public function assertOwnedBy(EventRegistrationCheckout $checkout, int $playerId): void
    {
        if ((int) $checkout->player_id !== $playerId) {
            throw new InvalidArgumentException('Checkout not found.');
        }
    }

    private function proofCancelledByMessage(EventRegistrationCheckout $checkout): string
    {
        $checkout->loadMissing('player');
        $player = $checkout->player;
        $firstName = trim((string) ($player?->first_name ?? ''));
        if ($firstName === '') {
            $firstName = trim((string) ($player?->getAttribute('google_name') ?? ''));
        }
        if ($firstName === '') {
            $firstName = 'Player';
        }

        return "Proof cancelled by {$firstName}";
    }
}
