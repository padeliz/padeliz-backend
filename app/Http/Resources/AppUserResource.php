<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\UserIdentity\UserAvatarResolver;
use Illuminate\Http\Request;

/**
 * The player as the App sees themselves. The avatar is the primary (else latest-linked) Google
 * avatar only; the PDF avatar and uploaded profile images never stand in for it.
 */
class AppUserResource extends UserResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = parent::toArray($request);

        unset($payload['pdf_avatar_url'], $payload['profile_image_url']);

        $payload['avatar_url'] = app(UserAvatarResolver::class)->resolveForApp($this->resource);

        return $payload;
    }
}
