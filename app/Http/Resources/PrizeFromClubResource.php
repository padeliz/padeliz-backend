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
            'box_1_text' => $this->box_1_text,
            'box_1_bg_color' => $this->box_1_bg_color,
            'box_1_text_color' => $this->box_1_text_color,
            'box_2_text' => $this->box_2_text,
            'box_2_bg_color' => $this->box_2_bg_color,
            'box_2_text_color' => $this->box_2_text_color,
            'prize' => new PrizeResource($this->whenLoaded('prize')),
        ];
    }
}
