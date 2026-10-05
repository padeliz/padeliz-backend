<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BenefitRedemptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_redemption' => $this->id_redemption,
            'checkout_item_id' => $this->checkout_item_id,
            'benefit_definition_id' => $this->benefit_definition_id,
            'entitlement_id' => $this->entitlement_id,
            'referral_attribution_id' => $this->referral_attribution_id,
            'amount_ron' => $this->amount_ron,
            'benefit_name' => $this->benefit_name,
            'benefit_snapshot_hash' => $this->benefit_snapshot_hash,
            'selection_source' => $this->selection_source?->value ?? $this->selection_source,
            'reason' => $this->reason,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
