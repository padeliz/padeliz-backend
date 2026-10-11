<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventReferralAttributionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_attribution' => $this->id_attribution,
            'event_id' => $this->event_id,
            'referred_player_user_id' => $this->referred_player_user_id,
            'referrer_player_user_id' => $this->referrer_player_user_id,
            'referral_code_id' => $this->referral_code_id,
            'checkout_id' => $this->checkout_id,
            'status' => $this->status?->value ?? $this->status,
            'referred_player' => $this->whenLoaded('referredPlayer', function () {
                return new UserSummaryResource($this->referredPlayer);
            }),
            'referrer_player' => $this->whenLoaded('referrerPlayer', function () {
                return new UserSummaryResource($this->referrerPlayer);
            }),
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
