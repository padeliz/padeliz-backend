<?php

declare(strict_types=1);

namespace App\Services\Lottery;

use App\Models\EditionLottery;
use App\Models\EditionLotteryRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class LotteryRuleGeneratorService
{
    private Randomizer $randomizer;

    public function __construct(Randomizer|int|null $randomizerOrSeed = null)
    {
        if ($randomizerOrSeed instanceof Randomizer) {
            $this->randomizer = $randomizerOrSeed;
        } elseif (is_int($randomizerOrSeed)) {
            $this->randomizer = new Randomizer(new Mt19937($randomizerOrSeed));
        } else {
            $this->randomizer = new Randomizer();
        }
    }

    /**
     * Delete all rules for the lottery's prize lines and insert a balanced generated set.
     *
     * @return Collection<int, EditionLotteryRule>
     */
    public function regenerate(EditionLottery $lottery, Collection $prizeLines, array $editionDivisionIds): Collection
    {
        $rows = $this->buildRules($prizeLines, $editionDivisionIds);
        $prizeIds = $prizeLines->pluck('id_lottery_prize')->map(static fn ($id): int => (int) $id)->all();

        return DB::transaction(function () use ($prizeIds, $rows, $lottery) {
            if ($prizeIds !== []) {
                EditionLotteryRule::query()->whereIn('lottery_prize_id', $prizeIds)->delete();
            }

            foreach ($rows as $row) {
                EditionLotteryRule::create([
                    'lottery_prize_id' => $row['lottery_prize_id'],
                    'edition_division_id' => $row['edition_division_id'],
                    'rule_prize_quantity' => 1,
                    'orderliness' => $row['orderliness'],
                    'gender' => null,
                    'has_social_status' => null,
                    'legal_age' => null,
                    'is_younger_than' => null,
                    'is_older_than' => null,
                    'has_tshirt_size' => null,
                    'is_guest' => null,
                    'is_padel_first_timer' => null,
                    'is_event_first_timer' => null,
                ]);
            }

            return EditionLotteryRule::query()
                ->with(['lotteryPrize.prize', 'lotteryPrize.sponsor', 'lotteryPrize.lottery', 'editionDivision'])
                ->withCount('luckies')
                ->whereHas('lotteryPrize', static fn ($q) => $q->where('lottery_id', $lottery->id_lottery))
                ->orderBy('orderliness')
                ->orderBy('id_lottery_rule')
                ->get();
        });
    }

    /**
     * Build generated rule rows (not persisted).
     *
     * @param  Collection<int, EditionLotteryPrize>|list<array{id_lottery_prize: int, prize_quantity: int}>  $prizeLines
     * @param  list<int>  $editionDivisionIds
     * @return list<array{lottery_prize_id: int, edition_division_id: int, rule_prize_quantity: int, orderliness: int}>
     */
    public function buildRules(Collection|array $prizeLines, array $editionDivisionIds): array
    {
        $lines = $prizeLines instanceof Collection ? $prizeLines->values()->all() : array_values($prizeLines);
        $divisionIds = array_values(array_map('intval', $editionDivisionIds));

        if ($lines === [] || $divisionIds === []) {
            return [];
        }

        $assignments = $this->assignDivisions($lines, $divisionIds);

        return $this->sequenceAssignments($assignments);
    }

    /**
     * Assign each prize unit to a division with balanced column and per-prize counts.
     *
     * Build a per-prize balanced matrix first (each prize uses only floor(Q/|D|) or
     * ceil(Q/|D|) per division), then transfer units between columns until column
     * totals also differ by at most 1 — without breaking per-prize balance.
     *
     * @param  list<array{id_lottery_prize: int, prize_quantity: int}|EditionLotteryPrize>  $prizeLines
     * @param  list<int>  $divisionIds
     * @return list<array{lottery_prize_id: int, edition_division_id: int}>
     */
    public function assignDivisions(array $prizeLines, array $divisionIds): array
    {
        /** @var array<int, int> $qtyByPrize */
        $qtyByPrize = [];
        foreach ($prizeLines as $line) {
            $prizeId = (int) (is_array($line) ? $line['id_lottery_prize'] : $line->id_lottery_prize);
            $qty = (int) (is_array($line) ? $line['prize_quantity'] : $line->prize_quantity);
            if ($qty > 0) {
                $qtyByPrize[$prizeId] = ($qtyByPrize[$prizeId] ?? 0) + $qty;
            }
        }

        if ($qtyByPrize === [] || $divisionIds === []) {
            return [];
        }

        $d = count($divisionIds);
        $total = array_sum($qtyByPrize);
        $colBase = intdiv($total, $d);
        $colExtra = $total % $d;

        $colOrder = $divisionIds;
        $this->shuffleList($colOrder);
        $colTarget = [];
        foreach ($colOrder as $index => $divId) {
            $colTarget[$divId] = $colBase + ($index < $colExtra ? 1 : 0);
        }

        /** @var array<int, array<int, int>> $matrix prizeId => [divId => count] */
        $matrix = [];
        $prizeOrder = array_keys($qtyByPrize);
        $this->shuffleList($prizeOrder);

        foreach ($prizeOrder as $prizeId) {
            $q = $qtyByPrize[$prizeId];
            $rowBase = intdiv($q, $d);
            $rowExtra = $q % $d;
            $matrix[$prizeId] = array_fill_keys($divisionIds, $rowBase);
            $extraDivs = $divisionIds;
            $this->shuffleList($extraDivs);
            for ($i = 0; $i < $rowExtra; $i++) {
                $matrix[$prizeId][$extraDivs[$i]]++;
            }
        }

        $columnFilled = array_fill_keys($divisionIds, 0);
        foreach ($matrix as $row) {
            foreach ($row as $divId => $count) {
                $columnFilled[$divId] += $count;
            }
        }

        $guard = 0;
        while ($guard++ < 10000) {
            $maxLoad = max($columnFilled);
            $minLoad = min($columnFilled);
            if ($maxLoad - $minLoad <= 1) {
                break;
            }

            $highs = [];
            $lows = [];
            foreach ($divisionIds as $divId) {
                if ($columnFilled[$divId] === $maxLoad) {
                    $highs[] = $divId;
                }
                if ($columnFilled[$divId] === $minLoad) {
                    $lows[] = $divId;
                }
            }
            $this->shuffleList($highs);
            $this->shuffleList($lows);

            $moved = false;
            foreach ($prizeOrder as $prizeId) {
                $q = $qtyByPrize[$prizeId];
                $floor = intdiv($q, $d);
                $ceil = $floor + ($q % $d > 0 ? 1 : 0);
                foreach ($highs as $high) {
                    if (($matrix[$prizeId][$high] ?? 0) !== $ceil) {
                        continue;
                    }
                    foreach ($lows as $low) {
                        if (($matrix[$prizeId][$low] ?? 0) !== $floor) {
                            continue;
                        }
                        $matrix[$prizeId][$high]--;
                        $matrix[$prizeId][$low]++;
                        $columnFilled[$high]--;
                        $columnFilled[$low]++;
                        $moved = true;

                        break 3;
                    }
                }
            }

            if (! $moved) {
                break;
            }
        }

        $assignments = [];
        foreach ($matrix as $prizeId => $row) {
            foreach ($row as $divId => $count) {
                for ($i = 0; $i < $count; $i++) {
                    $assignments[] = [
                        'lottery_prize_id' => (int) $prizeId,
                        'edition_division_id' => (int) $divId,
                    ];
                }
            }
        }

        $this->shuffleList($assignments);

        return $assignments;
    }

    /**
     * Greedy sequence maximizing (prize≠prev)+(division≠prev); randomize among best.
     *
     * @param  list<array{lottery_prize_id: int, edition_division_id: int}>  $assignments
     * @return list<array{lottery_prize_id: int, edition_division_id: int, rule_prize_quantity: int, orderliness: int}>
     */
    public function sequenceAssignments(array $assignments): array
    {
        $remaining = array_values($assignments);
        $result = [];
        $prevPrize = null;
        $prevDiv = null;

        while ($remaining !== []) {
            $bestScore = -1;
            $bestIndices = [];

            foreach ($remaining as $index => $pair) {
                $score = 0;
                if ($prevPrize !== null) {
                    if ((int) $pair['lottery_prize_id'] !== $prevPrize) {
                        $score++;
                    }
                    if ((int) $pair['edition_division_id'] !== $prevDiv) {
                        $score++;
                    }
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIndices = [$index];
                } elseif ($score === $bestScore) {
                    $bestIndices[] = $index;
                }
            }

            $pickIndex = $bestIndices[$this->randomizer->getInt(0, count($bestIndices) - 1)];
            $picked = $remaining[$pickIndex];
            array_splice($remaining, $pickIndex, 1);

            $result[] = [
                'lottery_prize_id' => (int) $picked['lottery_prize_id'],
                'edition_division_id' => (int) $picked['edition_division_id'],
                'rule_prize_quantity' => 1,
                'orderliness' => count($result),
            ];
            $prevPrize = (int) $picked['lottery_prize_id'];
            $prevDiv = (int) $picked['edition_division_id'];
        }

        return $result;
    }

    /**
     * @param  list<mixed>  $list
     */
    private function shuffleList(array &$list): void
    {
        if (count($list) <= 1) {
            return;
        }
        $this->randomizer->shuffleArray($list);
    }
}
