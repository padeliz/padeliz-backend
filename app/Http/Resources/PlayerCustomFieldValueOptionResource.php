<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerCustomFieldValueOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player_custom_field_value_option' => $this->id_player_custom_field_value_option,
            'player_custom_field_value_id' => $this->player_custom_field_value_id,
            'option_id' => $this->option_id,
            'option_label_snapshot' => $this->option_label_snapshot,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
