<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\TemplateMatches;
use App\Services\Schedule\TemplateMatchesGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Pure-function unit tests for {@see TemplateMatchesGenerator}.
 *
 * Combinatorial DFS / pipeline reflection coverage lives outside PHPUnit.
 */
final class TemplateMatchesGeneratorTest extends TestCase
{
    public function test_lehmer_seed_index_zero_is_identity_permutation(): void
    {
        $this->assertSame([0, 1, 2, 3, 4, 5, 6, 7], $this->invokeLehmer(0, 16, 8));
        $this->assertSame([0, 1, 2, 3], $this->invokeLehmer(0, 4, 4));
    }

    public function test_lehmer_seeds_are_distinct_when_count_at_most_factorial(): void
    {
        $seen = [];
        for ($i = 0; $i < 4; $i++) {
            $perm = $this->invokeLehmer($i, 4, 4);
            $seen[implode(',', $perm)] = true;
        }
        $this->assertCount(4, $seen);
    }

    public function test_lehmer_decodes_every_lex_index_when_count_equals_factorial(): void
    {
        $expected = [];
        $perm = [0, 1, 2, 3];
        do {
            $expected[] = $perm;
            $perm = $this->invokePcNext($perm, 3);
        } while ($perm !== false);

        $this->assertCount(24, $expected);

        $actual = [];
        for ($i = 0; $i < 24; $i++) {
            $actual[] = $this->invokeLehmer($i, 24, 4);
        }

        $this->assertSame($expected, $actual);
    }

    public function test_lehmer_returns_a_valid_permutation_for_arbitrary_seed(): void
    {
        foreach ([1, 5, 17, 31] as $seedIndex) {
            $perm = $this->invokeLehmer($seedIndex, 32, 12);
            $sorted = $perm;
            sort($sorted);
            $this->assertSame(range(0, 11), $sorted, "seedIndex={$seedIndex} must yield a permutation of 0..11");
        }
    }

    public function test_players_met_too_much_rejects_gap_above_limit(): void
    {
        $playersMet = [0 => [1 => 2, 2 => 0]];

        $result = $this->invokePlayersMetTooMuch([0, 3], [1, 4], $playersMet, 1);

        $this->assertTrue($result);
    }

    public function test_players_met_too_much_allows_gap_at_limit(): void
    {
        $playersMet = [0 => [1 => 1, 2 => 0]];

        $result = $this->invokePlayersMetTooMuch([0, 3], [1, 4], $playersMet, 1);

        $this->assertFalse($result);
    }

    public function test_players_met_too_much_strict_mode_rejects_any_gap(): void
    {
        $playersMet = [0 => [1 => 1, 2 => 0]];

        $result = $this->invokePlayersMetTooMuch([0, 3], [1, 4], $playersMet, 0);

        $this->assertTrue($result);
    }

    public function test_players_met_too_much_ignores_high_gap_when_mostmet_absent_from_match(): void
    {
        $playersMet = [0 => [9 => 5, 2 => 0]];

        $result = $this->invokePlayersMetTooMuch([0, 3], [1, 4], $playersMet, 1);

        $this->assertFalse($result);
    }

    public function test_use_static_budgets_returns_constructor_injected_values(): void
    {
        $generator = new TemplateMatchesGenerator(
            null,
            123,
            456,
            0,
            0
        );
        $generator->setUseStaticBudgets(true);

        $this->assertSame([123, 123, 456], $this->invokeBudgetFor($generator, 4, 2));
    }

    public function test_dynamic_budget_uses_compute_methods_when_static_disabled(): void
    {
        $generator = new TemplateMatchesGenerator(
            null,
            123,
            456,
            0,
            0
        );
        $generator->setUseStaticBudgets(false);

        $this->assertSame(
            [
                TemplateMatchesGenerator::computePairingWallBudgetNs(8, 2, 1),
                TemplateMatchesGenerator::computeMatchMakingWallBudgetNs(8, 2, 1),
                TemplateMatchesGenerator::computeOrderingWallBudgetNs(8, 2, 1),
            ],
            $this->invokeBudgetFor($generator, 8, 2)
        );
    }

    public function test_set_use_static_budgets_overrides_dynamic_defaults(): void
    {
        $generator = new TemplateMatchesGenerator(
            null,
            777,
            999,
            0,
            0
        );

        $this->assertSame(
            [
                TemplateMatchesGenerator::computePairingWallBudgetNs(16, 12, 1),
                TemplateMatchesGenerator::computeMatchMakingWallBudgetNs(16, 12, 1),
                TemplateMatchesGenerator::computeOrderingWallBudgetNs(16, 12, 1),
            ],
            $this->invokeBudgetFor($generator, 16, 12),
            'Default generator uses dynamic per-combo budgets.'
        );

        $generator->setUseStaticBudgets(true);

        $this->assertSame(
            [777, 777, 999],
            $this->invokeBudgetFor($generator, 16, 12),
            'setUseStaticBudgets(true) must force every combo down the constructor-injected path.'
        );
    }

