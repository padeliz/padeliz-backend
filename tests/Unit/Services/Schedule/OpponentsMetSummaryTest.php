<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\OpponentsMetSummary;
use PHPUnit\Framework\TestCase;

final class OpponentsMetSummaryTest extends TestCase
{
    public function test_rounds_count_uses_first_court_only(): void
    {
        $matches = [
            0 => [
                0 => [[0, 1], [2, 3]],
                1 => [[0, 2], [1, 3]],
            ],
            1 => [
                0 => [[4, 5], [6, 7]],
                1 => [[4, 6], [5, 7]],
                2 => [[4, 7], [5, 6]],
                3 => [[4, 5], [6, 7]],
            ],
        ];

        $this->assertSame(2, OpponentsMetSummary::roundsCount($matches));
    }

    public function test_rounds_count_null_for_empty(): void
    {
        $this->assertNull(OpponentsMetSummary::roundsCount(null));
        $this->assertNull(OpponentsMetSummary::roundsCount([]));
    }
}
