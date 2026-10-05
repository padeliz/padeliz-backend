<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DivisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_division' => $this->id_division,
            'name' => $this->name,
            'color' => $this->color,
            'its_first_event_id' => $this->its_first_event_id,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'its_first_event' => $this->whenLoaded('itsFirstEvent', function () {
                return new EventResource($this->itsFirstEvent);
            }),
            'event_divisions' => $this->whenLoaded('eventDivisions', function () {
                return EventDivisionResource::collection($this->eventDivisions);
            }),
        ];
    }
}
