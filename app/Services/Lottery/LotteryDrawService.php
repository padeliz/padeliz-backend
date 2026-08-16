<?php

declare(strict_types=1);

namespace App\Services\Lottery;

use App\Models\CachedPdfPlayerImage;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionReplacement;
use App\Models\EditionLottery;
use App\Models\EditionLotteryLucky;
use App\Models\EditionLotteryRule;
use App\Models\EditionParticipation;
use App\Models\Prize;
use App\Models\PrizeFromClub;
use App\Services\Schedule\PlayingWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class LotteryDrawService
{
    /**
     * Eligible participations for a draw within an edition.
     *
     * Rules: absence_reason IS NULL, not already drawn this edition,
     * not division-wide replaced; prefer rule edition_division_id; ignore demographics.
     *
     * @return Builder<EditionParticipation>
     */
    public function eligibleParticipationsQuery(int $editionId, ?int $preferEditionDivisionId = null, ?string $playingStartTimeGate = null): Builder
    {
        $replacedParticipationIds = EditionDivisionReplacement::query()
            ->whereNull('match_id')
            ->whereIn('edition_division_id', function ($q) use ($editionId): void {
                $q->select('id_edition_division')
                    ->from('edition_divisions')
                    ->where('edition_id', $editionId);
            })
            ->pluck('original_participation_id')
            ->all();

        $drawnParticipationIds = EditionLotteryLucky::query()
            ->whereIn('lottery_rule_id', function ($q) use ($editionId): void {
                $q->select('id_lottery_rule')
                    ->from('edition_lottery_rules')
                    ->whereIn('lottery_id', function ($q2) use ($editionId): void {
                        $q2->select('id_lottery')
                            ->from('edition_lotteries')
                            ->where('edition_id', $editionId);
                    });
            })
            ->pluck('drawn_participation_id')
            ->all();

        $query = EditionParticipation::query()
            ->where('edition_participations.edition_id', $editionId)
            ->whereNull('absence_reason')
            ->when($replacedParticipationIds !== [], fn (Builder $q) => $q->whereNotIn('id_participation', $replacedParticipationIds))
            ->when($drawnParticipationIds !== [], fn (Builder $q) => $q->whereNotIn('id_participation', $drawnParticipationIds));

        if ($playingStartTimeGate !== null) {
            // edition.date <= today OR participation's division start <= gate
            $query->where(function (Builder $q) use ($playingStartTimeGate): void {
                $q->whereExists(function ($sub): void {
                    $sub->select(DB::raw(1))
                        ->from('editions')
                        ->whereColumn('editions.id_edition', 'edition_participations.edition_id')
                        ->whereDate('editions.date', '<=', Carbon::today()->toDateString());
                })->orWhereExists(function ($sub) use ($playingStartTimeGate): void {
                    $sub->select(DB::raw(1))
                        ->from('edition_divisions')
                        ->whereColumn('edition_divisions.id_edition_division', 'edition_participations.edition_division_id')
                        ->where('edition_divisions.playing_start_time', '<=', $playingStartTimeGate);
                });
            });
        }

        if ($preferEditionDivisionId !== null) {
            $query->orderByRaw('(edition_division_id = ?) DESC', [$preferEditionDivisionId]);
        }

        return $query->inRandomOrder();
    }

    /**
     * Filter an in-memory collection (unit-test friendly).
     *
     * @param  Collection<int, EditionParticipation>  $participations
     * @param  list<int>  $alreadyDrawnParticipationIds
     * @param  list<int>  $divisionWideReplacedParticipationIds
     * @return Collection<int, EditionParticipation>
     */
    public function filterEligibleParticipations(
        Collection $participations,
        array $alreadyDrawnParticipationIds = [],
        array $divisionWideReplacedParticipationIds = [],
        ?int $preferEditionDivisionId = null,
    ): Collection {
        $drawn = array_fill_keys($alreadyDrawnParticipationIds, true);
        $replaced = array_fill_keys($divisionWideReplacedParticipationIds, true);

        $filtered = $participations
            ->filter(function (EditionParticipation $p) use ($drawn, $replaced): bool {
                if ($p->absence_reason !== null) {
                    return false;
                }
                if (isset($drawn[(int) $p->id_participation])) {
                    return false;
                }
                if (isset($replaced[(int) $p->id_participation])) {
                    return false;
                }

                return true;
            })
            ->values();

        if ($preferEditionDivisionId !== null) {
            $filtered = $filtered->sortByDesc(
                fn (EditionParticipation $p) => (int) $p->edition_division_id === (int) $preferEditionDivisionId ? 1 : 0
            )->values();
        }

        return $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    public function liveState(EditionLottery $lottery): array
    {
        $edition = $lottery->edition()->with('location')->firstOrFail();
        $editionId = (int) $edition->id_edition;
        $nextEdition = Edition::query()->whereDate('date', '>', Carbon::today()->toDateString())->orderBy('date')->first();

        $pending = $this->currentPendingLucky($editionId);

        if ($pending === null) {
            $luckiesCount = $this->editionLuckiesCount($editionId);
            if ($luckiesCount === 0) {
                $pending = $this->createFirstDraw($edition);
            }
        }

        if ($pending !== null) {
            $pending->loadMissing([
                'drawnParticipation.player',
                'drawnParticipation.editionDivision.division',
                'drawnParticipation.editionDivision.editionDivisionCourts.court',
                'lotteryRule.lottery.prize.clubPrizes',
                'lotteryRule.editionDivision',
            ]);

            return [
                'status' => 'pending',
                ...$this->lotteryBrandingUrls(),
                'edition' => [
                    'id_edition' => $edition->id_edition,
                    'name' => $edition->name,
                    'date' => optional($edition->date)?->format('Y-m-d') ?? (string) $edition->getRawOriginal('date'),
                ],
                'next_edition' => $nextEdition ? [
                    'id_edition' => $nextEdition->id_edition,
                    'name' => $nextEdition->name,
                    'date' => optional($nextEdition->date)?->format('Y-m-d') ?? (string) $nextEdition->getRawOriginal('date'),
                ] : null,
                'lucky' => $this->serializeLucky($pending, $edition, $nextEdition),
                'accepted' => [],
            ];
        }

        $accepted = EditionLotteryLucky::query()
            ->whereHas('lotteryRule.lottery', fn (Builder $q) => $q->where('edition_id', $editionId))
            ->whereNotNull('accepted_at')
            ->whereNull('rejected_at')
            ->with(['drawnParticipation.player', 'drawnParticipation.editionDivision.division', 'lotteryRule.lottery'])
            ->orderBy('id_lucky')
            ->get()
            ->map(fn (EditionLotteryLucky $lucky) => $this->serializeLucky($lucky, $edition, $nextEdition))
            ->all();

        return [
            'status' => 'finished',
            ...$this->lotteryBrandingUrls(),
            'edition' => [
                'id_edition' => $edition->id_edition,
                'name' => $edition->name,
                'date' => optional($edition->date)?->format('Y-m-d') ?? (string) $edition->getRawOriginal('date'),
            ],
            'next_edition' => $nextEdition ? [
                'id_edition' => $nextEdition->id_edition,
                'name' => $nextEdition->name,
                'date' => optional($nextEdition->date)?->format('Y-m-d') ?? (string) $nextEdition->getRawOriginal('date'),
            ] : null,
            'lucky' => null,
            'accepted' => $accepted,
        ];
    }

    public function drawNext(EditionLottery $lottery): EditionLotteryLucky
    {
        $edition = $lottery->edition()->firstOrFail();
        $pending = $this->currentPendingLucky((int) $edition->id_edition);
        if ($pending !== null) {
            return $pending;
        }

        if ($this->editionLuckiesCount((int) $edition->id_edition) === 0) {
            return $this->createFirstDraw($edition);
        }

        throw new RuntimeException('No pending draw and lottery already started; accept or reject the previous lucky first.');
    }

    public function announce(EditionLotteryLucky $lucky): EditionLotteryLucky
    {
        if ($lucky->announced_at === null) {
            $lucky->announced_at = time();
            $lucky->save();
        }

        return $lucky->fresh();
    }

    public function reject(EditionLotteryLucky $lucky, Edition $edition): EditionLotteryLucky
    {
        return DB::transaction(function () use ($lucky, $edition): EditionLotteryLucky {
            $lucky->rejected_at = time();
            $lucky->save();

            $rule = $lucky->lotteryRule()->first();
            $preferDivisionId = $rule?->edition_division_id;
            $gateTime = null;
            if ($preferDivisionId) {
                $gateTime = EditionDivision::query()->whereKey($preferDivisionId)->value('playing_start_time');
                $gateTime = $gateTime ? Carbon::parse((string) $gateTime)->format('H:i:s') : date('H:i:s');
            } else {
                $gateTime = date('H:i:s');
            }

            $participation = $this->eligibleParticipationsQuery(
                (int) $edition->id_edition,
                $preferDivisionId !== null ? (int) $preferDivisionId : null,
                $gateTime,
            )->first();

            if ($participation === null) {
                return $lucky->fresh();
            }

            return EditionLotteryLucky::query()->create([
                'lottery_rule_id' => $lucky->lottery_rule_id,
                'drawn_participation_id' => $participation->id_participation,
                'drawn_at' => time(),
                'available_at' => time() + 10,
            ]);
        });
    }

    public function accept(EditionLotteryLucky $lucky, Edition $edition): ?EditionLotteryLucky
    {
        return DB::transaction(function () use ($lucky, $edition): ?EditionLotteryLucky {
            $lucky->accepted_at = time();
            $lucky->save();

            $rules = EditionLotteryRule::query()
                ->whereHas('lottery', fn (Builder $q) => $q->where('edition_id', $edition->id_edition))
                ->with('editionDivision')
                ->get()
                ->sortBy(function (EditionLotteryRule $rule): array {
                    $start = $rule->editionDivision?->playing_start_time;

                    return [
                        $start === null ? 1 : 0,
                        (string) $start,
                        (int) $rule->orderliness,
                        (int) $rule->id_lottery_rule,
                    ];
                })
                ->values();

            foreach ($rules as $rule) {
                $acceptedCount = EditionLotteryLucky::query()
                    ->where('lottery_rule_id', $rule->id_lottery_rule)
                    ->whereNotNull('accepted_at')
                    ->count();

                if ($acceptedCount >= (int) $rule->rule_prize_quantity) {
                    continue;
                }

                $existing = EditionLotteryLucky::query()
                    ->where('lottery_rule_id', $rule->id_lottery_rule)
                    ->whereNull('accepted_at')
                    ->whereNull('rejected_at')
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                $currentTime = date('H:i:s');
                $participation = $this->eligibleParticipationsQuery(
                    (int) $edition->id_edition,
                    $rule->edition_division_id !== null ? (int) $rule->edition_division_id : null,
                    $currentTime,
                )->with('editionDivision')->first();

                if ($participation === null) {
                    continue;
                }

                $availableAt = $this->computeNextAvailableAt($edition, $participation, $currentTime);

                return EditionLotteryLucky::query()->create([
                    'lottery_rule_id' => $rule->id_lottery_rule,
                    'drawn_participation_id' => $participation->id_participation,
                    'drawn_at' => time(),
                    'available_at' => $availableAt,
                ]);
            }

            return null;
        });
    }

    /**
     * @return array{logo: string, background: string, intervals: list<array<string, mixed>>}
     */
    public function prizesBoard(EditionLottery $lottery): array
    {
        $edition = $lottery->edition()->with('location')->firstOrFail();
        $editionId = (int) $edition->id_edition;
        $clubId = $edition->location?->club_id;
        $nextEdition = Edition::query()->whereDate('date', '>', Carbon::today()->toDateString())->orderBy('date')->first();

        $rules = EditionLotteryRule::query()
            ->whereHas('lottery', fn (Builder $q) => $q->where('edition_id', $editionId))
            ->with(['lottery.prize.clubPrizes', 'editionDivision', 'luckies.drawnParticipation.player', 'luckies.drawnParticipation.editionDivision.division'])
            ->get()
            ->sortBy(function (EditionLotteryRule $rule): array {
                $start = $rule->editionDivision?->playing_start_time;

                return [
                    $start === null ? 1 : 0,
                    (string) $start,
                    (int) $rule->orderliness,
                    (int) $rule->id_lottery_rule,
                ];
            })
            ->values();

        $grouped = [];
        foreach ($rules as $rule) {
            $start = $rule->editionDivision?->playing_start_time;
            $end = $rule->editionDivision?->playing_end_time;
            $key = $rule->edition_division_id === null
                ? '__unscheduled__'
                : $this->prizesBoardIntervalKey($start, $end);
            $grouped[$key][] = $rule;
        }

        uksort($grouped, static function (string $a, string $b): int {
            if ($a === '__unscheduled__') {
                return 1;
            }
            if ($b === '__unscheduled__') {
                return -1;
            }

            return strcmp(explode('|', $a)[0], explode('|', $b)[0]);
        });

        $intervals = [];
        foreach ($grouped as $key => $intervalRules) {
            /** @var list<EditionLotteryRule> $intervalRules */
            $byLottery = [];
            foreach ($intervalRules as $rule) {
                $byLottery[(int) $rule->lottery_id][] = $rule;
            }

            $prizes = [];
            foreach ($byLottery as $lotteryRules) {
                usort($lotteryRules, static fn (EditionLotteryRule $a, EditionLotteryRule $b): int => [
                    (int) $a->orderliness, (int) $a->id_lottery_rule,
                ] <=> [(int) $b->orderliness, (int) $b->id_lottery_rule]);

                $head = $lotteryRules[0];
                $qty = array_sum(array_map(fn (EditionLotteryRule $r) => (int) $r->rule_prize_quantity, $lotteryRules));
                $prizePayload = $this->resolvePrizePayload($head->lottery?->prize, $clubId !== null ? (int) $clubId : null, $nextEdition);

                $luckies = [];
                foreach ($lotteryRules as $rule) {
                    foreach ($rule->luckies as $lucky) {
                        $luckies[] = [
                            'id_lucky' => $lucky->id_lucky,
                            'status' => $this->luckyStatus($lucky),
                            'player_name' => $lucky->drawnParticipation?->player?->name,
                            'division_title' => $lucky->drawnParticipation?->editionDivision?->division_title,
                            'division_color' => $lucky->drawnParticipation?->editionDivision?->division?->color,
                        ];
                    }
                }

                $prizes[] = [
                    'id_lottery' => $head->lottery_id,
                    'interval_quantity' => $qty,
                    'prize' => $prizePayload,
                    'luckies' => $luckies,
                ];
            }

            $intervals[] = [
                'label' => $key === '__unscheduled__'
                    ? 'Unscheduled'
                    : $this->formatIntervalLabelFromKey($key),
                'prizes' => $prizes,
            ];
        }

        return [
            ...$this->lotteryBrandingUrls(),
            'intervals' => $intervals,
        ];
    }

    /**
     * Absolute public URLs for lottery live / prizes-board chrome.
     *
     * @return array{logo: string, background: string}
     */
    private function lotteryBrandingUrls(): array
    {
        $logo = config('padeliz_branding.lottery.logo');
        $background = config('padeliz_branding.lottery.background');

        return [
            'logo' => url('/media/' . ltrim(is_string($logo) ? $logo : 'lottery/minitour-long-e.png', '/')),
            'background' => url('/media/' . ltrim(is_string($background) ? $background : 'lottery/padel-balls.jpg', '/')),
        ];
    }

    private function createFirstDraw(Edition $edition): EditionLotteryLucky
    {
        $firstRule = EditionLotteryRule::query()
            ->whereHas('lottery', fn (Builder $q) => $q->where('edition_id', $edition->id_edition))
            ->with('editionDivision')
            ->get()
            ->sortBy(function (EditionLotteryRule $rule): array {
                $start = $rule->editionDivision?->playing_start_time;

                return [
                    $start === null ? 1 : 0,
                    (string) $start,
                    (int) $rule->orderliness,
                    (int) $rule->id_lottery_rule,
                ];
            })
            ->first();

        if ($firstRule === null) {
            throw new RuntimeException('No lottery rules found for this edition.');
        }

        $participation = $this->eligibleParticipationsQuery(
            (int) $edition->id_edition,
            $firstRule->edition_division_id !== null ? (int) $firstRule->edition_division_id : null,
        )->first();

        if ($participation === null) {
            throw new RuntimeException('No eligible participations for this edition.');
        }

        $start = $firstRule->editionDivision?->playing_start_time;
        $editionDate = optional($edition->date)?->format('Y-m-d') ?? (string) $edition->getRawOriginal('date');
        $target = $start
            ? Carbon::parse($editionDate . ' ' . Carbon::parse((string) $start)->format('H:i:s'))->addMinutes(20)->getTimestamp()
            : time() + 10;

        return EditionLotteryLucky::query()->create([
            'lottery_rule_id' => $firstRule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
            'drawn_at' => time(),
            'available_at' => max(time() + 10, $target),
        ]);
    }

    private function currentPendingLucky(int $editionId): ?EditionLotteryLucky
    {
        return EditionLotteryLucky::query()
            ->whereHas('lotteryRule.lottery', fn (Builder $q) => $q->where('edition_id', $editionId))
            ->whereNull('accepted_at')
            ->whereNull('rejected_at')
            ->orderByDesc('id_lucky')
            ->first();
    }

    private function editionLuckiesCount(int $editionId): int
    {
        return EditionLotteryLucky::query()
            ->whereHas('lotteryRule.lottery', fn (Builder $q) => $q->where('edition_id', $editionId))
            ->count();
    }

    private function computeNextAvailableAt(Edition $edition, EditionParticipation $participation, string $currentTime): int
    {
        $participation->loadMissing('editionDivision');
        $drawnStart = $participation->editionDivision?->playing_start_time;
        $drawnStartStr = PlayingWindow::toClockString($drawnStart) ?? PlayingWindow::normalizeStoredTime($currentTime);

        $sumOfPrizeQuantity = (int) EditionLotteryRule::query()
            ->whereHas('lottery', fn (Builder $q) => $q->where('edition_id', $edition->id_edition))
            ->where(function (Builder $q) use ($currentTime, $drawnStartStr): void {
                $q->whereNull('edition_division_id')
                    ->orWhereHas('editionDivision', function (Builder $ed) use ($currentTime, $drawnStartStr): void {
                        $ed->where('playing_start_time', '<=', $currentTime)
                            ->orWhere('playing_start_time', '=', $drawnStartStr);
                    });
            })
            ->sum('rule_prize_quantity');

        $nonRejectedCount = EditionLotteryLucky::query()
            ->whereHas('lotteryRule.lottery', fn (Builder $q) => $q->where('edition_id', $edition->id_edition))
            ->whereNull('rejected_at')
            ->whereHas('lotteryRule.editionDivision', function (Builder $ed) use ($currentTime, $drawnStartStr, $edition): void {
                $ed->where('edition_id', $edition->id_edition)
                    ->where(function (Builder $q) use ($currentTime, $drawnStartStr): void {
                        $q->where('playing_start_time', '<=', $currentTime)
                            ->orWhere('playing_start_time', '=', $drawnStartStr);
                    });
            })
            ->count();

        $remaining = max(1, $sumOfPrizeQuantity - $nonRejectedCount);

        $editionDate = optional($edition->date)?->format('Y-m-d') ?? (string) $edition->getRawOriginal('date');
        $currentClock = PlayingWindow::normalizeStoredTime($currentTime);

        $endingEnds = [];
        $editionDivisions = EditionDivision::query()
            ->where('edition_id', $edition->id_edition)
            ->get(['playing_start_time', 'playing_end_time']);

        foreach ($editionDivisions as $ed) {
            $start = PlayingWindow::toClockString($ed->getAttributes()['playing_start_time'] ?? null);
            $end = PlayingWindow::toClockString($ed->getAttributes()['playing_end_time'] ?? null);
            if ($start === null || $end === null) {
                continue;
            }

            $include = PlayingWindow::isActiveAt($start, $end, $currentClock, $editionDate)
                || $start === $drawnStartStr;
            if (! $include) {
                continue;
            }

            $resolved = PlayingWindow::resolveInterval($start, $end, $editionDate);
            $endingEnds[] = $resolved['end'];
        }

        if ($endingEnds === []) {
            return time() + 10;
        }

        $adjustedNow = max(
            Carbon::parse($editionDate . ' ' . $drawnStartStr)->addMinutes(20)->getTimestamp(),
            time()
        );

        $soonestEnd = null;
        foreach ($endingEnds as $endDt) {
            if ($soonestEnd === null || $endDt < $soonestEnd) {
                $soonestEnd = $endDt;
            }
        }
        if ($soonestEnd === null) {
            return time() + 10;
        }

        $intervalEnding = max(time(), $soonestEnd->getTimestamp() - (30 * 60));
        $secondsLeft = $intervalEnding - $adjustedNow;

        return $adjustedNow + (int) max(15, round($secondsLeft / $remaining));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLucky(EditionLotteryLucky $lucky, Edition $edition, ?Edition $nextEdition): array
    {
        $clubId = $edition->location?->club_id;
        $prize = $this->resolvePrizePayload($lucky->lotteryRule?->lottery?->prize, $clubId !== null ? (int) $clubId : null, $nextEdition);
        $division = $lucky->drawnParticipation?->editionDivision;
        $acceptedForLottery = EditionLotteryLucky::query()
            ->whereHas('lotteryRule', fn (Builder $q) => $q->where('lottery_id', $lucky->lotteryRule?->lottery_id))
            ->whereNotNull('accepted_at')
            ->count();

        $player = $lucky->drawnParticipation?->player;
        $avatarUrl = null;
        if ($player !== null) {
            $collecting = (bool) ($lucky->drawnParticipation?->is_collecting_points ?? false);
            $cached = CachedPdfPlayerImage::query()
                ->where('player_id', (int) $player->id_player)
                ->where('player_is_collecting_points', $collecting)
                ->first();
            $avatarUrl = $cached?->url;
        }

        return [
            'id_lucky' => $lucky->id_lucky,
            'lottery_rule_id' => $lucky->lottery_rule_id,
            'drawn_at' => $lucky->drawn_at,
            'available_at' => $lucky->available_at,
            'announced_at' => $lucky->announced_at,
            'accepted_at' => $lucky->accepted_at,
            'rejected_at' => $lucky->rejected_at,
            'seconds_left' => max(0, (int) $lucky->available_at - time()),
            'prize_index' => 1 + $acceptedForLottery,
            'prize_quantity' => (int) ($lucky->lotteryRule?->lottery?->prize_quantity ?? 0),
            'player' => [
                'id_player' => $lucky->drawnParticipation?->player_id,
                'name' => $player?->name,
                'avatar_url' => $avatarUrl,
            ],
            'division' => [
                'edition_division_id' => $division?->id_edition_division,
                'division_title' => $division?->division_title,
                'color' => $division?->division?->color,
                'court' => $division?->editionDivisionCourts?->sortBy('display_order')->first()?->court?->name,
            ],
            'prize' => $prize,
        ];
    }

    /**
     * @return null|array<string, mixed>
     */
    private function resolvePrizePayload(?Prize $prize, ?int $clubId, ?Edition $nextEdition): ?array
    {
        if ($prize === null) {
            return null;
        }

        $clubOverride = null;
        if ($clubId !== null) {
            $clubOverride = PrizeFromClub::query()
                ->where('prize_id', $prize->id_prize)
                ->where(function (Builder $q) use ($clubId): void {
                    $q->whereNull('club_id')->orWhere('club_id', $clubId);
                })
                ->orderByRaw('club_id IS NULL ASC')
                ->first();
        }

        $mediaPath = $clubOverride?->media_path ?: $prize->media_path;
        $mediaUrl = null;
        if ($mediaPath) {
            $disk = $prize->storageDisk();
            $mediaUrl = Storage::disk($disk)->url($mediaPath);
        }

        $box1 = LotteryHtmlHelper::replaceEditionNextPlaceholders(
            $clubOverride?->box_1_text ?? $prize->box_1_text,
            $nextEdition
        );
        $box2 = LotteryHtmlHelper::replaceEditionNextPlaceholders(
            $clubOverride?->box_2_text ?? $prize->box_2_text,
            $nextEdition
        );

        return [
            'id_prize' => $prize->id_prize,
            'name' => $prize->name,
            'media_path' => $mediaPath,
            'media_url' => $mediaUrl,
            'is_video' => LotteryHtmlHelper::isPrizeVideo($mediaPath),
            'template' => $clubOverride?->template ?? $prize->template,
            'box_1_text' => $box1,
            'box_1_bg_color' => $clubOverride?->box_1_bg_color ?? $prize->box_1_bg_color,
            'box_1_text_color' => $clubOverride?->box_1_text_color ?? $prize->box_1_text_color,
            'box_2_text' => $box2,
            'box_2_bg_color' => $clubOverride?->box_2_bg_color ?? $prize->box_2_bg_color,
            'box_2_text_color' => $clubOverride?->box_2_text_color ?? $prize->box_2_text_color,
        ];
    }

    private function luckyStatus(EditionLotteryLucky $lucky): string
    {
        if ($lucky->accepted_at !== null) {
            return 'accepted';
        }
        if ($lucky->rejected_at !== null) {
            return 'rejected';
        }

        return 'pending';
    }

    /**
     * Group key for prizes board: midnight-equivalent ends (24:00 and overnight 00:00) share a bucket.
     */
    private function prizesBoardIntervalKey(mixed $start, mixed $end): string
    {
        $startClock = PlayingWindow::toClockString($start) ?? '';
        $endClock = $this->normalizePrizesBoardEndClock($start, $end);

        return $startClock . '|' . $endClock;
    }

    private function normalizePrizesBoardEndClock(mixed $start, mixed $end): string
    {
        $startClock = PlayingWindow::toClockString($start);
        $endClock = PlayingWindow::toClockString($end);
        if ($endClock === null) {
            return '';
        }

        if (preg_match('/^24:00(?::00)?$/', $endClock) === 1) {
            return '24:00:00';
        }

        // Overnight end at wall-clock midnight ≡ end-of-day 24:00 for the same start.
        if (
            $endClock === '00:00:00'
            && $startClock !== null
            && $startClock !== '00:00:00'
        ) {
            return '24:00:00';
        }

        return $endClock;
    }

    private function formatIntervalLabelFromKey(string $key): string
    {
        [$start, $end] = array_pad(explode('|', $key, 2), 2, '');

        return $this->formatIntervalLabel($start !== '' ? $start : null, $end !== '' ? $end : null);
    }

    private function formatIntervalLabel(mixed $start, mixed $end): string
    {
        $startClock = PlayingWindow::toClockString($start);
        $endClock = $this->normalizePrizesBoardEndClock($start, $end);
        if ($endClock === '') {
            $endClock = PlayingWindow::toClockString($end);
        }
        $startLabel = $startClock !== null ? substr($startClock, 0, 5) : '?';
        $endLabel = $endClock !== null && $endClock !== '' ? substr($endClock, 0, 5) : '?';

        return $startLabel . ' – ' . $endLabel;
    }
}
