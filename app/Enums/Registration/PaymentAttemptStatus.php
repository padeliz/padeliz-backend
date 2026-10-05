<?php

declare(strict_types=1);

namespace App\Enums\Registration;

enum PaymentAttemptStatus: string
{
    case Started = 'started';
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Superseded = 'superseded';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases()
        );
    }
}
