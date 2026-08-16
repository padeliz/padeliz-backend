<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchSideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_match_side' => $this->id_match_side,
            'match_id' => $this->match_id,
            'side' => $this->side,
            'score' => $this->score,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'couple_image_url' => $this->when(
                filter_var(request()->query('scores'), FILTER_VALIDATE_BOOLEAN),
                $this->couple_image_url ?? null,
            ),
            'players' => $this->whenLoaded('players', function () {
                return MatchPlayerResource::collection($this->players);
            }),
        ];
    }
}
