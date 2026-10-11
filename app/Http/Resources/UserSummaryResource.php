<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\PlayerProfileImage\PlayerProfileImageResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact user embed for nested sport / domain resources.
 *
 * @OA\Schema(
 *     schema="UserSummaryResource",
 *     @OA\Property(property="id_user", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="first_name", type="string"),
 *     @OA\Property(property="middle_name", type="string", nullable=true),
 *     @OA\Property(property="last_name", type="string", nullable=true),
 *     @OA\Property(property="pdf_avatar_url", type="string", nullable=true),
 *     @OA\Property(property="profile_image_url", type="string", nullable=true)
 * )
 */
class UserSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_user' => $this->id_user,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'pdf_avatar_url' => $this->pdfAvatarUrl(),
            'profile_image_url' => $this->profileImageUrl(),
            'phones' => $this->whenLoaded('phones', function () {
                return UserPhoneResource::collection($this->phones);
            }),
        ];
    }

    private function pdfAvatarUrl(): ?string
    {
        if (! $this->relationLoaded('cachedPdfImages')) {
            return null;
        }

        $preferred = $this->cachedPdfImages->firstWhere('player_is_collecting_points', true)
            ?? $this->cachedPdfImages->first();

        return $preferred?->url;
    }

    private function profileImageUrl(): ?string
    {
        $resolved = app(PlayerProfileImageResolver::class)->resolveForPdf($this->resource);

        return $resolved?->url;
    }
}
