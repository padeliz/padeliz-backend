<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Player;

final class PlayerFullNameMatchIndex
{
    /**
     * @param  list<string>  $lookupStrings
     * @return array<string, list<int>>
     */
    public static function build(array $lookupStrings): array
    {
        $lookups = [];
        foreach ($lookupStrings as $raw) {
            $normalized = PlayerDisplayName::normalizeStem($raw);
            if ($normalized !== '') {
                $lookups[$normalized] = true;
            }
        }

        if ($lookups === []) {
            return [];
        }

        $expression = PlayerFullNameSql::expression();
        $index = [];

        foreach (array_keys($lookups) as $lookup) {
            $ids = Player::query()
                ->whereRaw("{$expression} = ?", [$lookup])
                ->pluck('id_player')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            if ($ids !== []) {
                $index[$lookup] = $ids;
            }
        }

        return $index;
    }
}
