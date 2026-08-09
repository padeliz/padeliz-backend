<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrizeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_prize' => $this->id_prize,
            'name' => $this->name,
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
            'clubPrizes' => $this->whenLoaded('clubPrizes', function () {
                return PrizeFromClubResource::collection($this->clubPrizes);
            }),
        ];
    }
}
