<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\PlayerLevel\PlayerLevelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerLevelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $band = $this->relationLoaded('band')
            ? $this->getRelation('band')
            : app(PlayerLevelService::class)->bandFor((string) $this->level);

        return [
            'id_player_level' => $this->id_player_level,
            'player_user_id' => $this->player_user_id,
            'level' => $this->level,
            'band_name' => $band?->name,
            'player_level_assessment_id' => $this->player_level_assessment_id,
            'event_participation_id' => $this->event_participation_id,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'assessment' => $this->whenLoaded('assessment', function () {
                return $this->assessment !== null
                    ? new PlayerLevelAssessmentResource($this->assessment)
                    : null;
            }),
            'participation' => $this->whenLoaded('participation', function () {
                if ($this->participation === null) {
                    return null;
                }

                $event = $this->participation->relationLoaded('event')
                    ? $this->participation->event
                    : null;

                return [
                    'id_participation' => $this->participation->id_participation,
                    'event_id' => $this->participation->event_id,
                    'player_user_id' => $this->participation->player_user_id,
                    'inserted_at' => $this->participation->inserted_at,
                    'event' => $event !== null
                        ? [
                            'id_event' => $event->id_event,
                            'name' => $event->name,
                            'event_starts_at' => $event->event_starts_at,
                            'event_ends_at' => $event->event_ends_at,
                        ]
                        : null,
                ];
            }),
        ];
    }
}
