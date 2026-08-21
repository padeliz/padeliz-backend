<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerCustomFieldOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player_custom_field_option' => $this->id_player_custom_field_option,
            'player_custom_field_id' => $this->player_custom_field_id,
            'code' => $this->code,
            'label' => $this->label,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
