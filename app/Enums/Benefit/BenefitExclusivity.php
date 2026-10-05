<?php

declare(strict_types=1);

namespace App\Enums\Benefit;

enum BenefitExclusivity: string
{
    case Stackable = 'stackable';
    case ExclusiveInGroup = 'exclusive_in_group';
    case ExclusiveGlobal = 'exclusive_global';

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
