<?php

declare(strict_types=1);

namespace App\Services\Schedule;

/**
 * Distinct-opponent counts derived from match-making playersMet maps.
 */
final class OpponentsMetSummary
{
    /**
     * @param  null|array<int, array<int, int>>  $playersMet
     * @return array{min: null|int, max: null|int}
     */
    public static function fromPlayersMet(?array $playersMet, int $playersCount): array
    {
        if ($playersMet === null || $playersMet === []) {
            return ['min' => null, 'max' => null];
        }

        $perPlayer = [];
        for ($p = 0; $p < $playersCount; $p++) {
            $perPlayer[] = isset($playersMet[$p]) ? count($playersMet[$p]) : 0;
        }

        if ($perPlayer === []) {
            return ['min' => null, 'max' => null];
        }

        return [
            'min' => min($perPlayer),
            'max' => max($perPlayer),
        ];
    }

    /**
     * Simultaneous time slots (rounds on the first court). Prefer
     * {@see TemplateMatchDerivation::roundsCount()} for new call sites.
     *
     * @param  null|array<int, array<int, mixed>>  $matchesByCourt
     */
    public static function roundsCount(?array $matchesByCourt): ?int
    {
        return TemplateMatchDerivation::roundsCount($matchesByCourt);
    }
}
