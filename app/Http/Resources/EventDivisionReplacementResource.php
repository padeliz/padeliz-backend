<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CachedPdfPlayerImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventDivisionReplacementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_replacement' => $this->id_replacement,
            'event_division_id' => $this->event_division_id,
            'original_participation_id' => $this->original_participation_id,
            'substitute_participation_id' => $this->substitute_participation_id,
            'substitute_player_id' => $this->substitute_player_id,
            'match_id' => $this->match_id,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'event_division' => $this->whenLoaded('eventDivision', function () {
                return [
                    'id_event_division' => $this->eventDivision?->id_event_division,
                    'division_title' => $this->eventDivision?->division_title,
                    'event_id' => $this->eventDivision?->event_id,
                    'division_id' => $this->eventDivision?->division_id,
                ];
            }),
            'original_participation' => $this->whenLoaded('originalParticipation', function () {
                return $this->slimParticipation($this->originalParticipation);
            }),
            'substitute_participation' => $this->whenLoaded('substituteParticipation', function () {
                return $this->slimParticipation($this->substituteParticipation);
            }),
            'substitute_player' => $this->whenLoaded('substitutePlayer', function () {
                return $this->slimPlayer($this->substitutePlayer, true);
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

        $collecting = (bool) $participation->is_collecting_points;

        return [
            'id_participation' => $participation->id_participation,
            'is_collecting_points' => $collecting,
            'player' => $this->slimPlayer(
                $participation->relationLoaded('player') ? $participation->player : null,
                $collecting
            ),
        ];
    }

    /**
     * @return null|array<string, mixed>
     */
    private function slimPlayer(mixed $player, bool $collecting): ?array
    {
        if ($player === null) {
            return null;
        }

        return [
            'id_player' => $player->id_player,
            'name' => $player->name,
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'pdf_avatar_url' => CachedPdfPlayerImage::urlForPlayer((int) $player->id_player, $collecting),
        ];
    }
}
