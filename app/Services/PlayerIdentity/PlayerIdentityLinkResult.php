<?php

declare(strict_types=1);

namespace App\Services\PlayerIdentity;

use App\Models\PlayerIdentity;

final class PlayerIdentityLinkResult
{
    public const LINKED = 'linked';

    public const ALREADY_LINKED = 'already_linked';

    public function __construct(
        public readonly string $status,
        public readonly PlayerIdentity $identity,
    ) {
    }

    public function wasAlreadyLinked(): bool
    {
        return $this->status === self::ALREADY_LINKED;
    }
}
