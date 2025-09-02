<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionParticipationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_participation' => $this->id_participation,
            'edition_id' => $this->edition_id,
            'player_id' => $this->player_id,
            'division_id' => $this->division_id,
            'is_minor' => $this->is_minor,
            'is_student' => $this->is_student,
            'discount' => $this->discount,
            'payment' => $this->payment,
            'absence_reason' => $this->absence_reason,
            'ban_reason' => $this->ban_reason,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'player' => $this->whenLoaded('player', function () {
                return new PlayerResource($this->player);
            }),
            'edition' => $this->whenLoaded('edition', function () {
                return new EditionResource($this->edition);
            }),
            'division' => $this->whenLoaded('division', function () {
                return new DivisionResource($this->division);
            }),
        ];
    }
}
