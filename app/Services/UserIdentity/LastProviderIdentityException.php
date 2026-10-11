<?php

declare(strict_types=1);

namespace App\Services\UserIdentity;

use RuntimeException;

final class LastProviderIdentityException extends RuntimeException
{
    public function __construct(string $provider)
    {
        parent::__construct("Cannot unlink the last {$provider} identity.");
    }
}
