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
        ];
    }
}
