<?php

declare(strict_types=1);

namespace App\Services\Standings;

/**
 * Competition-style standing ranks from wins + points_total.
 *
 * Sort: wins DESC, then points_total DESC. Ties share rank; next place skips (1, 1, 3).
 * NULL wins/points_total are treated as 0 for sorting only.
 */
final class StandingsRanker
{
    /**
     * @param  array<int|string, array{wins?: null|float|int|string, points_total?: null|float|int|string}>  $rows
     * @return array<int|string, int>
     */
    public static function assignCompetitionRanks(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $key => $row) {
            $normalized[$key] = [
                'wins' => self::coerceNumber($row['wins'] ?? null),
                'points_total' => self::coerceNumber($row['points_total'] ?? null),
            ];
        }

        uasort($normalized, static function (array $a, array $b): int {
            if ($a['wins'] !== $b['wins']) {
                return $b['wins'] <=> $a['wins'];
            }

            return $b['points_total'] <=> $a['points_total'];
        });

        $rank = 0;
        $position = 0;
        $previous = null;
        $ranks = [];

        foreach ($normalized as $key => $row) {
            $position++;
            $tieKey = $row['wins'] . ':' . $row['points_total'];
            if ($previous === null || $tieKey !== $previous) {
                $rank = $position;
                $previous = $tieKey;
            }
            $ranks[$key] = $rank;
        }

        return $ranks;
    }

    private static function coerceNumber(float|int|string|null $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }
}
