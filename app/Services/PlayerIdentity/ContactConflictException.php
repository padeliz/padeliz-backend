<?php

declare(strict_types=1);

namespace App\Services\PlayerIdentity;

use RuntimeException;

final class ContactConflictException extends RuntimeException
{
    public function __construct(string $message = 'Email is already used by another player.')
    {
        parent::__construct($message);
    }
}
