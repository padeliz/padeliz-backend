<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Lottery;

use App\Services\Lottery\LotteryRuleGeneratorService;
use PHPUnit\Framework\TestCase;

final class LotteryRuleGeneratorServiceTest extends TestCase
{
    public function test_build_rules_balances_columns_and_per_prize_counts(): void
    {
        $service = new LotteryRuleGeneratorService(42);

        $prizeLines = [
            ['id_lottery_prize' => 10, 'prize_quantity' => 4],
            ['id_lottery_prize' => 20, 'prize_quantity' => 2],
            ['id_lottery_prize' => 30, 'prize_quantity' => 3],
        ];
        $divisionIds = [100, 200, 300];

        $rules = $service->buildRules($prizeLines, $divisionIds);

        $this->assertCount(9, $rules);

        $columnTotals = array_fill_keys($divisionIds, 0);
        $perPrize = [];
        foreach ($rules as $index => $rule) {
            $this->assertSame(1, $rule['rule_prize_quantity']);
            $this->assertSame($index, $rule['orderliness']);
            $this->assertContains($rule['edition_division_id'], $divisionIds);
            $columnTotals[$rule['edition_division_id']]++;
            $perPrize[$rule['lottery_prize_id']][$rule['edition_division_id']] =
                ($perPrize[$rule['lottery_prize_id']][$rule['edition_division_id']] ?? 0) + 1;
        }

        $colValues = array_values($columnTotals);
        $this->assertLessThanOrEqual(1, max($colValues) - min($colValues));

        foreach ($prizeLines as $line) {
            $prizeId = $line['id_lottery_prize'];
            $this->assertSame(
                $line['prize_quantity'],
                array_sum($perPrize[$prizeId] ?? [])
            );
            $counts = [];
            foreach ($divisionIds as $divId) {
                $counts[] = $perPrize[$prizeId][$divId] ?? 0;
            }
            $this->assertLessThanOrEqual(1, max($counts) - min($counts));
        }
    }

    public function test_sequence_never_picks_worse_score_when_better_remains(): void
    {
        $service = new LotteryRuleGeneratorService(7);

        $assignments = [
            ['lottery_prize_id' => 1, 'edition_division_id' => 10],
            ['lottery_prize_id' => 1, 'edition_division_id' => 20],
            ['lottery_prize_id' => 2, 'edition_division_id' => 10],
            ['lottery_prize_id' => 2, 'edition_division_id' => 20],
        ];

        $sequenced = $service->sequenceAssignments($assignments);

        $remaining = $assignments;
        $prevPrize = null;
        $prevDiv = null;

        foreach ($sequenced as $picked) {
            $bestScore = -1;
            foreach ($remaining as $candidate) {
                $score = 0;
                if ($prevPrize !== null) {
                    if ((int) $candidate['lottery_prize_id'] !== $prevPrize) {
                        $score++;
                    }
                    if ((int) $candidate['edition_division_id'] !== $prevDiv) {
                        $score++;
                    }
                }
                $bestScore = max($bestScore, $score);
            }

            $pickedScore = 0;
            if ($prevPrize !== null) {
                if ((int) $picked['lottery_prize_id'] !== $prevPrize) {
                    $pickedScore++;
                }
                if ((int) $picked['edition_division_id'] !== $prevDiv) {
                    $pickedScore++;
                }
            }
            $this->assertSame($bestScore, $pickedScore);

            $removed = false;
            foreach ($remaining as $i => $candidate) {
                if ((int) $candidate['lottery_prize_id'] === (int) $picked['lottery_prize_id']
                    && (int) $candidate['edition_division_id'] === (int) $picked['edition_division_id']) {
                    array_splice($remaining, $i, 1);
                    $removed = true;

                    break;
                }
            }
            $this->assertTrue($removed);

            $prevPrize = (int) $picked['lottery_prize_id'];
            $prevDiv = (int) $picked['edition_division_id'];
        }
    }

    public function test_seed_makes_build_deterministic(): void
    {
        $a = (new LotteryRuleGeneratorService(99))->buildRules(
            [['id_lottery_prize' => 1, 'prize_quantity' => 5]],
            [10, 20]
        );
        $b = (new LotteryRuleGeneratorService(99))->buildRules(
            [['id_lottery_prize' => 1, 'prize_quantity' => 5]],
            [10, 20]
        );

        $this->assertSame($a, $b);
    }
}
