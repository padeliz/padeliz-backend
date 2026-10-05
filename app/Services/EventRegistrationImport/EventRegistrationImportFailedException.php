<?php

declare(strict_types=1);

namespace App\Services\EventRegistrationImport;

use RuntimeException;

final class EventRegistrationImportFailedException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
