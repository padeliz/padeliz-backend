<?php

declare(strict_types=1);

namespace App\Services\Challenge;

use RuntimeException;

final class ChallengeStateException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 409,
    ) {
        parent::__construct($message);
    }
}
