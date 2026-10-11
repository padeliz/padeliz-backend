<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EventRegistrationCheckoutItem;
use App\Models\EventRegistrationPaymentAttempt;
use App\Models\EventRegistrationPaymentProof;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $checkoutId = $this->resolveCheckoutId();

        return [
            'id_registration' => $this->id_registration,
            'event_id' => $this->event_id,
            'player_user_id' => $this->player_user_id,
            'event_division_id' => $this->event_division_id,
            'checkout_id' => $checkoutId,
            'is_minor' => $this->is_minor,
            'is_student' => $this->is_student,
            'payment' => $this->payment,
            'amount_due' => $this->amountDue(),
            'payment_due_at' => $this->payment_due_at,
            'is_overdue' => $this->isPaymentOverdue(),
            'lifecycle_status' => $this->lifecycle_status?->value ?? $this->lifecycle_status,
            'payment_status' => $this->payment_status?->value ?? $this->payment_status,
            'payment_method' => $this->payment_method?->value ?? $this->payment_method,
            'paid_at' => $this->paid_at,
            'cancelled_at' => $this->cancelled_at,
            'declined_at' => $this->declined_at,
            'cancel_reason' => $this->cancel_reason?->value ?? $this->cancel_reason,
            'decline_reason' => $this->decline_reason?->value ?? $this->decline_reason,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'redemptions' => $this->resolveRedemptions(),
            'event' => $this->whenLoaded('event', function () {
                return new EventResource($this->event);
            }),
            'player' => $this->whenLoaded('player', function () {
                return new UserSummaryResource($this->player);
            }),
            'event_division' => $this->whenLoaded('eventDivision', function () {
                return $this->eventDivision === null
                    ? null
                    : new EventDivisionResource($this->eventDivision);
            }),
            'payment_attempts' => $this->resolvePaymentAttempts(),
            'payment_proofs' => $this->resolvePaymentProofs(),
        ];
    }

    private function resolveCheckoutId(): ?int
    {
        $item = $this->resolveLatestCheckoutItem();

        return $item !== null ? (int) $item->checkout_id : null;
    }

    private function resolveLatestCheckoutItem(): ?EventRegistrationCheckoutItem
    {
        if ($this->relationLoaded('latestCheckoutItem')) {
            return $this->latestCheckoutItem;
        }

        if ($this->relationLoaded('checkoutItems') && $this->checkoutItems->isNotEmpty()) {
            return $this->checkoutItems->sortByDesc('id_checkout_item')->first();
        }

        return EventRegistrationCheckoutItem::query()
            ->where('registration_id', $this->id_registration)
            ->orderByDesc('id_checkout_item')
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveRedemptions(): array
    {
        $item = $this->resolveLatestCheckoutItem();
        if ($item === null) {
            return [];
        }

        $redemptions = $item->relationLoaded('redemptions')
            ? $item->redemptions
            : $item->redemptions()->get();

        return BenefitRedemptionResource::collection($redemptions)->resolve();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolvePaymentAttempts(): array
    {
        $checkoutId = $this->resolveCheckoutId();
        if ($checkoutId === null) {
            return [];
        }

        return EventRegistrationPaymentAttempt::query()
            ->where('checkout_id', $checkoutId)
            ->orderByDesc('id_payment_attempt')
            ->get()
            ->map(static fn ($attempt) => [
                'id_payment_attempt' => $attempt->id_payment_attempt,
                'checkout_id' => $attempt->checkout_id,
                'amount' => $attempt->amount,
                'currency' => $attempt->currency,
                'status' => $attempt->status?->value ?? $attempt->status,
                'ntp_id' => $attempt->ntp_id,
                'order_id' => $attempt->order_id,
                'redirect_url' => $attempt->redirect_url,
                'error_message' => $attempt->error_message,
                'inserted_at' => $attempt->inserted_at,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolvePaymentProofs(): array
    {
        $checkoutId = $this->resolveCheckoutId();
        if ($checkoutId === null) {
            return [];
        }

        return EventRegistrationPaymentProof::query()
            ->where('checkout_id', $checkoutId)
            ->orderByDesc('id_payment_proof')
            ->get()
            ->map(static fn ($proof) => [
                'id_payment_proof' => $proof->id_payment_proof,
                'checkout_id' => $proof->checkout_id,
                'url' => $proof->url,
                'review_status' => $proof->review_status?->value ?? $proof->review_status,
                'review_message' => $proof->review_message,
                'reviewed_at' => $proof->reviewed_at,
                'inserted_at' => $proof->inserted_at,
            ])
            ->all();
    }
}
