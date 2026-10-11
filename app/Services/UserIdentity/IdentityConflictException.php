<?php

declare(strict_types=1);

namespace App\Services\UserIdentity;

use RuntimeException;

final class IdentityConflictException extends RuntimeException
{
    public function __construct(string $message = 'This identity is already linked to another player.')
    {
        parent::__construct($message);
    }
}
