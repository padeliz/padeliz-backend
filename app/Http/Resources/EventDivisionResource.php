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
            'level_min' => $this->level_min,
            'level_max' => $this->level_max,
            'is_eligible' => $this->when(
                array_key_exists('is_eligible', $this->resource->getAttributes()),
                fn () => $this->is_eligible
            ),
            'check_in_at' => $this->check_in_at,
            'playing_starts_at' => $this->playing_starts_at,
            'playing_ends_at' => $this->playing_ends_at,
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
