<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full Manager shape for a linked sign-in identity (App clients keep using PlayerIdentityResource).
 */
class PlayerIdentityManagerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_identity' => $this->id_identity,
            'player_id' => $this->player_id,
            'provider' => $this->provider,
            'provider_subject' => $this->provider_subject,
            'email_id' => $this->email_id,
            'phone_id' => $this->phone_id,
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_url,
            'is_avatar_primary' => (bool) $this->is_avatar_primary,
            'linked_at' => $this->linked_at,
            'last_login_at' => $this->last_login_at,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
