<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionLotteryLuckyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_lucky' => $this->id_lucky,
            'lottery_rule_id' => $this->lottery_rule_id,
            'drawn_participation_id' => $this->drawn_participation_id,
            'drawn_at' => $this->drawn_at,
            'available_at' => $this->available_at,
            'announced_at' => $this->announced_at,
            'accepted_at' => $this->accepted_at,
            'rejected_at' => $this->rejected_at,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'lottery_rule' => $this->whenLoaded('lotteryRule', function () {
                return new EditionLotteryRuleResource($this->lotteryRule);
            }),
            'drawn_participation' => $this->when(
                $this->relationLoaded('drawnParticipation') || $this->relationLoaded('participation'),
                function () {
                    $participation = $this->relationLoaded('drawnParticipation')
                        ? $this->drawnParticipation
                        : $this->participation;

                    return new EditionParticipationResource($participation);
                }
            ),
        ];
    }
}
