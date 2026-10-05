<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\AveragePlayingTimeCalculator;
use PHPUnit\Framework\TestCase;

final class AveragePlayingTimeCalculatorTest extends TestCase
{
    public function test_known_window_and_rounds_yields_one_decimal_minutes(): void
    {
        // 14:00–17:00 = 180 minutes / 6 rounds = 30.0
        $this->assertSame(
            30.0,
            AveragePlayingTimeCalculator::calculate('14:00', '17:00', 6)
        );
    }

    public function test_rounds_total_zero_or_negative_returns_zero(): void
    {
        $this->assertSame(0.0, AveragePlayingTimeCalculator::calculate('14:00', '17:00', 0));
        $this->assertSame(0.0, AveragePlayingTimeCalculator::calculate('14:00', '17:00', -1));
    }

    public function test_non_integer_average_rounds_to_one_decimal(): void
    {
        // 10:00–16:00 = 360 minutes / 7 rounds ≈ 51.428 → 51.4
        $this->assertSame(
            51.4,
            AveragePlayingTimeCalculator::calculate('10:00', '16:00', 7)
        );
    }
}
