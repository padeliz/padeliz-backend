<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full Manager shape for a linked sign-in identity (App clients keep using UserIdentityResource).
 *
 * @OA\Schema(
 *     schema="UserIdentityManagerResource",
 *     @OA\Property(property="id_user_identity", type="integer", format="int64"),
 *     @OA\Property(property="user_id", type="integer", format="int64"),
 *     @OA\Property(property="provider", type="string"),
 *     @OA\Property(property="provider_subject", type="string"),
 *     @OA\Property(property="email_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="phone_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="display_name", type="string", nullable=true),
 *     @OA\Property(property="avatar_url", type="string", nullable=true),
 *     @OA\Property(property="is_avatar_primary", type="boolean"),
 *     @OA\Property(property="linked_at", type="integer", format="timestamp"),
 *     @OA\Property(property="last_login_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true)
 * )
 */
class UserIdentityManagerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_user_identity' => $this->id_user_identity,
            'user_id' => $this->user_id,
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
