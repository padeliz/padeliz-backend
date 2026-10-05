<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventDivisionCourtResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_event_division_court' => $this->id_event_division_court,
            'event_division_id' => $this->event_division_id,
            'court_id' => $this->court_id,
            'display_order' => $this->display_order,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'court' => $this->whenLoaded('court', function () {
                return new CourtResource($this->court);
            }),
            'event_division' => $this->whenLoaded('eventDivision', function () {
                return new EventDivisionResource($this->eventDivision);
            }),
        ];
    }
}
