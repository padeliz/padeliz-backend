<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerProfileImageImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player_profile_image_import' => $this->id_player_profile_image_import,
            'imported_by_manager_user_id' => $this->imported_by_manager_user_id,
            'original_filename' => $this->original_filename,
            'included_count' => $this->included_count,
            'uploaded_count' => $this->uploaded_count,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'importedByManager' => $this->whenLoaded('importedByManager', function () {
                return $this->importedByManager
                    ? new UserSummaryResource($this->importedByManager)
                    : null;
            }),
            'rows' => $this->whenLoaded('rows', function () {
                return PlayerProfileImageImportRowResource::collection($this->rows);
            }),
        ];
    }
}
