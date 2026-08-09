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
            'prize_id' => $this->prize_id,
            'prize_quantity' => $this->prize_quantity,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'edition' => $this->whenLoaded('edition', function () {
                return new EditionResource($this->edition);
            }),
            'prize' => $this->whenLoaded('prize', function () {
                return new PrizeResource($this->prize);
            }),
            'rules' => $this->whenLoaded('rules', function () {
                return EditionLotteryRuleResource::collection($this->rules);
            }),
            'luckies' => $this->whenLoaded('luckies', function () {
                return EditionLotteryLuckyResource::collection($this->luckies);
            }),
        ];
    }
}
