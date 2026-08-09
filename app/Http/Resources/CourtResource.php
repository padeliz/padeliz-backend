<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_court' => $this->id_court,
            'location_id' => $this->location_id,
            'name' => $this->name,
            'is_indoor' => $this->is_indoor,
            'display_order' => $this->display_order,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'location' => $this->whenLoaded('location', function () {
                return new LocationResource($this->location);
            }),
        ];
    }
}
