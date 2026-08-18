<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionLotteryPrizeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_lottery_prize' => $this->id_lottery_prize,
            'lottery_id' => $this->lottery_id,
            'prize_id' => $this->prize_id,
            'prize_quantity' => $this->prize_quantity,
            'sponsor_id' => $this->sponsor_id,
            'display_order' => $this->display_order,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'lottery' => $this->whenLoaded('lottery', function () {
                return new EditionLotteryResource($this->lottery);
            }),
            'prize' => $this->whenLoaded('prize', function () {
                return new PrizeResource($this->prize);
            }),
            'sponsor' => $this->whenLoaded('sponsor', function () {
                return $this->sponsor ? new SponsorResource($this->sponsor) : null;
            }),
            'rules' => $this->whenLoaded('rules', function () {
                return EditionLotteryRuleResource::collection($this->rules);
            }),
            'luckies' => $this->whenLoaded('luckies', function () {
                return EditionLotteryLuckyResource::collection($this->luckies);
            }),
            'rule_prize_quantity_total' => $this->when(
                array_key_exists('rule_prize_quantity_total', $this->resource->getAttributes()),
                fn () => $this->rule_prize_quantity_total
            ),
            'activity_at' => $this->when(
                array_key_exists('activity_at', $this->resource->getAttributes()),
                fn () => $this->activity_at
            ),
        ];
    }
}
