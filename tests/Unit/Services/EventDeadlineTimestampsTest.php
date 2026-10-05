<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\EventDeadlineTimestamps;
use Carbon\Carbon;
use Tests\TestCase;

class EventDeadlineTimestampsTest extends TestCase
{
    public function test_from_legacy_null_uses_48h_before_start_of_day(): void
    {
        $date = '2026-11-10';
        $expected = EventDeadlineTimestamps::startOfEditionDay($date) - (48 * 3600);

        [$registration, $cancellation] = EventDeadlineTimestamps::fromLegacyCancelUntil(null, $date);

        $this->assertSame($expected, $registration);
        $this->assertSame($expected, $cancellation);
    }

    public function test_from_legacy_set_copies_and_clamps_to_start_of_day(): void
    {
        $date = '2026-11-10';
        $start = EventDeadlineTimestamps::startOfEditionDay($date);
        $legacy = $start - 3600;

        [$registration, $cancellation] = EventDeadlineTimestamps::fromLegacyCancelUntil($legacy, $date);
        $this->assertSame($legacy, $registration);
        $this->assertSame($legacy, $cancellation);

        [$registrationClamped, $cancellationClamped] = EventDeadlineTimestamps::fromLegacyCancelUntil(
            $start + 10_000,
            $date
        );
        $this->assertSame($start, $registrationClamped);
        $this->assertSame($start, $cancellationClamped);
    }

    public function test_start_of_edition_day_uses_app_timezone(): void
    {
        $tz = config('app.timezone');
        $expected = Carbon::parse('2026-06-15', $tz)->startOfDay()->timestamp;

        $this->assertSame($expected, EventDeadlineTimestamps::startOfEditionDay('2026-06-15'));
    }
}
