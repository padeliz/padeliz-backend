<?php

declare(strict_types=1);

namespace App\Support;

final class PlayerDisplayName
{
    public static function normalizeStem(string $value): string
    {
        $trimmed = trim($value);

        return trim(preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed);
    }

    public static function fromParts(string $first, ?string $middle, ?string $last): string
    {
        $parts = collect([$first, $middle, $last])
            ->map(static fn ($part) => is_string($part) ? trim($part) : '')
            ->filter(static fn (string $part): bool => $part !== '')
            ->all();

        return $parts === [] ? '' : implode(' ', $parts);
    }
}
