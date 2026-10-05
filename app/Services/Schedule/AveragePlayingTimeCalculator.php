<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use InvalidArgumentException;

/**
 * Average minutes per regular match slot from the playing window and round count.
 *
 * Does not account for demonstrative (−15m) or final (+1 segment) adjustments.
 */
final class AveragePlayingTimeCalculator
{
    public static function calculate(string $playingStart, string $playingEnd, int $roundsTotal): float
    {
        if ($roundsTotal <= 0) {
            return 0.0;
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

        $minutes = ($resolved['end']->getTimestamp() - $resolved['start']->getTimestamp()) / 60;

        return round($minutes / $roundsTotal, 1);
    }
}
