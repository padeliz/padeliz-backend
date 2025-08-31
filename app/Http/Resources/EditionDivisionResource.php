<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionDivisionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_edition_division' => $this->id_edition_division,
            'edition_id' => $this->edition_id,
            'division_id' => $this->division_id,
            'players_limit' => $this->players_limit,
            'court' => $this->court,
            'playing_start_time' => $this->playing_start_time,
            'playing_end_time' => $this->playing_end_time,
            'player_opponents_nr' => $this->player_opponents_nr,
            'player_matches_repeat' => $this->player_matches_repeat,
            'include_scores' => $this->include_scores,
            'has_demonstrative_match' => $this->has_demonstrative_match,
            'fixed_teams' => $this->fixed_teams,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'division' => new DivisionResource($this->whenLoaded('division')),
        ];
    }
}
