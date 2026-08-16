<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use DateInterval;

/**
 * Shared round-slot clocks for schedule generate and match reorder.
 *
 * Mirrors the former MatchesGenerator segment loop: PlayingWindow interval,
 * optional demonstrative −15m, optional final +1 segment, floor minute boundaries.
 */
final class RoundClockCalculator
{
    /**
     * @return list<array{start: string, end: string}> Index 0 = sequence_no 1 (H:i)
     */
    public static function slots(
        string $playingStart,
        string $playingEnd,
        int $roundsTotal,
        bool $includeFinal,
        bool $hasDemonstrativeMatch,
    ): array {
        if ($roundsTotal <= 0) {
            return [];
        }

        $segments = $roundsTotal;
        if ($includeFinal) {
            $segments++;
        }

        $resolved = PlayingWindow::resolveInterval($playingStart, $playingEnd);
        $dateTime1 = $resolved['start'];
        $dateTime2 = $resolved['end'];

        if ($hasDemonstrativeMatch) {
            $dateTime2 = $dateTime2->modify('-15 minutes');
        }

        $totalMinutes = ($dateTime2->getTimestamp() - $dateTime1->getTimestamp()) / 60;
        $segmentDuration = $segments > 0 ? $totalMinutes / $segments : 0;

        $slots = [];
        for ($r = 0; $r < $roundsTotal; $r++) {
            $startTime = $dateTime1->add(new DateInterval('PT' . (int) floor($segmentDuration * $r) . 'M'));
            $endTime = $dateTime1->add(new DateInterval('PT' . (int) floor($segmentDuration * ($r + 1)) . 'M'));
            $slots[] = [
                'start' => $startTime->format('H:i'),
                'end' => $endTime->format('H:i'),
            ];
        }

        return $slots;
    }
}
