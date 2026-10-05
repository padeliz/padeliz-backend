<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BenefitDefinitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_benefit' => $this->id_benefit,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
            'amount_type' => $this->amount_type?->value ?? $this->amount_type,
            'amount_value' => $this->amount_value,
            'max_discount_ron' => $this->max_discount_ron,
            'scope_type' => $this->scope_type?->value ?? $this->scope_type,
            'club_id' => $this->club_id,
            'valid_from' => $this->valid_from,
            'valid_until' => $this->valid_until,
            'player_optional' => $this->player_optional,
            'stack_group' => $this->stack_group,
            'exclusivity' => $this->exclusivity?->value ?? $this->exclusivity,
            'priority' => $this->priority,
            'max_redemptions_per_player' => $this->max_redemptions_per_player,
            'max_redemptions_global' => $this->max_redemptions_global,
            'max_redemptions_per_event' => $this->max_redemptions_per_event,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'conditions' => $this->whenLoaded('conditions', function () {
                return $this->conditions->map(static fn ($c) => [
                    'id_benefit_condition' => $c->id_benefit_condition,
                    'condition_type' => $c->condition_type?->value ?? $c->condition_type,
                    'parameters' => $c->parameters,
                ])->all();
            }),
            'event_ids' => $this->whenLoaded('events', function () {
                return $this->events->pluck('id_event')->all();
            }),
        ];
    }
}
