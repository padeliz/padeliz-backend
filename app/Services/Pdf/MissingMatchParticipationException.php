<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use RuntimeException;

final class MissingMatchParticipationException extends RuntimeException
{
    public function __construct(string $message = 'Match slot is missing a participation.')
    {
        parent::__construct($message);
    }
}
