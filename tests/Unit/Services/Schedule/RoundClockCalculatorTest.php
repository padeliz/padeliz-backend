<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\RoundClockCalculator;
use PHPUnit\Framework\TestCase;

class RoundClockCalculatorTest extends TestCase
{
    public function test_slot_count_equals_rounds_total(): void
    {
        $slots = RoundClockCalculator::slots('10:00', '16:00', 4, false, false);
        $this->assertCount(4, $slots);
        $this->assertSame('10:00', $slots[0]['start']);
        $this->assertSame($slots[0]['end'], $slots[1]['start']);
        $this->assertSame('16:00', $slots[3]['end']);
    }

    public function test_demo_shortens_usable_end(): void
    {
        $plain = RoundClockCalculator::slots('10:00', '12:00', 2, false, false);
        $demo = RoundClockCalculator::slots('10:00', '12:00', 2, false, true);

        $this->assertSame('12:00', $plain[1]['end']);
        $this->assertSame('11:45', $demo[1]['end']);
    }

    public function test_final_adds_segment_so_last_regular_ends_earlier(): void
    {
        $without = RoundClockCalculator::slots('10:00', '13:00', 2, false, false);
        $withFinal = RoundClockCalculator::slots('10:00', '13:00', 2, true, false);

        $this->assertSame('13:00', $without[1]['end']);
        // 3 segments over 180m → 60m each; round 1 ends at 10:00+120m
        $this->assertSame('12:00', $withFinal[1]['end']);
    }

    public function test_overnight_window(): void
    {
        $slots = RoundClockCalculator::slots('22:00', '02:00', 2, false, false);
        $this->assertCount(2, $slots);
        $this->assertSame('22:00', $slots[0]['start']);
        $this->assertSame('02:00', $slots[1]['end']);
    }

    public function test_overnight_to_midnight(): void
    {
        $slots = RoundClockCalculator::slots('18:00', '00:00', 2, false, false);
        $this->assertCount(2, $slots);
        $this->assertSame('18:00', $slots[0]['start']);
        $this->assertSame('00:00', $slots[1]['end']);
    }

    public function test_zero_rounds_returns_empty(): void
    {
        $this->assertSame([], RoundClockCalculator::slots('10:00', '12:00', 0, false, false));
    }
}
