<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventDivisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_event_division' => $this->id_event_division,
            'event_id' => $this->event_id,
            'division_id' => $this->division_id,
            'division_title' => $this->division_title,
            'players_limit' => $this->players_limit,
            'entry_fee' => $this->entry_fee,
            'skill_order' => $this->skill_order,
            'playing_start_time' => $this->formatted_playing_start_time,
            'playing_end_time' => $this->formatted_playing_end_time,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'event' => $this->whenLoaded('event', function () {
                return new EventResource($this->event);
            }),
            'division' => $this->whenLoaded('division', function () {
                return new DivisionResource($this->division);
            }),
            'courts' => $this->whenLoaded('courts', function () {
                return CourtResource::collection($this->courts);
            }),
            'schedule' => $this->whenLoaded('schedule', function () {
                return $this->schedule
                    ? new EventDivisionScheduleResource($this->schedule)
                    : null;
            }),
        ];
    }
}
