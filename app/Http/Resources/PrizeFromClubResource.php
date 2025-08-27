<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrizeFromClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_club_prize' => $this->id_club_prize,
            'club_id' => $this->club_id,
            'prize_id' => $this->prize_id,
            'image' => $this->image,
            'prize' => new PrizeResource($this->whenLoaded('prize')),
        ];
    }
}
