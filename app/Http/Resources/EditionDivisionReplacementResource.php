<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionDivisionReplacementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_replacement' => $this->id_replacement,
            'edition_division_id' => $this->edition_division_id,
            'original_participation_id' => $this->original_participation_id,
            'substitute_participation_id' => $this->substitute_participation_id,
            'substitute_player_id' => $this->substitute_player_id,
            'match_id' => $this->match_id,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'edition_division' => $this->whenLoaded('editionDivision', function () {
                return [
                    'id_edition_division' => $this->editionDivision?->id_edition_division,
                    'division_title' => $this->editionDivision?->division_title,
                    'edition_id' => $this->editionDivision?->edition_id,
                    'division_id' => $this->editionDivision?->division_id,
                ];
            }),
            'original_participation' => $this->whenLoaded('originalParticipation', function () {
                return $this->slimParticipation($this->originalParticipation);
            }),
            'substitute_participation' => $this->whenLoaded('substituteParticipation', function () {
                return $this->slimParticipation($this->substituteParticipation);
            }),
            'substitute_player' => $this->whenLoaded('substitutePlayer', function () {
                return $this->slimPlayer($this->substitutePlayer);
            }),
        ];
    }

    /**
     * @return null|array<string, mixed>
     */
    private function slimParticipation(mixed $participation): ?array
    {
        if ($participation === null) {
            return null;
        }

        return [
            'id_participation' => $participation->id_participation,
            'player' => $this->slimPlayer($participation->relationLoaded('player') ? $participation->player : null),
        ];
    }

    /**
     * @return null|array<string, mixed>
     */
    private function slimPlayer(mixed $player): ?array
    {
        if ($player === null) {
            return null;
        }

        return [
            'id_player' => $player->id_player,
            'name' => $player->name,
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
        ];
    }
}
