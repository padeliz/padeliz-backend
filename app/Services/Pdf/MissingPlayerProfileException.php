<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use RuntimeException;

final class MissingPlayerProfileException extends RuntimeException
{
    /**
     * @param  list<int>  $playerIds
     */
    public function __construct(
        public readonly array $playerIds,
        string $message = 'One or more players are missing a profile image.',
    ) {
        parent::__construct($message);
    }
}
