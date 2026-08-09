<?php

declare(strict_types=1);

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
            'url' => $this->url,
            'template' => $this->template,
            'box_1_text' => $this->box_1_text,
            'box_1_bg_color' => $this->box_1_bg_color,
            'box_1_text_color' => $this->box_1_text_color,
            'box_2_text' => $this->box_2_text,
            'box_2_bg_color' => $this->box_2_bg_color,
            'box_2_text_color' => $this->box_2_text_color,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'club' => $this->whenLoaded('club', function () {
                return new ClubResource($this->club);
            }),
            'prize' => $this->whenLoaded('prize', function () {
                return new PrizeResource($this->prize);
            }),
        ];
    }
}
