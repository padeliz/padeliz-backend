<?php

declare(strict_types=1);

namespace App\Enums;

enum PlayerCustomFieldType: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Date = 'date';
    case SingleSelect = 'single_select';
    case MultiSelect = 'multi_select';

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

    public function isSelect(): bool
    {
        return $this === self::SingleSelect || $this === self::MultiSelect;
    }
}
