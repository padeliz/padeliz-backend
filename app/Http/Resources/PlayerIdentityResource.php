<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerIdentityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_identity' => $this->id_identity,
            'provider' => $this->provider,
            'email' => $this->when(
                $this->relationLoaded('email'),
                fn () => $this->email?->email
            ),
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_url,
            'linked_at' => $this->linked_at,
            'last_login_at' => $this->last_login_at,
        ];
    }
}
