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

        $this->assertSame('2026-08-14', $resolved['start']->format('Y-m-d'));
        $this->assertSame('2026-08-14', $resolved['end']->format('Y-m-d'));
        $this->assertSame('10:00:00', $resolved['start']->format('H:i:s'));
        $this->assertSame('18:00:00', $resolved['end']->format('H:i:s'));
        $this->assertGreaterThan(0, $resolved['end']->getTimestamp() - $resolved['start']->getTimestamp());
    }

    public function test_overnight_resolve(): void
    {
        $resolved = PlayingWindow::resolveInterval('18:00', '00:00', '2026-08-14');

        $this->assertSame('2026-08-14 18:00:00', $resolved['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-15 00:00:00', $resolved['end']->format('Y-m-d H:i:s'));
        $hours = ($resolved['end']->getTimestamp() - $resolved['start']->getTimestamp()) / 3600;
        $this->assertEqualsWithDelta(6.0, $hours, 0.01);
    }

    public function test_end_of_day_24_00(): void
    {
        $resolved = PlayingWindow::resolveInterval('18:00', '24:00:00', '2026-08-14');

        $this->assertSame('2026-08-14 18:00:00', $resolved['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-15 00:00:00', $resolved['end']->format('Y-m-d H:i:s'));
        $hours = ($resolved['end']->getTimestamp() - $resolved['start']->getTimestamp()) / 3600;
        $this->assertEqualsWithDelta(6.0, $hours, 0.01);
        $this->assertNotSame('2026-08-16', $resolved['end']->format('Y-m-d'));
    }

    public function test_equal_start_end_zero_length(): void
    {
        $resolved = PlayingWindow::resolveInterval('12:00', '12:00', '2026-08-14');

        $this->assertSame($resolved['start']->getTimestamp(), $resolved['end']->getTimestamp());
        $this->assertSame('2026-08-14', $resolved['end']->format('Y-m-d'));
    }

    public function test_normalize_and_to_clock_string_preserve_24_00(): void
    {
        $this->assertSame('24:00:00', PlayingWindow::normalizeStoredTime('24:00'));
        $this->assertSame('24:00:00', PlayingWindow::normalizeStoredTime('24:00:00'));
        $this->assertSame('09:05:00', PlayingWindow::normalizeStoredTime('09:05'));
        $this->assertSame('24:00:00', PlayingWindow::toClockString('24:00'));
        $this->assertSame('24:00:00', PlayingWindow::toClockString('24:00:00'));
        $this->assertSame('09:05:00', PlayingWindow::toClockString('9:05'));
        $this->assertNotSame('00:00:00', PlayingWindow::toClockString('24:00:00'));
    }

    public function test_compare_clock(): void
    {
        $this->assertLessThan(0, PlayingWindow::compareClock('18:00', '19:00'));
        $this->assertGreaterThan(0, PlayingWindow::compareClock('24:00:00', '23:00'));
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
        $this->assertTrue(PlayingWindow::isActiveAt('18:00', '24:00:00', '23:30:00', '2026-08-14'));
    }

    public function test_invalid_clock_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PlayingWindow::resolveInterval('nope', '18:00');
    }
}
