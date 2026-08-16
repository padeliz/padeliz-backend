<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use InvalidArgumentException;

/**
 * MiniTour points-per-match formula from playing window and template round count.
 *
 * hours = whole hours of resolved playing window (overnight / 24:00 aware)
 * points = floor(ceil(110 * hours - 1 + 20/60) / rounds)
 */
final class PointsPerMatchCalculator
{
    public static function calculate(string $playingStart, string $playingEnd, int $rounds): int
    {
        if ($rounds <= 0) {
            return 0;
        }

        try {
            $resolved = PlayingWindow::resolveInterval($playingStart, $playingEnd);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(
                sprintf('Invalid playing time; expected H:i or H:i:s (%s).', $e->getMessage()),
                0,
                $e
            );
        }

        $hours = (int) floor(
            ($resolved['end']->getTimestamp() - $resolved['start']->getTimestamp()) / 3600
        );

        return (int) floor(ceil(110 * $hours - 1 + (20 / 60)) / $rounds);
    }
}
