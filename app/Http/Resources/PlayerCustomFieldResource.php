<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerCustomFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player_custom_field' => $this->id_player_custom_field,
            'code' => $this->code,
            'label' => $this->label,
            'help_text' => $this->help_text,
            'sort_order' => $this->sort_order,
            'type' => $this->type?->value ?? $this->type,
            'rules_json' => $this->rules_json,
            'is_required' => $this->is_required,
            'archived_at' => $this->archived_at,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'options' => $this->whenLoaded('options', function () {
                return PlayerCustomFieldOptionResource::collection($this->options);
            }),
        ];
    }
}
