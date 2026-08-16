<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Standings;

use App\Services\Standings\StandingsRanker;
use PHPUnit\Framework\TestCase;

final class StandingsRankerTest extends TestCase
{
    public function test_wins_beat_points(): void
    {
        $ranks = StandingsRanker::assignCompetitionRanks([
            1 => ['wins' => 1.0, 'points_total' => 10],
            2 => ['wins' => 2.0, 'points_total' => 5],
            3 => ['wins' => 0.0, 'points_total' => 40],
        ]);

        $this->assertSame(1, $ranks[2]);
        $this->assertSame(2, $ranks[1]);
        $this->assertSame(3, $ranks[3]);
    }

    public function test_ties_share_rank_and_skip(): void
    {
        $ranks = StandingsRanker::assignCompetitionRanks([
            'a' => ['wins' => 2.0, 'points_total' => 21],
            'b' => ['wins' => 2.0, 'points_total' => 21],
            'c' => ['wins' => 1.0, 'points_total' => 30],
        ]);

        $this->assertSame(1, $ranks['a']);
        $this->assertSame(1, $ranks['b']);
        $this->assertSame(3, $ranks['c']);
    }

    public function test_null_totals_sort_like_zero(): void
    {
        $ranks = StandingsRanker::assignCompetitionRanks([
            1 => ['wins' => null, 'points_total' => null],
            2 => ['wins' => 0.0, 'points_total' => 0],
            3 => ['wins' => 1.0, 'points_total' => 5],
            4 => ['wins' => 0.5, 'points_total' => null],
        ]);

        $this->assertSame(1, $ranks[3]);
        $this->assertSame(2, $ranks[4]);
        $this->assertSame(3, $ranks[1]);
        $this->assertSame(3, $ranks[2]);
    }

    public function test_half_wins_in_tie_key(): void
    {
        $ranks = StandingsRanker::assignCompetitionRanks([
            1 => ['wins' => 0.5, 'points_total' => 18],
            2 => ['wins' => 0.5, 'points_total' => 18],
            3 => ['wins' => 0.0, 'points_total' => 20],
        ]);

        $this->assertSame(1, $ranks[1]);
        $this->assertSame(1, $ranks[2]);
        $this->assertSame(3, $ranks[3]);
    }
}
