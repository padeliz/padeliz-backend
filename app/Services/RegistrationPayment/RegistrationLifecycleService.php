<?php

declare(strict_types=1);

namespace App\Services\RegistrationPayment;

use App\Enums\Registration\PaymentProofReviewStatus;
use App\Enums\Registration\RegistrationCancelReason;
use App\Enums\Registration\RegistrationDeclineReason;
use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentMethod;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\EventParticipation;
use App\Models\EventRegistration;
use App\Models\EventRegistrationCheckoutItem;
use App\Models\EventRegistrationPaymentProof;
use App\Services\Push\ExpoPushService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegistrationLifecycleService
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly ExpoPushService $expoPushService,
    ) {
    }

    public function cancelByPlayer(
        EventRegistration $registration,
        RegistrationCancelReason $reason,
        bool $skipCheckoutSideEffects = false
    ): EventRegistration {
        $this->assertActive($registration);

        $registration->loadMissing('event');
        $event = $registration->event;
        if ($event === null) {
            throw new InvalidArgumentException('PadelEvent not found for registration.');
        }

        $until = $event->cancellation_allowed_until;
        if ($until === null || time() >= (int) $until) {
            throw new InvalidArgumentException('Player self-cancel is not allowed for this edition.');
        }

        return $this->finalizeCancelOrDecline(
            $registration,
            RegistrationLifecycleStatus::Cancelled,
            $reason,
            null,
            $skipCheckoutSideEffects
        );
    }

    public function cancelByManager(
        EventRegistration $registration,
        RegistrationCancelReason $reason,
        bool $skipCheckoutSideEffects = false
    ): EventRegistration {
        $this->assertActive($registration);
        $this->assertEditionNotFinalized($registration);

        return $this->finalizeCancelOrDecline(
            $registration,
            RegistrationLifecycleStatus::Cancelled,
            $reason,
            null,
            $skipCheckoutSideEffects
        );
    }

    public function declineByManager(EventRegistration $registration, RegistrationDeclineReason $reason): EventRegistration
    {
        $this->assertActive($registration);
        $this->assertEditionNotFinalized($registration);

        return $this->finalizeCancelOrDecline(
            $registration,
            RegistrationLifecycleStatus::Declined,
            null,
            $reason,
            false
        );
    }

    public function markRefunded(EventRegistration $registration): EventRegistration
    {
        if ($registration->payment_status !== RegistrationPaymentStatus::RefundPending) {
            throw new InvalidArgumentException('Only refund_pending registrations can be marked refunded.');
        }

        $registration->payment_status = RegistrationPaymentStatus::Refunded;
        $registration->save();

        app(\App\Services\Referral\ReferralService::class)->releaseOnRefund($registration);

        $this->expoPushService->notifyRegistrationStatus(
            (int) $registration->player_user_id,
            'refunded',
            ['registration_id' => $registration->id_registration]
        );

        return $registration->refresh();
    }

    /**
     * @param  list<int>  $registrationIds
     * @return list<EventRegistration>
     */
    public function cancelManyByPlayer(array $registrationIds, int $playerId, RegistrationCancelReason $reason): array
    {
        $results = [];
        foreach ($registrationIds as $id) {
            $registration = EventRegistration::query()
                ->where('id_registration', $id)
                ->where('player_user_id', $playerId)
                ->firstOrFail();
            $results[] = $this->cancelByPlayer($registration, $reason);
        }

        return $results;
    }

    private function finalizeCancelOrDecline(
        EventRegistration $registration,
        RegistrationLifecycleStatus $lifecycle,
        ?RegistrationCancelReason $cancelReason,
        ?RegistrationDeclineReason $declineReason,
        bool $skipCheckoutSideEffects
    ): EventRegistration {
        return DB::transaction(function () use (
            $registration,
            $lifecycle,
            $cancelReason,
            $declineReason,
            $skipCheckoutSideEffects
        ) {
            $registration = EventRegistration::query()
                ->where('id_registration', $registration->id_registration)
                ->lockForUpdate()
                ->firstOrFail();

            $amountDue = $this->registrationService->amountDue($registration);
            $wasPaid = $registration->payment_status === RegistrationPaymentStatus::Paid;
            $wasNetopia = $registration->payment_method === RegistrationPaymentMethod::Netopia;

            $registration->lifecycle_status = $lifecycle;
            if ($lifecycle === RegistrationLifecycleStatus::Cancelled) {
                $registration->cancelled_at = time();
                $registration->cancel_reason = $cancelReason;
            } else {
                $registration->declined_at = time();
                $registration->decline_reason = $declineReason;
            }

            $becameRefunded = false;
            if ($wasPaid) {
                if ($amountDue === 0) {
                    $registration->payment_status = RegistrationPaymentStatus::Refunded;
                    $becameRefunded = true;
                } else {
                    $registration->payment_status = RegistrationPaymentStatus::RefundPending;
                }
            } elseif (in_array($registration->payment_status, [
                RegistrationPaymentStatus::Unpaid,
                RegistrationPaymentStatus::AwaitingProofReview,
            ], true)) {
                $registration->payment_status = RegistrationPaymentStatus::Unpaid;
                $this->rejectPendingProofs($registration);
            }

            $registration->save();
            $this->deleteParticipations($registration);

            if ($becameRefunded) {
                app(\App\Services\Referral\ReferralService::class)->releaseOnRefund($registration);
            }

            if (! $wasPaid) {
                $stillUnpaid = EventRegistration::query()
                    ->active()
                    ->where('event_id', $registration->event_id)
                    ->where('player_user_id', $registration->player_user_id)
                    ->where('id_registration', '!=', $registration->id_registration)
                    ->whereIn('payment_status', [
                        RegistrationPaymentStatus::Unpaid->value,
                        RegistrationPaymentStatus::AwaitingProofReview->value,
                    ])
                    ->exists();
                if (! $stillUnpaid) {
                    app(\App\Services\Referral\ReferralService::class)->clearPendingAttribution(
                        (int) $registration->event_id,
                        (int) $registration->player_user_id
                    );
                }
            }

            if (! $skipCheckoutSideEffects) {
                app(CheckoutService::class)->softCancelItemForRegistration($registration);

                if ($wasPaid && $amountDue > 0 && $wasNetopia) {
                    app(NetopiaPaymentService::class)->refundPaidRegistrationLine($registration->fresh());
                }
            }

            $event = $lifecycle === RegistrationLifecycleStatus::Cancelled ? 'cancelled' : 'declined';
            if ($registration->fresh()->payment_status === RegistrationPaymentStatus::RefundPending) {
                $this->expoPushService->notifyRegistrationStatus(
                    (int) $registration->player_user_id,
                    'refund_pending',
                    ['registration_id' => $registration->id_registration]
                );
            }
            $this->expoPushService->notifyRegistrationStatus(
                (int) $registration->player_user_id,
                $event,
                ['registration_id' => $registration->id_registration]
            );

            return $registration->refresh();
        });
    }

    private function rejectPendingProofs(EventRegistration $registration): void
    {
        $checkoutIds = EventRegistrationCheckoutItem::query()
            ->where('registration_id', $registration->id_registration)
            ->pluck('checkout_id')
            ->all();

        if ($checkoutIds === []) {
            return;
        }

        EventRegistrationPaymentProof::query()
            ->whereIn('checkout_id', $checkoutIds)
            ->where('review_status', PaymentProofReviewStatus::Pending->value)
            ->update([
                'review_status' => PaymentProofReviewStatus::Rejected->value,
                'review_message' => 'Registration cancelled or declined',
                'reviewed_at' => time(),
                'updated_at' => time(),
            ]);
    }

    private function deleteParticipations(EventRegistration $registration): void
    {
        EventParticipation::query()
            ->where('event_id', $registration->event_id)
            ->where('player_user_id', $registration->player_user_id)
            ->delete();
    }

    private function assertActive(EventRegistration $registration): void
    {
        if ($registration->lifecycle_status !== RegistrationLifecycleStatus::Active) {
            throw new InvalidArgumentException('Only active registrations can be cancelled or declined.');
        }
    }

    private function assertEditionNotFinalized(EventRegistration $registration): void
    {
        $registration->loadMissing('event');
        if ($registration->event?->finalized_at !== null) {
            throw new InvalidArgumentException('Cannot cancel or decline registrations after the edition is finalized.');
        }
    }
}
