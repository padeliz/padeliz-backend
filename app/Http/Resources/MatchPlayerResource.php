<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchPlayerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_match_player' => $this->id_match_player,
            'match_side_id' => $this->match_side_id,
            'slot' => $this->slot,
            'participation_id' => $this->participation_id,
            'player_user_id' => $this->player_user_id,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'participation' => $this->whenLoaded('participation', function () {
                return new EventParticipationResource($this->participation);
            }),
            'player' => $this->whenLoaded('player', function () {
                return new UserSummaryResource($this->player);
            }),
        ];
    }
}
