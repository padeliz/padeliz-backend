<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="UserIdentityResource",
 *     @OA\Property(property="id_user_identity", type="integer", format="int64"),
 *     @OA\Property(property="provider", type="string"),
 *     @OA\Property(property="email", type="string", nullable=true),
 *     @OA\Property(property="display_name", type="string", nullable=true),
 *     @OA\Property(property="avatar_url", type="string", nullable=true),
 *     @OA\Property(property="is_avatar_primary", type="boolean"),
 *     @OA\Property(property="linked_at", type="integer", format="timestamp"),
 *     @OA\Property(property="last_login_at", type="integer", format="timestamp", nullable=true)
 * )
 */
class UserIdentityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_user_identity' => $this->id_user_identity,
            'provider' => $this->provider,
            'email' => $this->when(
                $this->relationLoaded('email'),
                fn () => $this->email?->email
            ),
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_url,
            'is_avatar_primary' => (bool) $this->is_avatar_primary,
            'linked_at' => $this->linked_at,
            'last_login_at' => $this->last_login_at,
        ];
    }
}
