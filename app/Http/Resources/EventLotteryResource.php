<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventLotteryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_lottery' => (int) $this->id_lottery,
            'event_id' => (int) $this->event_id,
            'name' => $this->name,
            'display_order' => $this->display_order,
            'has_luckies' => (bool) ($this->has_luckies ?? false),
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'event' => $this->whenLoaded('event', function () {
                return new EventResource($this->event);
            }),
            'lottery_prizes' => $this->whenLoaded('lotteryPrizes', function () {
                return EventLotteryPrizeResource::collection($this->lotteryPrizes);
            }),
        ];
    }
}
