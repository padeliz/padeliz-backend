<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\EventParticipation;
use App\Services\EventCalendarTiming;
use Carbon\Carbon;

final class AttendancePdfLayout
{
    public const TITLE_TOP_PAD_MM = 2;

    public const PARTICIPANT_CONTENT_MM = 6;

    public const PARTICIPANT_GAP_MM = 3;

    public static function columnWidthPercent(int $columnCount): int
    {
        return (int) floor(100 / max(1, $columnCount));
    }

    public static function columnCountForEdCount(int $edCount): int
    {
        if ($edCount <= 0) {
            return 1;
        }

        return match ($edCount) {
            1, 2, 3 => 1,
            4 => 2,
            5, 6, 9 => 3,
            7, 8 => 4,
            default => 3,
        };
    }

    /**
     * @template T
     *
     * @param  list<T>  $cells
     * @return list<list<T>>
     */
    public static function rowBatches(array $cells, int $columnCount): array
    {
        if ($cells === []) {
            return [];
        }

        $columns = max(1, $columnCount);

        return array_map(
            static fn (array $chunk): array => array_values($chunk),
            array_chunk($cells, $columns)
        );
    }

    /**
     * @template T
     *
     * @param  list<T>  $eventDivisions
     * @return list<list<T>>
     */
    public static function pageBatches(array $eventDivisions, int $maxPerPage = 9): array
    {
        if ($eventDivisions === []) {
            return [[]];
        }

        return array_map(
            static fn (array $chunk): array => array_values($chunk),
            array_chunk($eventDivisions, max(1, $maxPerPage))
        );
    }

    /**
     * @param  iterable<EventParticipation>  $participations
     * @return list<EventParticipation>
     */
    public static function sortParticipationsByPlayerName(iterable $participations): array
    {
        $rows = is_array($participations) ? $participations : iterator_to_array($participations);
        usort(
            $rows,
            static function (EventParticipation $left, EventParticipation $right): int {
                $leftName = mb_strtolower((string) ($left->player?->name ?? ''));
                $rightName = mb_strtolower((string) ($right->player?->name ?? ''));

                return $leftName <=> $rightName;
            }
        );

        return $rows;
    }

    /**
     * Attendance PDF header date: ordinal single day, or compact multi-day range.
     */
    public static function formatAttendanceHeaderDate(int $eventStartsAt, int $eventEndsAt, int $eventNrOfDays): string
    {
        if ($eventNrOfDays <= 1) {
            return self::formatOrdinalHeaderDate(EventCalendarTiming::dayYmd($eventStartsAt));
        }

        return self::formatCompactHeaderDateRange($eventStartsAt, $eventEndsAt);
    }

    /**
     * Compact multi-day range (short months Jan..Dec, Europe/Bucharest).
     */
    public static function formatCompactHeaderDateRange(int $eventStartsAt, int $eventEndsAt): string
    {
        $tz = EventCalendarTiming::timezone();
        $start = Carbon::createFromTimestamp($eventStartsAt, $tz);
        $end = Carbon::createFromTimestamp($eventEndsAt, $tz);

        $startDay = (int) $start->format('j');
        $endDay = (int) $end->format('j');
        $startMon = $start->format('M');
        $endMon = $end->format('M');
        $startYear = (int) $start->format('Y');
        $endYear = (int) $end->format('Y');

        if ($startYear === $endYear && $startMon === $endMon) {
            return sprintf('%d-%d %s %d', $startDay, $endDay, $startMon, $startYear);
        }

        if ($startYear === $endYear) {
            return sprintf('%d %s - %d %s', $startDay, $startMon, $endDay, $endMon);
        }

        return sprintf('%d %s %d - %d %s %d', $startDay, $startMon, $startYear, $endDay, $endMon, $endYear);
    }

    public static function formatAttendanceHeaderTimes(int $eventStartsAt, int $eventEndsAt): string
    {
        return EventCalendarTiming::formatHi($eventStartsAt) . ' – ' . EventCalendarTiming::formatHi($eventEndsAt);
    }

    public static function formatOrdinalHeaderDate(string $date): string
    {
        try {
            $dt = Carbon::createFromFormat('Y-m-d', $date, EventCalendarTiming::timezone());
        } catch (\Throwable) {
            return $date;
        }

        if ($dt === false) {
            return $date;
        }

        $year = (int) $dt->format('Y');
        $month = $dt->format('F');
        $day = (int) $dt->format('j');
        $suffix = self::ordinalSuffix($day);

        return sprintf('%d %s %d%s', $year, $month, $day, $suffix);
    }

    private static function ordinalSuffix(int $day): string
    {
        if ($day >= 11 && $day <= 13) {
            return 'th';
        }

        return match ($day % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
