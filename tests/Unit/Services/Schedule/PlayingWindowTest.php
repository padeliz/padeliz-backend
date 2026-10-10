<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\PlayingWindow;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PlayingWindowTest extends TestCase
{
    public function test_same_day_resolve(): void
    {
        $resolved = PlayingWindow::resolveInterval('10:00', '18:00', '2026-08-14');

        $this->assertSame('2026-08-14 10:00:00', $resolved['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-14 18:00:00', $resolved['end']->format('Y-m-d H:i:s'));
        $this->assertSame('10:00:00', $resolved['start']->format('H:i:s'));
        $this->assertSame('18:00:00', $resolved['end']->format('H:i:s'));
    }

    public function test_overnight_resolve(): void
    {
        $resolved = PlayingWindow::resolveInterval('18:00', '00:00', '2026-08-14');

        $this->assertSame('2026-08-14 18:00:00', $resolved['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-15 00:00:00', $resolved['end']->format('Y-m-d H:i:s'));
        $hours = ($resolved['end']->getTimestamp() - $resolved['start']->getTimestamp()) / 3600;
        $this->assertEqualsWithDelta(6.0, $hours, 0.01);
    }

    public function test_full_day_resolve_is_86399_seconds(): void
    {
        $resolved = PlayingWindow::resolveInterval('00:00', '23:59:59', '2026-08-14');

        $this->assertSame('2026-08-14 00:00:00', $resolved['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-14 23:59:59', $resolved['end']->format('Y-m-d H:i:s'));
        $seconds = $resolved['end']->getTimestamp() - $resolved['start']->getTimestamp();
        $this->assertSame(86399, $seconds);
    }

    public function test_equal_start_end_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must differ');

        PlayingWindow::resolveInterval('12:00', '12:00', '2026-08-14');
    }

    public function test_windows_are_distinct(): void
    {
        $this->assertTrue(PlayingWindow::windowsAreDistinct('10:00', '18:00'));
        $this->assertTrue(PlayingWindow::windowsAreDistinct('18:00', '00:00'));
        $this->assertFalse(PlayingWindow::windowsAreDistinct('12:00', '12:00:00'));
    }

    public function test_normalize_and_to_clock_string(): void
    {
        $this->assertSame('09:05:00', PlayingWindow::normalizeStoredTime('09:05'));
        $this->assertSame('09:05:00', PlayingWindow::toClockString('9:05'));
        $this->assertSame('00:00:00', PlayingWindow::toClockString('00:00'));
        $this->assertSame('23:59:59', PlayingWindow::toClockString('23:59:59'));
    }

    public function test_to_clock_string_rejects_24_00(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PlayingWindow::toClockString('24:00:00');
    }

    public function test_clock_regex_rejects_24_00(): void
    {
        $this->assertSame(0, preg_match('/' . PlayingWindow::CLOCK_PATTERN . '/', '24:00'));
        $this->assertSame(0, preg_match('/' . PlayingWindow::CLOCK_PATTERN . '/', '24:00:00'));
        $this->assertSame(1, preg_match('/' . PlayingWindow::CLOCK_PATTERN . '/', '23:59:59'));
        $this->assertSame(1, preg_match('/' . PlayingWindow::CLOCK_PATTERN . '/', '00:00'));
    }

    public function test_resolve_rejects_24_00(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PlayingWindow::resolveInterval('18:00', '24:00:00', '2026-08-14');
    }

    public function test_compare_clock(): void
    {
        $this->assertLessThan(0, PlayingWindow::compareClock('18:00', '19:00'));
        $this->assertLessThan(0, PlayingWindow::compareClock('00:00', '23:00'));
        $this->assertSame(0, PlayingWindow::compareClock('10:00', '10:00:00'));
    }

    public function test_is_active_at(): void
    {
        $this->assertTrue(PlayingWindow::isActiveAt('18:00', '00:00', '19:00:00', '2026-08-14'));
        $this->assertFalse(PlayingWindow::isActiveAt('18:00', '00:00', '00:00:00', '2026-08-14'));
        $this->assertTrue(PlayingWindow::isActiveAt('18:00', '09:00', '01:00:00', '2026-08-14'));
        $this->assertTrue(PlayingWindow::isActiveAt('10:00', '18:00', '12:00:00', '2026-08-14'));
        $this->assertFalse(PlayingWindow::isActiveAt('10:00', '18:00', '18:00:00', '2026-08-14'));
        $this->assertFalse(PlayingWindow::isActiveAt('10:00', '18:00', '09:00:00', '2026-08-14'));
        $this->assertTrue(PlayingWindow::isActiveAt('18:00', '00:00', '23:30:00', '2026-08-14'));
    }

    public function test_invalid_clock_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PlayingWindow::resolveInterval('nope', '18:00');
    }
}
