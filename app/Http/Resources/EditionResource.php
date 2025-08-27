<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_edition' => $this->id_edition,
            'location_id' => $this->location_id,
            'name' => $this->name,
            'date' => $this->date,
            'entry_fee' => $this->entry_fee,
            'students_entry_fee' => $this->students_entry_fee,
            'arriving_time' => $this->arriving_time,
            'closing_time' => $this->closing_time,
            'location' => new LocationResource($this->whenLoaded('location')),
            'edition_divisions' => EditionDivisionResource::collection($this->whenLoaded('editionDivisions')),
            'participations' => EditionParticipationResource::collection($this->whenLoaded('participations')),
        ];
    }
}


