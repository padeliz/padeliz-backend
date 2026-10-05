<?php

declare(strict_types=1);

namespace App\Services\RegistrationPayment;

use RuntimeException;

class RegistrationValidationException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        public readonly array $errors
    ) {
        parent::__construct('Registration validation failed');
    }
}
