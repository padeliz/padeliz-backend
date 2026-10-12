<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bucharest calendar-day Past / Ongoing / Upcoming for events with unix bounds.
 */
final class EventCalendarTiming
{
    public const PAST = 'past';

    public const ONGOING = 'ongoing';

    public const UPCOMING = 'upcoming';

    public static function timezone(): string
    {
        return (string) config('app.timezone', 'Europe/Bucharest');
    }

    public static function todayYmd(?int $now = null, ?string $timezone = null): string
    {
        return Carbon::createFromTimestamp($now ?? time(), $timezone ?? self::timezone())->toDateString();
    }

    public static function dayYmd(int $unix, ?string $timezone = null): string
    {
        return Carbon::createFromTimestamp($unix, $timezone ?? self::timezone())->toDateString();
    }

    /** Bucharest wall clock H:i for schedule engines (Mode A / reorder). */
    public static function formatHi(int $unix, ?string $timezone = null): string
    {
        return Carbon::createFromTimestamp($unix, $timezone ?? self::timezone())->format('H:i');
    }

    public static function startOfDayUnix(string $dateYmd, ?string $timezone = null): int
    {
        return Carbon::createFromFormat('Y-m-d', $dateYmd, $timezone ?? self::timezone())
            ->startOfDay()
            ->timestamp;
    }

    /**
     * Exclusive end bound: start of the calendar day after $dateYmd.
     */
    public static function endExclusiveUnix(string $dateYmd, ?string $timezone = null): int
    {
        return Carbon::createFromFormat('Y-m-d', $dateYmd, $timezone ?? self::timezone())
            ->addDay()
            ->startOfDay()
            ->timestamp;
    }

    public static function classify(int $eventStartsAt, int $eventEndsAt, ?int $now = null, ?string $timezone = null): string
    {
        $today = self::todayYmd($now, $timezone);
        $startsDay = self::dayYmd($eventStartsAt, $timezone);
        $endsDay = self::dayYmd($eventEndsAt, $timezone);

        if ($today > $endsDay) {
            return self::PAST;
        }
        if ($today < $startsDay) {
            return self::UPCOMING;
        }

        return self::ONGOING;
    }

    public static function eventNrOfDays(int $eventStartsAt, int $eventEndsAt, ?string $timezone = null): int
    {
        $tz = $timezone ?? self::timezone();
        $start = Carbon::createFromTimestamp($eventStartsAt, $tz)->startOfDay();
        $end = Carbon::createFromTimestamp($eventEndsAt, $tz)->startOfDay();

        return (int) $start->diffInDays($end) + 1;
    }

    /**
     * Inclusive Bucharest calendar-day overlap with [dateFrom, dateTo].
     */
    public static function overlapsDateRange(
        int $eventStartsAt,
        int $eventEndsAt,
        string $dateFrom,
        string $dateTo,
        ?string $timezone = null,
    ): bool {
        $startsDay = self::dayYmd($eventStartsAt, $timezone);
        $endsDay = self::dayYmd($eventEndsAt, $timezone);

        return $startsDay <= $dateTo && $endsDay >= $dateFrom;
    }

    /**
     * SQL: starts_day <= date_to AND ends_day >= date_from
     * (via unix bounds in Europe/Bucharest).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyOverlapsDateRange(Builder $query, string $dateFrom, string $dateTo): void
    {
        $query->where('event_starts_at', '<', self::endExclusiveUnix($dateTo))
            ->where('event_ends_at', '>=', self::startOfDayUnix($dateFrom));
    }

    /**
     * SQL: calendar Past — today > ends_day.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyCalendarPast(Builder $query, ?int $now = null): void
    {
        $query->where('event_ends_at', '<', self::startOfDayUnix(self::todayYmd($now)));
    }

    /**
     * SQL: ends_day < cutoffDateYmd (history before a reference calendar day).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyEndsBeforeDay(Builder $query, string $cutoffDateYmd): void
    {
        $query->where('event_ends_at', '<', self::startOfDayUnix($cutoffDateYmd));
    }
}
