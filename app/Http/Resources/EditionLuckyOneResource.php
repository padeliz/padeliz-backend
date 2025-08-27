<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionLuckyOneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lottery_id' => $this->lottery_id,
            'drawn_participation_id' => $this->drawn_participation_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'drawn_participation' => $this->whenLoaded('drawnParticipation', function () {
                return new EditionParticipationResource($this->drawnParticipation);
            }),
        ];
    }
}


