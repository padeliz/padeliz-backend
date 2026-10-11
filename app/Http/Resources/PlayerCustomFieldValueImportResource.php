<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerCustomFieldValueImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player_custom_field_value_import' => $this->id_player_custom_field_value_import,
            'imported_by_manager_user_id' => $this->imported_by_manager_user_id,
            'format' => $this->format,
            'original_filename' => $this->original_filename,
            'event_id' => $this->event_id,
            'included_count' => $this->included_count,
            'values_new_count' => $this->values_new_count,
            'values_replaced_count' => $this->values_replaced_count,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'importedByManager' => $this->whenLoaded('importedByManager', function () {
                return $this->importedByManager
                    ? new UserSummaryResource($this->importedByManager)
                    : null;
            }),
            'event' => $this->whenLoaded('event', function () {
                return $this->event ? new EventResource($this->event) : null;
            }),
            'rows' => $this->whenLoaded('rows', function () {
                return PlayerCustomFieldValueImportRowResource::collection($this->rows);
            }),
        ];
    }
}
