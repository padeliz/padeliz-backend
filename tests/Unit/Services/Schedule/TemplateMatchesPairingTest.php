<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\PartnersFairnessScorer;
use App\Services\Schedule\TemplateMatchesGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Pure-function pairing helpers (no runPairingPhase DFS).
 */
final class TemplateMatchesPairingTest extends TestCase
{
    public function test_compute_pairing_dfs_branch_cap_clamps_to_formula(): void
    {
        $this->assertSame(10_000, TemplateMatchesGenerator::computePairingDfsBranchCap(1));
        $this->assertSame(10_000, TemplateMatchesGenerator::computePairingDfsBranchCap(20));
        $this->assertSame(21_200, TemplateMatchesGenerator::computePairingDfsBranchCap(48));
        $this->assertSame(50_000, TemplateMatchesGenerator::computePairingDfsBranchCap(200));
    }

    public function test_order_pairing_candidates_sorts_by_edge_penalty_then_lehmer(): void
    {
        $generator = new TemplateMatchesGenerator(null, 1, 12);
        $reflection = new \ReflectionClass(TemplateMatchesGenerator::class);
        $method = $reflection->getMethod('orderPairingCandidates');
        $method->setAccessible(true);

        $playersCount = 8;
        $partnersPerPlayer = 4;
        $activePlayer = 0;
        $candidates = range(1, $playersCount - 1);
        $candidateOrder = range(0, $playersCount - 2);

        /** @var array<int, int> $ordered */
        $ordered = $method->invoke(
            $generator,
            $candidates,
            $candidateOrder,
            $activePlayer,
            $playersCount,
            $partnersPerPlayer
        );

        $penalties = [];
        foreach ($ordered as $q) {
            $penalties[] = PartnersFairnessScorer::edgePenalty($activePlayer, $q, $playersCount, $partnersPerPlayer);
        }

        $sortedPenalties = $penalties;
        sort($sortedPenalties, SORT_NUMERIC);
        $this->assertSame($sortedPenalties, $penalties);
    }
}
