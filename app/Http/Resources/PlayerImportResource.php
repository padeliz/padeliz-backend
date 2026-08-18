<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player_import' => $this->id_player_import,
            'imported_by_player_id' => $this->imported_by_player_id,
            'format' => $this->format,
            'original_filename' => $this->original_filename,
            'included_count' => $this->included_count,
            'created_count' => $this->created_count,
            'attached_count' => $this->attached_count,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'importedByPlayer' => $this->whenLoaded('importedByPlayer', function () {
                return $this->importedByPlayer
                    ? new PlayerResource($this->importedByPlayer)
                    : null;
            }),
            'rows' => $this->whenLoaded('rows', function () {
                return PlayerImportRowResource::collection($this->rows);
            }),
        ];
    }
}
