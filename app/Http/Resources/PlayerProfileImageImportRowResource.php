<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerProfileImageImportRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player_profile_image_import_row' => $this->id_player_profile_image_import_row,
            'player_profile_image_import_id' => $this->player_profile_image_import_id,
            'row_index' => $this->row_index,
            'original_filename' => $this->original_filename,
            'player_user_id' => $this->player_user_id,
            'player_profile_image_id' => $this->player_profile_image_id,
            'is_pdf_primary' => (bool) $this->is_pdf_primary,
            'action' => $this->action,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