    public function test_pairing_relax_attempts_round_trip_through_json(): void
    {
        $template = new TemplateMatches(
            8,
            2,
            1,
            1,
            false,
            [[[[0, 1], [2, 3]]]],
            0.95,
            0.97,
            [0 => 2, 1 => 2, 2 => 2, 3 => 2, 4 => 2, 5 => 2, 6 => 2, 7 => 2],
            0,
            8,
            'FACTORIAL_COMPLETE',
            0.1,
            100,
            1,
            1,
            0.0,
            2,
            2,
            [0 => [1 => 1]],
            1,
            null,
            null,
            null,
            10,
            7,
            10,
            7,
            50,
            'FACTORIAL_COMPLETE',
            0.05,
            2,
            [
                [
                    'meetingsVariationLimit' => 1,
                    'permutationsIterated' => 3,
                    'templatesGenerated' => 0,
                    'nodesExplored' => 0,
                    'time' => 0.04,
                    'candidatesCollected' => 0,
                    'candidatesDeduped' => 0,
                    'stopReason' => 'DEADLINE',
                ],
                [
                    'meetingsVariationLimit' => 2,
                    'permutationsIterated' => 5,
                    'templatesGenerated' => 5,
                    'nodesExplored' => 0,
                    'time' => 0.06,
                    'candidatesCollected' => 5,
                    'candidatesDeduped' => 5,
                    'stopReason' => 'FACTORIAL_COMPLETE',
                ],
            ],
            0.9,
            0.95,
            0,
            0,
            null,
            null,
            0,
            null,
            1,
            'FACTORIAL_COMPLETE',
            10,
            7,
            50,
            1,
            1,
            0.05,
            null,
            null,
            null,
            [
                [
                    'meetingsVariationLimit' => 1,
                    'candidatesTried' => 0,
                    'eligible' => false,
                    'time' => 0.01,
                    'stopReason' => 'DEADLINE',
                ],
                [
                    'meetingsVariationLimit' => 2,
                    'candidatesTried' => 1,
                    'eligible' => true,
                    'time' => 0.02,
                    'stopReason' => null,
                ],
            ]
        );

        $round = TemplateMatches::fromArray($template->toArray());

        $this->assertSame(2, $round->getMatchMakingStatsMeetingsVariationLimit());
        $attempts = $round->getMatchMakingStatsRelaxAttempts();
        $this->assertNotNull($attempts);
        $this->assertCount(2, $attempts);
        $this->assertSame(1, $attempts[0]['meetingsVariationLimit']);
        $this->assertSame(3, $attempts[0]['permutationsIterated']);
        $this->assertSame(0, $attempts[0]['templatesGenerated']);
        $this->assertSame(0.04, $attempts[0]['time']);
        $this->assertSame(2, $attempts[1]['meetingsVariationLimit']);
        $this->assertSame(5, $attempts[1]['templatesGenerated']);

        $orderingAttempts = $round->getOrderingStatsRelaxAttempts();
        $this->assertNotNull($orderingAttempts);
        $this->assertCount(2, $orderingAttempts);
        $this->assertSame(1, $orderingAttempts[0]['meetingsVariationLimit']);
        $this->assertSame(0, $orderingAttempts[0]['candidatesTried']);
        $this->assertFalse($orderingAttempts[0]['eligible']);
        $this->assertTrue($orderingAttempts[1]['eligible']);
        $this->assertNull($orderingAttempts[1]['stopReason']);
    }

    /**
     * @param array{0:int,1:int} $pair1
     * @param array{0:int,1:int} $pair2
     * @param array<int, array<int, int>> $playersMet
     */
    private function invokePlayersMetTooMuch(array $pair1, array $pair2, array $playersMet, int $meetingsVariationLimit): bool
    {
        $generator = new TemplateMatchesGenerator();
        $reflection = new \ReflectionClass(TemplateMatchesGenerator::class);
        $method = $reflection->getMethod('playersMetTooMuch');
        $method->setAccessible(true);

        return (bool) $method->invoke($generator, $pair1, $pair2, $playersMet, $meetingsVariationLimit);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function invokeBudgetFor(TemplateMatchesGenerator $generator, int $players, int $partners, int $courts = 1, bool $fixedTeams = false): array
    {
        $reflection = new \ReflectionClass(TemplateMatchesGenerator::class);
        $method = $reflection->getMethod('budgetFor');
        $method->setAccessible(true);

        return $method->invoke($generator, $players, $partners, $courts, $fixedTeams);
    }

    /**
     * @return array<int, int>
     */
    private function invokeLehmer(int $seedIndex, int $totalSeeds, int $n): array
    {
        $generator = new TemplateMatchesGenerator();
        $reflection = new \ReflectionClass(TemplateMatchesGenerator::class);
        $method = $reflection->getMethod('lehmerSeedPermutation');
        $method->setAccessible(true);

        return $method->invoke($generator, $seedIndex, $totalSeeds, $n);
    }

    /**
     * @param array<int, int> $perm
     * @return array<int, int>|false
     */
    private function invokePcNext(array $perm, int $size)
    {
        $generator = new TemplateMatchesGenerator();
        $reflection = new \ReflectionClass(TemplateMatchesGenerator::class);
        $method = $reflection->getMethod('pcNextPermutation');
        $method->setAccessible(true);

        return $method->invoke($generator, $perm, $size);
    }
}
