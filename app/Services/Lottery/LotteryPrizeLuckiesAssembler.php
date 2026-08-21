<?php

declare(strict_types=1);

namespace App\Services\Lottery;

use App\Models\CachedPdfPlayerImage;
use App\Models\EditionLotteryLucky;
use App\Models\EditionLotteryPrize;
use App\Models\EditionLotteryRule;
use Illuminate\Support\Collection;

final class LotteryPrizeLuckiesAssembler
{
    /** @var list<string> */
    public const ACTIVITY_FIELDS = [
        'updated_at',
        'rejected_at',
        'accepted_at',
        'announced_at',
        'available_at',
        'drawn_at',
        'inserted_at',
    ];

    /**
     * @param  Collection<int, EditionLotteryPrize>  $prizeLines
     */
    public function attach(Collection $prizeLines, ?int $editionDivisionId): void
    {
        if ($prizeLines->isEmpty()) {
            return;
        }

        $prizeIds = $prizeLines->pluck('id_lottery_prize')->all();

        $rulesQuery = EditionLotteryRule::query()->whereIn('lottery_prize_id', $prizeIds);
        if ($editionDivisionId !== null) {
            $rulesQuery->where('edition_division_id', $editionDivisionId);
        }
        $rules = $rulesQuery->get();

        $ruleIds = $rules->pluck('id_lottery_rule')->all();
        $luckies = $ruleIds === []
            ? collect()
            : EditionLotteryLucky::query()
                ->whereIn('lottery_rule_id', $ruleIds)
                ->with(['lotteryRule', 'drawnParticipation.player.phones'])
                ->orderBy('inserted_at')
                ->orderBy('id_lucky')
                ->get();

        $this->preloadPdfAvatars($luckies);

        $rulesByPrize = $rules->groupBy(static fn (EditionLotteryRule $rule): int => (int) $rule->lottery_prize_id);
        $luckiesByRule = $luckies->groupBy(static fn (EditionLotteryLucky $lucky): int => (int) $lucky->lottery_rule_id);

        foreach ($prizeLines as $line) {
            $prizeRules = $rulesByPrize->get((int) $line->id_lottery_prize, collect());
            $merged = collect();
            foreach ($prizeRules as $rule) {
                foreach ($luckiesByRule->get((int) $rule->id_lottery_rule, collect()) as $lucky) {
                    $merged->push($lucky);
                }
            }
            $merged = $merged
                ->sortBy([
                    ['inserted_at', 'asc'],
                    ['id_lucky', 'asc'],
                ])
                ->values();

            $line->setRelation('luckies', $merged);
            $line->setAttribute(
                'rule_prize_quantity_total',
                (int) $prizeRules->sum(static fn (EditionLotteryRule $rule): int => (int) $rule->rule_prize_quantity)
            );
            $line->setAttribute('activity_at', $this->activityAt($merged));
        }
    }

    public function hasUnresolvedPendingForLottery(int $lotteryId): bool
    {
        return EditionLotteryLucky::query()
            ->whereHas(
                'lotteryRule.lotteryPrize',
                static fn ($q) => $q->where('lottery_id', $lotteryId)
            )
            ->whereNull('accepted_at')
            ->whereNull('rejected_at')
            ->exists();
    }

    /**
     * @param  Collection<int, EditionLotteryLucky>  $luckies
     */
    private function preloadPdfAvatars(Collection $luckies): void
    {
        $keys = [];
        foreach ($luckies as $lucky) {
            $playerId = $lucky->drawnParticipation?->player_id;
            if ($playerId === null) {
                continue;
            }
            $collecting = (bool) ($lucky->drawnParticipation?->is_collecting_points ?? false);
            $keys[$playerId . ':' . ($collecting ? '1' : '0')] = [
                'player_id' => (int) $playerId,
                'collecting' => $collecting,
            ];
        }
        if ($keys === []) {
            return;
        }

        $playerIds = array_values(array_unique(array_map(
            static fn (array $row): int => $row['player_id'],
            $keys
        )));
        $images = CachedPdfPlayerImage::query()
            ->whereIn('player_id', $playerIds)
            ->get();
        $byKey = [];
        foreach ($images as $image) {
            $flag = $image->player_is_collecting_points ? '1' : '0';
            $byKey[$image->player_id . ':' . $flag] = $image->url;
        }

        foreach ($luckies as $lucky) {
            $playerId = $lucky->drawnParticipation?->player_id;
            if ($playerId === null) {
                $lucky->setAttribute('pdf_avatar_url', null);

                continue;
            }
            $collecting = (bool) ($lucky->drawnParticipation?->is_collecting_points ?? false);
            $lucky->setAttribute(
                'pdf_avatar_url',
                $byKey[$playerId . ':' . ($collecting ? '1' : '0')] ?? null
            );
        }
    }

    /**
     * @param  Collection<int, EditionLotteryLucky>  $luckies
     */
    private function activityAt(Collection $luckies): ?int
    {
        $max = 0;
        foreach ($luckies as $lucky) {
            foreach (self::ACTIVITY_FIELDS as $field) {
                $n = $lucky->{$field};
                if ($n !== null && is_numeric($n) && (int) $n > $max) {
                    $max = (int) $n;
                }
            }
        }

        return $max > 0 ? $max : null;
    }
}
