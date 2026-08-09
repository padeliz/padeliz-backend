<?php

declare(strict_types=1);

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
            'player_1_is_collecting_points' => $this->player_1_is_collecting_points,
            'player_2_is_collecting_points' => $this->player_2_is_collecting_points,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
