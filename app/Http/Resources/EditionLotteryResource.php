<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionLotteryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_lottery' => $this->id_lottery,
            'edition_id' => $this->edition_id,
            'name' => $this->name,
            'display_order' => $this->display_order,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'edition' => $this->whenLoaded('edition', function () {
                return new EditionResource($this->edition);
            }),
            'lottery_prizes' => $this->whenLoaded('lotteryPrizes', function () {
                return EditionLotteryPrizeResource::collection($this->lotteryPrizes);
            }),
        ];
    }
}
