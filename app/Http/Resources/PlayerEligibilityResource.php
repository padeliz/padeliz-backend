<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerEligibilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_eligibility,
            'id_eligibility' => $this->id_eligibility,
            'player_id' => $this->player_id,
            'edition_id' => $this->edition_id,
            'review_status' => $this->review_status,
            'notes' => $this->review_message,
            'name' => $this->name,
            'created_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'player' => $this->whenLoaded('player', function () {
                return new PlayerResource($this->player);
            }),
        ];
    }
}
