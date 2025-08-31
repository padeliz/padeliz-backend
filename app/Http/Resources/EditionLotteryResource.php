<?php

declare(strict_types=1);

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
            'id_lottery' => $this->id_lottery,
            'edition_id' => $this->edition_id,
            'orderliness' => $this->orderliness,
            'division_id' => $this->division_id,
            'prize_id' => $this->prize_id,
            'total_draws_nr' => $this->total_draws_nr,
            'inserted_at' => $this->inserted_at,
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
