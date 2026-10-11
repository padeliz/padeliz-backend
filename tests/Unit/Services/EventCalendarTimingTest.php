<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\EventCalendarTiming;
use Carbon\Carbon;
use Tests\TestCase;

final class EventCalendarTimingTest extends TestCase
{
    public function test_classify_past_ongoing_upcoming(): void
    {
        $tz = 'Europe/Bucharest';
        $today = Carbon::parse('2026-10-11', $tz)->startOfDay();
        $now = $today->copy()->setTime(12, 0)->timestamp;

        $ongoingStart = $today->copy()->setTime(10, 0)->timestamp;
        $ongoingEnd = $today->copy()->setTime(18, 0)->timestamp;
        $this->assertSame(EventCalendarTiming::ONGOING, EventCalendarTiming::classify($ongoingStart, $ongoingEnd, $now));

        $pastEnd = $today->copy()->subDay()->setTime(18, 0)->timestamp;
        $pastStart = $today->copy()->subDay()->setTime(10, 0)->timestamp;
        $this->assertSame(EventCalendarTiming::PAST, EventCalendarTiming::classify($pastStart, $pastEnd, $now));

        $upcomingStart = $today->copy()->addDay()->setTime(10, 0)->timestamp;
        $upcomingEnd = $today->copy()->addDay()->setTime(18, 0)->timestamp;
        $this->assertSame(EventCalendarTiming::UPCOMING, EventCalendarTiming::classify($upcomingStart, $upcomingEnd, $now));
    }

    public function test_overnight_spans_two_days(): void
    {
        $tz = 'Europe/Bucharest';
        $starts = Carbon::parse('2026-10-11 22:00:00', $tz)->timestamp;
        $ends = Carbon::parse('2026-10-12 02:00:00', $tz)->timestamp;

        $this->assertSame(2, EventCalendarTiming::eventNrOfDays($starts, $ends));
        $this->assertTrue(EventCalendarTiming::overlapsDateRange($starts, $ends, '2026-10-12', '2026-10-12'));
        $this->assertFalse(EventCalendarTiming::overlapsDateRange($starts, $ends, '2026-10-13', '2026-10-13'));
    }
}
