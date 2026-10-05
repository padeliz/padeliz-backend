<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventLotteryRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_lottery_rule' => $this->id_lottery_rule,
            'lottery_prize_id' => $this->lottery_prize_id,
            'rule_prize_quantity' => $this->rule_prize_quantity,
            'orderliness' => $this->orderliness,
            'event_division_id' => $this->event_division_id,
            'gender' => $this->gender,
            'has_social_status' => $this->has_social_status,
            'legal_age' => $this->legal_age,
            'is_younger_than' => $this->is_younger_than,
            'is_older_than' => $this->is_older_than,
            'has_tshirt_size' => $this->has_tshirt_size,
            'is_guest' => $this->is_guest,
            'is_padel_first_timer' => $this->is_padel_first_timer,
            'is_event_first_timer' => $this->is_event_first_timer,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'luckies_count' => (int) ($this->luckies_count ?? 0),
            'event_division' => $this->whenLoaded('eventDivision', function () {
                return new EventDivisionResource($this->eventDivision);
            }),
            'lottery_prize' => $this->whenLoaded('lotteryPrize', function () {
                return new EventLotteryPrizeResource($this->lotteryPrize);
            }),
        ];
    }
}
