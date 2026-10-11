<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerCustomFieldValueImportRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player_custom_field_value_import_row' => $this->id_player_custom_field_value_import_row,
            'player_custom_field_value_import_id' => $this->player_custom_field_value_import_id,
            'row_index' => $this->row_index,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'player_user_id' => $this->player_user_id,
            'action' => $this->action,
            'answers_json' => $this->answers_json,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'player' => $this->whenLoaded('player', function () {
                return $this->player ? new UserSummaryResource($this->player) : null;
            }),
        ];
    }
}
