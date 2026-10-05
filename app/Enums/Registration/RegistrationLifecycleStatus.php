<?php

declare(strict_types=1);

namespace App\Enums\Registration;

enum RegistrationLifecycleStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Declined = 'declined';

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
