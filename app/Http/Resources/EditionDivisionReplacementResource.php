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
            'match_scope_key' => $this->match_scope_key,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
