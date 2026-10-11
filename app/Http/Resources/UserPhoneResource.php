<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="UserPhoneResource",
 *     @OA\Property(property="id_user_phone", type="integer", format="int64"),
 *     @OA\Property(property="user_id", type="integer", format="int64"),
 *     @OA\Property(property="phone", type="string"),
 *     @OA\Property(property="purposes", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="is_primary", type="boolean"),
 *     @OA\Property(property="verified_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true)
 * )
 */
class UserPhoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_user_phone' => $this->id_user_phone,
            'user_id' => $this->user_id,
            'phone' => $this->phone,
            'purposes' => $this->purposes,
            'is_primary' => $this->is_primary,
            'verified_at' => $this->verified_at,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
