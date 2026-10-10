<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerEmailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player_email' => $this->id_player_email,
            'player_id' => $this->player_id,
            'email' => $this->email,
            'purposes' => $this->purposes,
            'is_primary' => $this->is_primary,
            'verified_at' => $this->verified_at,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
