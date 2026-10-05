<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_match' => $this->id_match,
            'event_division_id' => $this->event_division_id,
            'event_division_court_id' => $this->event_division_court_id,
            'match_kind' => $this->match_kind,
            'sequence_no' => $this->sequence_no,
            'scheduled_start' => $this->scheduled_start?->format('H:i:s'),
            'scheduled_end' => $this->scheduled_end?->format('H:i:s'),
            'points_target' => $this->points_target,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'event_division' => $this->whenLoaded('eventDivision', function () {
                return new EventDivisionResource($this->eventDivision);
            }),
            'event_division_court' => $this->whenLoaded('eventDivisionCourt', function () {
                return new EventDivisionCourtResource($this->eventDivisionCourt);
            }),
            'sides' => $this->whenLoaded('sides', function () {
                return MatchSideResource::collection($this->sides);
            }),
        ];
    }
}
