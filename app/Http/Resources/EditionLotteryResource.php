<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionLotteryResource extends JsonResource
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
            'edition_id' => $this->edition_id,
            'prize_id' => $this->prize_id,
            'division_id' => $this->division_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'prize' => $this->whenLoaded('prize', function () {
                return new PrizeResource($this->prize);
            }),
            'division' => $this->whenLoaded('division', function () {
                return new DivisionResource($this->division);
            }),
            'lucky_ones' => $this->whenLoaded('luckyOnes', function () {
                return EditionLuckyOneResource::collection($this->luckyOnes);
            }),
        ];
    }
}


