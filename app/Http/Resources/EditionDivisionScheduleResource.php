<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionDivisionScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_edition_division_schedule' => $this->id_edition_division_schedule,
            'edition_division_id' => $this->edition_division_id,
            'player_opponents_nr' => $this->player_opponents_nr,
            'player_matches_repeat' => $this->player_matches_repeat,
            'include_scores' => $this->include_scores,
            'has_demonstrative_match' => $this->has_demonstrative_match,
            'allow_replacements' => $this->allow_replacements,
            'points_per_match' => $this->points_per_match,
            'final_points_target' => $this->final_points_target,
            'include_final' => $this->include_final,
            'template_version' => $this->template_version,
            'adjust' => $this->adjust,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
