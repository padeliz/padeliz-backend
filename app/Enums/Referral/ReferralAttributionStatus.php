<?php

declare(strict_types=1);

namespace App\Enums\Referral;

enum ReferralAttributionStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Released = 'released';

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
