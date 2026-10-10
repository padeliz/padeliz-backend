<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Shared clock-window helpers for playing / arriving / closing times.
 *
 * Wall clocks are 00:00–23:59(:ss). Overnight windows use end &lt; start
 * (end falls on the next calendar day). Start and end must differ.
 */
final class PlayingWindow
{
    /**
     * Shape-only validation: 00:00–23:59 with optional seconds.
     */
    public const CLOCK_PATTERN = '^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$';

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

        if (preg_match('/^24:00(?::00)?$/', $raw) === 1) {
            throw new InvalidArgumentException('Invalid clock "24:00:00".');
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m) === 1) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            $s = isset($m[3]) ? (int) $m[3] : 0;

            if ($h > 23 || $i > 59 || $s > 59) {
                throw new InvalidArgumentException(sprintf('Invalid clock "%s".', $raw));
            }

            return sprintf('%02d:%02d:%02d', $h, $i, $s);
        }

        throw new InvalidArgumentException(sprintf('Invalid clock "%s".', $raw));
    }

    /**
     * Whether normalized start and end clocks differ (same-day or overnight).
     */
    public static function windowsAreDistinct(string $start, string $end): bool
    {
        return self::compareClock($start, $end) !== 0;
    }

    /**
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}
     */
    public static function resolveInterval(string $start, string $end, ?string $date = null): array
    {
        $date = $date ?? '1970-01-01';
        $startClock = self::normalizeStoredTime(self::requireClock($start));
        $endClock = self::normalizeStoredTime(self::requireClock($end));

        if ($startClock === $endClock) {
            throw new InvalidArgumentException('Start and end clocks must differ.');
        }

        $startDt = self::dateTimeOnDate($date, $startClock);

        if (self::compareClock($endClock, $startClock) < 0) {
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

    private static function requireClock(string $time): string
    {
        $time = trim($time);
        if ($time === '' || preg_match('/' . self::CLOCK_PATTERN . '/', $time) !== 1) {
            // Allow H:i without leading zero on hour for internal callers.
            if (preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid clock "%s".', $time));
            }
        }

        return $time;
    }

    private static function dateTimeOnDate(string $date, string $clockHms): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $clockHms);
        if (! $parsed instanceof DateTimeImmutable) {
            throw new InvalidArgumentException(sprintf('Invalid date/time "%s %s".', $date, $clockHms));
        }

        return $parsed;
    }
}
