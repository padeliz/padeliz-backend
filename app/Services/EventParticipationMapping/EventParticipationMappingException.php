<?php

declare(strict_types=1);

namespace App\Services\EventParticipationMapping;

use RuntimeException;

final class EventParticipationMappingException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $status = 409,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
