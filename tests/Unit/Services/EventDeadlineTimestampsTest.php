<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\EventDeadlineTimestamps;
use Carbon\Carbon;
use Tests\TestCase;

class EventDeadlineTimestampsTest extends TestCase
{
    public function test_from_legacy_null_uses_48h_before_start_of_start_day(): void
    {
        $startsAt = (int) Carbon::parse('2026-11-10 14:00:00', config('app.timezone'))->timestamp;
        $expected = EventDeadlineTimestamps::startOfEventStartDay($startsAt) - (48 * 3600);

        [$registration, $cancellation] = EventDeadlineTimestamps::fromLegacyCancelUntil(null, $startsAt);

        $this->assertSame($expected, $registration);
        $this->assertSame($expected, $cancellation);
    }

    public function test_from_legacy_set_copies_and_clamps_before_event_starts_at(): void
    {
        $startsAt = (int) Carbon::parse('2026-11-10 14:00:00', config('app.timezone'))->timestamp;
        $legacy = $startsAt - 3600;

        [$registration, $cancellation] = EventDeadlineTimestamps::fromLegacyCancelUntil($legacy, $startsAt);
        $this->assertSame($legacy, $registration);
        $this->assertSame($legacy, $cancellation);

        [$registrationClamped, $cancellationClamped] = EventDeadlineTimestamps::fromLegacyCancelUntil(
            $startsAt + 10_000,
            $startsAt
        );
        $this->assertSame($startsAt - 1, $registrationClamped);
        $this->assertSame($startsAt - 1, $cancellationClamped);
    }

    public function test_start_of_event_start_day_uses_app_timezone(): void
    {
        $tz = config('app.timezone');
        $startsAt = Carbon::parse('2026-06-15 14:30:00', $tz)->timestamp;
        $expected = Carbon::parse('2026-06-15', $tz)->startOfDay()->timestamp;

        $this->assertSame($expected, EventDeadlineTimestamps::startOfEventStartDay($startsAt));
    }

    public function test_default_deadline_is_48h_before_start_of_start_day(): void
    {
        $startsAt = (int) Carbon::parse('2026-06-15 12:00:00', config('app.timezone'))->timestamp;
        $expected = EventDeadlineTimestamps::startOfEventStartDay($startsAt) - (48 * 3600);

        $this->assertSame($expected, EventDeadlineTimestamps::defaultDeadline($startsAt));
    }
}
