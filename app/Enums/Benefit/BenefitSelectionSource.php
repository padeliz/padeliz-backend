<?php

declare(strict_types=1);

namespace App\Enums\Benefit;

enum BenefitSelectionSource: string
{
    case Auto = 'auto';
    case Player = 'player';
    case Manager = 'manager';

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
