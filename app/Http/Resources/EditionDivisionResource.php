<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionDivisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_edition_division' => $this->id_edition_division,
            'edition_id' => $this->edition_id,
            'division_id' => $this->division_id,
            'division_title' => $this->division_title,
            'players_limit' => $this->players_limit,
            'skill_order' => $this->skill_order,
            'playing_start_time' => $this->formatted_playing_start_time,
            'playing_end_time' => $this->formatted_playing_end_time,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'edition' => $this->whenLoaded('edition', function () {
                return new EditionResource($this->edition);
            }),
            'division' => $this->whenLoaded('division', function () {
                return new DivisionResource($this->division);
            }),
            'courts' => $this->whenLoaded('courts', function () {
                return CourtResource::collection($this->courts);
            }),
            'schedule' => $this->whenLoaded('schedule', function () {
                return $this->schedule
                    ? new EditionDivisionScheduleResource($this->schedule)
                    : null;
            }),
        ];
    }
}
