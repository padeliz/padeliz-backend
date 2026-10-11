<?php

declare(strict_types=1);

namespace App\Services\UserIdentity;

use App\Models\UserIdentity;

final class UserIdentityLinkResult
{
    public const LINKED = 'linked';

    public const ALREADY_LINKED = 'already_linked';

    public function __construct(
        public readonly string $status,
        public readonly UserIdentity $identity,
    ) {
    }

    public function wasAlreadyLinked(): bool
    {
        return $this->status === self::ALREADY_LINKED;
    }
}
