<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerFriendsSourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player_friends_source' => $this->id_player_friends_source,
            'player_id' => $this->player_id,
            'friend_player_id' => $this->friend_player_id,
            'friend_name' => $this->whenLoaded('friend', fn () => $this->friend?->name),
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
