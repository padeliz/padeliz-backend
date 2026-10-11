<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BenefitEntitlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_entitlement' => $this->id_entitlement,
            'benefit_definition_id' => $this->benefit_definition_id,
            'player_user_id' => $this->player_user_id,
            'remaining_uses' => $this->remaining_uses,
            'valid_from' => $this->valid_from,
            'valid_until' => $this->valid_until,
            'source_type' => $this->source_type?->value ?? $this->source_type,
            'source_id' => $this->source_id,
            'status' => $this->status?->value ?? $this->status,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'benefit_definition' => $this->whenLoaded('benefitDefinition', function () {
                return new BenefitDefinitionResource($this->benefitDefinition);
            }),
            'player' => $this->whenLoaded('player', function () {
                return new UserSummaryResource($this->player);
            }),
        ];
    }
}
