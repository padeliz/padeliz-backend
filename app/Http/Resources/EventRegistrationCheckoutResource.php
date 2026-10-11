<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventRegistrationCheckoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_checkout' => $this->id_checkout,
            'event_id' => $this->event_id,
            'player_user_id' => $this->player_user_id,
            'status' => $this->status?->value ?? $this->status,
            'total_amount' => $this->total_amount,
            'payment_method' => $this->payment_method?->value ?? $this->payment_method,
            'paid_at' => $this->paid_at,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'player' => $this->whenLoaded('player', fn () => new UserSummaryResource($this->player)),
            'event' => $this->whenLoaded('event', fn () => new EventResource($this->event)),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(static function ($item) {
                    $redemptions = $item->relationLoaded('redemptions')
                        ? $item->redemptions
                        : $item->redemptions()->get();

                    return [
                        'id_checkout_item' => $item->id_checkout_item,
                        'registration_id' => $item->registration_id,
                        'base_amount_ron' => $item->base_amount_ron,
                        'amount' => $item->amount,
                        'item_status' => $item->item_status?->value ?? $item->item_status,
                        'redemptions' => BenefitRedemptionResource::collection($redemptions)->resolve(),
                        'registration' => $item->relationLoaded('registration') && $item->registration !== null
                            ? (new EventRegistrationResource($item->registration))->resolve()
                            : null,
                    ];
                });
            }),
            'payment_attempts' => $this->whenLoaded('paymentAttempts', function () {
                return $this->paymentAttempts->map(static fn ($attempt) => [
                    'id_payment_attempt' => $attempt->id_payment_attempt,
                    'amount' => $attempt->amount,
                    'status' => $attempt->status?->value ?? $attempt->status,
                    'order_id' => $attempt->order_id,
                    'ntp_id' => $attempt->ntp_id,
                    'redirect_url' => $attempt->redirect_url,
                    'error_message' => $attempt->error_message,
                    'inserted_at' => $attempt->inserted_at,
                    'coverage' => $attempt->coverage ?? [],
                ]);
            }),
            'payment_proofs' => $this->whenLoaded('paymentProofs', function () {
                return $this->paymentProofs->map(static fn ($proof) => [
                    'id_payment_proof' => $proof->id_payment_proof,
                    'url' => $proof->url,
                    'review_status' => $proof->review_status?->value ?? $proof->review_status,
                    'review_message' => $proof->review_message,
                    'reviewed_at' => $proof->reviewed_at,
                    'inserted_at' => $proof->inserted_at,
                    'coverage' => $proof->coverage ?? [],
                ]);
            }),
            'latest_attempt' => $this->when(
                $this->relationLoaded('paymentAttempts'),
                function () {
                    $latest = $this->paymentAttempts->sortByDesc('id_payment_attempt')->first();
                    if ($latest === null) {
                        return null;
                    }

                    return [
                        'id_payment_attempt' => $latest->id_payment_attempt,
                        'amount' => $latest->amount,
                        'status' => $latest->status?->value ?? $latest->status,
                        'order_id' => $latest->order_id,
                        'inserted_at' => $latest->inserted_at,
                        'coverage' => $latest->coverage ?? [],
                    ];
                }
            ),
        ];
    }
}
