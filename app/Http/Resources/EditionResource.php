<?php

declare(strict_types=1);

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
            'organizer_id' => $this->organizer_id,
            'include_club_host_on_pdf' => (bool) $this->include_club_host_on_pdf,
            'name' => $this->name,
            'date' => $this->formatted_date,
            'entry_fee' => $this->entry_fee,
            'students_entry_fee' => $this->students_entry_fee,
            'arriving_time' => $this->formatted_arriving_time,
            'closing_time' => $this->formatted_closing_time,
            'finalized_at' => $this->finalized_at,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'location' => $this->whenLoaded('location', function () {
                return new LocationResource($this->location);
            }),
            'organizer' => $this->whenLoaded('organizer', function () {
                return $this->organizer ? new OrganizerResource($this->organizer) : null;
            }),
            'edition_divisions' => $this->whenLoaded('divisions', function () {
                return EditionDivisionResource::collection($this->divisions);
            }),
            'participations' => $this->whenLoaded('participations', function () {
                return EditionParticipationResource::collection($this->participations);
            }),
            'lotteries' => $this->whenLoaded('lotteries', function () {
                return EditionLotteryResource::collection($this->lotteries);
            }),
        ];
    }
}
