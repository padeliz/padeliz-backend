<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerPhoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player_phone' => $this->id_player_phone,
            'phone' => $this->phone,
            'purposes' => $this->purposes,
            'is_primary' => $this->is_primary,
            'verified_at' => $this->verified_at,
        ];
    }
}
