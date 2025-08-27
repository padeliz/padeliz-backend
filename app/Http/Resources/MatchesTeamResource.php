<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchesTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_team' => $this->id_team,
            'player_1' => $this->player_1,
            'player_2' => $this->player_2,
            'player1' => $this->whenLoaded('player1', function () {
                return new PlayerResource($this->player1);
            }),
            'player2' => $this->whenLoaded('player2', function () {
                return new PlayerResource($this->player2);
            }),
        ];
    }
}
