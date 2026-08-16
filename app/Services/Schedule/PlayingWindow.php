<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Shared clock-window helpers for playing / arriving / closing times.
 *
 * Supports end-of-day 24:00(:00) and overnight windows (end &lt; start).
 */
final class PlayingWindow
{
    /**
     * Shape-only validation: 00:00–23:59(:ss) or exactly 24:00 / 24:00:00.
     */
    public const CLOCK_PATTERN = '^(?:(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?|24:00(?::00)?)$';

    public static function clockRegexRule(): string
    {
        return 'regex:/' . self::CLOCK_PATTERN . '/';
    }

    public static function normalizeStoredTime(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time . ':00';
        }

        return $time;
    }

    public static function toClockString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        // Preserve end-of-day sentinel that Carbon/DateTime cannot represent.
        if (preg_match('/^24:00(?::00)?$/', $raw) === 1) {
            return '24:00:00';
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $raw, $m) === 1) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            $s = isset($m[3]) ? (int) $m[3] : 0;

            return sprintf('%02d:%02d:%02d', $h, $i, $s);
        }

        return $raw;
    }

    /**
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}
     */
    public static function resolveInterval(string $start, string $end, ?string $date = null): array
    {
        $date = $date ?? '1970-01-01';
        $startClock = self::normalizeStoredTime(self::requireClock($start));
        $endClock = self::normalizeStoredTime(self::requireClock($end));

        $startDt = self::dateTimeOnDate($date, $startClock);

        if (self::isEndOfDay($endClock)) {
            $endDt = self::dateTimeOnDate($date, '00:00:00')->modify('+1 day');
        } elseif (self::compareClock($endClock, $startClock) < 0) {
            $endDt = self::dateTimeOnDate($date, $endClock)->modify('+1 day');
        } else {
            $endDt = self::dateTimeOnDate($date, $endClock);
        }

        return ['start' => $startDt, 'end' => $endDt];
    }

    public static function compareClock(string $a, string $b): int
    {
        $na = self::normalizeStoredTime(self::requireClock($a));
        $nb = self::normalizeStoredTime(self::requireClock($b));

        return $na <=> $nb;
    }

    /**
     * Whether clock $now falls in [start, end) after resolve.
     * Also checks $now on the day after $date so overnight morning hours match.
     */
    public static function isActiveAt(string $start, string $end, string $now, ?string $date = null): bool
    {
        $date = $date ?? '1970-01-01';
        $nowClock = self::normalizeStoredTime(self::requireClock($now));
        $interval = self::resolveInterval($start, $end, $date);

        $candidates = [
            self::dateTimeOnDate($date, $nowClock),
            self::dateTimeOnDate($date, $nowClock)->modify('+1 day'),
        ];

        foreach ($candidates as $nowDt) {
            if ($nowDt >= $interval['start'] && $nowDt < $interval['end']) {
                return true;
            }
        }

        return false;
    }

    public static function isEndOfDay(string $clock): bool
    {
        $n = self::normalizeStoredTime(trim($clock));

        return $n === '24:00:00';
    }

    private static function requireClock(string $time): string
    {
        $time = trim($time);
        if ($time === '' || preg_match('/' . self::CLOCK_PATTERN . '/', $time) !== 1) {
            // Allow slightly looser parse for internal callers that already validated,
            // but still reject obvious garbage.
            if (preg_match('/^\d{1,2}:\d{2}(?::\d{2})?$/', $time) !== 1 && preg_match('/^24:00(?::00)?$/', $time) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid clock "%s".', $time));
            }
        }

        return $time;
    }

    private static function dateTimeOnDate(string $date, string $clockHms): DateTimeImmutable
    {
        if (self::isEndOfDay($clockHms)) {
            throw new InvalidArgumentException('24:00:00 cannot be placed on a calendar day as a wall clock.');
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $clockHms);
        if (! $parsed instanceof DateTimeImmutable) {
            throw new InvalidArgumentException(sprintf('Invalid date/time "%s %s".', $date, $clockHms));
        }

        return $parsed;
    }
}
