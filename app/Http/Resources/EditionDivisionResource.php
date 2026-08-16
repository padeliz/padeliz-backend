<?php

declare(strict_types=1);

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
            'division_title' => $this->division_title,
            'players_limit' => $this->players_limit,
            'playing_start_time' => $this->formatted_playing_start_time,
            'playing_end_time' => $this->formatted_playing_end_time,
            'player_opponents_nr' => $this->player_opponents_nr,
            'player_matches_repeat' => $this->player_matches_repeat,
            'include_scores' => $this->include_scores,
            'has_demonstrative_match' => $this->has_demonstrative_match,
            'allow_replacements' => $this->allow_replacements,
            'points_per_match' => $this->points_per_match,
            'final_points_target' => $this->final_points_target,
            'include_final' => $this->include_final,
            'template_version' => $this->template_version,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'edition' => $this->whenLoaded('edition', function () {
                return new EditionResource($this->edition);
            }),
            'division' => $this->whenLoaded('division', function () {
                return new DivisionResource($this->division);
            }),
            'courts' => $this->whenLoaded('courts', function () {
                return CourtResource::collection($this->courts);
            }),
        ];
    }
}
