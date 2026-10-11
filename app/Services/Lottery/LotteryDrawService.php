<?php

declare(strict_types=1);

namespace App\Services\Lottery;

use App\Models\CachedPdfPlayerImage;
use App\Models\EventDivision;
use App\Models\EventDivisionReplacement;
use App\Models\EventLottery;
use App\Models\EventLotteryLucky;
use App\Models\EventLotteryPrize;
use App\Models\EventLotteryRule;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
use App\Models\SponsorPrize;
use App\Services\EventCalendarTiming;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class LotteryDrawService
{
    /**
     * Eligible participations for a draw within an edition.
     *
     * Already-drawn is per lottery when $lotteryId is set.
     *
     * When $playingStartsAtGate is set, a participation is eligible if the event's
     * Bucharest start day is today-or-past, or its division playing_starts_at ≤ gate.
     *
     * @return Builder<EventParticipation>
     */
    public function eligibleParticipationsQuery(int $editionId, ?int $preferEventDivisionId = null, ?int $playingStartsAtGate = null, ?int $lotteryId = null): Builder
    {
        $replacedParticipationIds = EventDivisionReplacement::query()
            ->whereNull('match_id')
            ->whereIn('event_division_id', function ($q) use ($editionId): void {
                $q->select('id_event_division')
                    ->from('event_divisions')
                    ->where('event_id', $editionId);
            })
            ->pluck('original_participation_id')
            ->all();

        $drawnParticipationIds = [];
        if ($lotteryId !== null) {
            $drawnParticipationIds = EventLotteryLucky::query()
                ->whereIn('lottery_rule_id', function ($q) use ($lotteryId): void {
                    $q->select('id_lottery_rule')
                        ->from('event_lottery_rules')
                        ->whereIn('lottery_prize_id', function ($q2) use ($lotteryId): void {
                            $q2->select('id_lottery_prize')
                                ->from('event_lottery_prizes')
                                ->where('lottery_id', $lotteryId);
                        });
                })
                ->pluck('drawn_participation_id')
                ->all();
        }

        $query = EventParticipation::query()
            ->where('event_participations.event_id', $editionId)
            ->whereNull('absence_reason')
            ->when($replacedParticipationIds !== [], fn (Builder $q) => $q->whereNotIn('id_participation', $replacedParticipationIds))
            ->when($drawnParticipationIds !== [], fn (Builder $q) => $q->whereNotIn('id_participation', $drawnParticipationIds));

        if ($playingStartsAtGate !== null) {
            $tomorrowStart = EventCalendarTiming::endExclusiveUnix(EventCalendarTiming::todayYmd());
            $query->where(function (Builder $q) use ($playingStartsAtGate, $tomorrowStart): void {
                $q->whereExists(function ($sub) use ($tomorrowStart): void {
                    $sub->select(DB::raw(1))
                        ->from('events')
                        ->whereColumn('events.id_event', 'event_participations.event_id')
                        ->where('events.event_starts_at', '<', $tomorrowStart);
                })->orWhereExists(function ($sub) use ($playingStartsAtGate): void {
                    $sub->select(DB::raw(1))
                        ->from('event_divisions')
                        ->whereColumn('event_divisions.id_event_division', 'event_participations.event_division_id')
                        ->where('event_divisions.playing_starts_at', '<=', $playingStartsAtGate);
                });
            });
        }

        if ($preferEventDivisionId !== null) {
            $query->orderByRaw('(event_division_id = ?) DESC', [$preferEventDivisionId]);
        }

        return $query->inRandomOrder();
    }

    /**
     * @param  Collection<int, EventParticipation>  $participations
     * @param  list<int>  $alreadyDrawnParticipationIds
     * @param  list<int>  $divisionWideReplacedParticipationIds
     * @return Collection<int, EventParticipation>
     */
    public function filterEligibleParticipations(
        Collection $participations,
        array $alreadyDrawnParticipationIds = [],
        array $divisionWideReplacedParticipationIds = [],
        ?int $preferEventDivisionId = null,
    ): Collection {
        $drawn = array_fill_keys($alreadyDrawnParticipationIds, true);
        $replaced = array_fill_keys($divisionWideReplacedParticipationIds, true);

        $filtered = $participations
            ->filter(function (EventParticipation $p) use ($drawn, $replaced): bool {
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

        if ($preferEventDivisionId !== null) {
            $filtered = $filtered->sortByDesc(
                fn (EventParticipation $p) => (int) $p->event_division_id === (int) $preferEventDivisionId ? 1 : 0
            )->values();
        }

        return $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    public function liveState(EventLottery $lottery): array
    {
        $event = $lottery->event()->with('location')->firstOrFail();
        $nextEdition = $this->nextEdition($event);

        $pending = $this->currentPendingLucky($lottery);

        if ($pending === null) {
            $luckiesCount = $this->lotteryLuckiesCount($lottery);
            if ($luckiesCount === 0) {
                $pending = $this->createFirstDraw($lottery);
            }
        }

        if ($pending !== null) {
            $pending->loadMissing([
                'drawnParticipation.player',
                'drawnParticipation.eventDivision.division',
                'drawnParticipation.eventDivision.eventDivisionCourts.court',
                'lotteryRule.lotteryPrize.prize',
                'lotteryRule.lotteryPrize.sponsor',
                'lotteryRule.eventDivision',
            ]);

            return [
                'status' => 'pending',
                ...$this->lotteryBrandingUrls(),
                'event' => $this->serializeEditionSummary($event),
                'next_edition' => $nextEdition ? $this->serializeEditionSummary($nextEdition) : null,
                'lucky' => $this->serializeLucky($pending, $event, $nextEdition),
                'accepted' => [],
            ];
        }

        $accepted = $this->luckiesForLotteryQuery($lottery)
            ->whereNotNull('accepted_at')
            ->whereNull('rejected_at')
            ->with(['drawnParticipation.player', 'drawnParticipation.eventDivision.division', 'lotteryRule.lotteryPrize.prize', 'lotteryRule.lotteryPrize.sponsor'])
            ->orderBy('id_lucky')
            ->get()
            ->map(fn (EventLotteryLucky $lucky) => $this->serializeLucky($lucky, $event, $nextEdition))
            ->all();

        return [
            'status' => 'finished',
            ...$this->lotteryBrandingUrls(),
            'event' => $this->serializeEditionSummary($event),
            'next_edition' => $nextEdition ? $this->serializeEditionSummary($nextEdition) : null,
            'lucky' => null,
            'accepted' => $accepted,
        ];
    }

    public function drawNext(EventLottery $lottery): EventLotteryLucky
    {
        $pending = $this->currentPendingLucky($lottery);
        if ($pending !== null) {
            return $pending;
        }

        if ($this->lotteryLuckiesCount($lottery) === 0) {
            return $this->createFirstDraw($lottery);
        }

        throw new RuntimeException('No pending draw and lottery already started; accept or reject the previous lucky first.');
    }

    public function announce(EventLotteryLucky $lucky): EventLotteryLucky
    {
        if ($lucky->announced_at === null) {
            $lucky->announced_at = time();
            $lucky->save();
        }

        return $lucky->fresh();
    }

    public function reject(EventLotteryLucky $lucky, PadelEvent $event): EventLotteryLucky
    {
        return DB::transaction(function () use ($lucky, $event): EventLotteryLucky {
            $lucky->rejected_at = time();
            $lucky->save();

            $rule = $lucky->lotteryRule()->with('lotteryPrize')->first();
            $lotteryId = $rule?->lotteryPrize?->lottery_id;
            $preferDivisionId = $rule?->event_division_id;
            $gateUnix = time();
            if ($preferDivisionId) {
                $divisionStart = EventDivision::query()->whereKey($preferDivisionId)->value('playing_starts_at');
                $gateUnix = $divisionStart !== null ? (int) $divisionStart : time();
            }

            $participation = $this->eligibleParticipationsQuery(
                (int) $event->id_event,
                $preferDivisionId !== null ? (int) $preferDivisionId : null,
                $gateUnix,
                $lotteryId !== null ? (int) $lotteryId : null,
            )->first();

            if ($participation === null) {
                return $lucky->fresh();
            }

            return EventLotteryLucky::query()->create([
                'lottery_rule_id' => $lucky->lottery_rule_id,
                'drawn_participation_id' => $participation->id_participation,
                'drawn_at' => time(),
                'available_at' => time() + 10,
            ]);
        });
    }

    public function accept(EventLotteryLucky $lucky, PadelEvent $event): ?EventLotteryLucky
    {
        return DB::transaction(function () use ($lucky, $event): ?EventLotteryLucky {
            $lucky->accepted_at = time();
            $lucky->save();

            $lucky->loadMissing('lotteryRule.lotteryPrize');
            $lotteryId = (int) ($lucky->lotteryRule?->lotteryPrize?->lottery_id ?? 0);
            $lottery = EventLottery::query()->find($lotteryId);
            if ($lottery === null) {
                return null;
            }

            $rules = $this->sortedRulesForLottery($lottery);

            foreach ($rules as $rule) {
                $acceptedCount = EventLotteryLucky::query()
                    ->where('lottery_rule_id', $rule->id_lottery_rule)
                    ->whereNotNull('accepted_at')
                    ->count();

                if ($acceptedCount >= (int) $rule->rule_prize_quantity) {
                    continue;
                }

                $existing = EventLotteryLucky::query()
                    ->where('lottery_rule_id', $rule->id_lottery_rule)
                    ->whereNull('accepted_at')
                    ->whereNull('rejected_at')
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                $now = time();
                // Match reject(): when the rule targets a division, gate with that
                // division's playing_starts_at so upcoming editions stay drawable
                // after the first accept (createFirstDraw uses a null gate).
                $gateUnix = $now;
                if ($rule->event_division_id !== null) {
                    $divisionStart = $rule->eventDivision?->playing_starts_at;
                    $gateUnix = $divisionStart !== null ? (int) $divisionStart : $now;
                }

                $participation = $this->eligibleParticipationsQuery(
                    (int) $event->id_event,
                    $rule->event_division_id !== null ? (int) $rule->event_division_id : null,
                    $gateUnix,
                    (int) $lottery->id_lottery,
                )->with('eventDivision')->first();

                if ($participation === null) {
                    continue;
                }

                $availableAt = $this->computeNextAvailableAt($event, $participation, $now, $lottery);

                return EventLotteryLucky::query()->create([
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
    public function prizesBoard(EventLottery $lottery): array
    {
        $event = $lottery->event()->firstOrFail();
        $nextEdition = $this->nextEdition($event);

        $rules = $this->sortedRulesForLottery($lottery);

        $grouped = [];
        foreach ($rules as $rule) {
            $start = $rule->eventDivision?->playing_starts_at;
            $end = $rule->eventDivision?->playing_ends_at;
            $key = $rule->event_division_id === null
                ? '__unscheduled__'
                : $this->prizesBoardIntervalKey(
                    $start !== null ? (int) $start : null,
                    $end !== null ? (int) $end : null,
                );
            $grouped[$key][] = $rule;
        }

        uksort($grouped, static function (string $a, string $b): int {
            if ($a === '__unscheduled__') {
                return 1;
            }
            if ($b === '__unscheduled__') {
                return -1;
            }

            return ((int) explode('|', $a)[0]) <=> ((int) explode('|', $b)[0]);
        });

        $intervals = [];
        foreach ($grouped as $key => $intervalRules) {
            /** @var list<EventLotteryRule> $intervalRules */
            $byPrizeLine = [];
            foreach ($intervalRules as $rule) {
                $byPrizeLine[(int) $rule->lottery_prize_id][] = $rule;
            }

            $prizes = [];
            foreach ($byPrizeLine as $prizeLineRules) {
                usort($prizeLineRules, static fn (EventLotteryRule $a, EventLotteryRule $b): int => [
                    (int) $a->orderliness, (int) $a->id_lottery_rule,
                ] <=> [(int) $b->orderliness, (int) $b->id_lottery_rule]);

                $head = $prizeLineRules[0];
                $qty = array_sum(array_map(fn (EventLotteryRule $r) => (int) $r->rule_prize_quantity, $prizeLineRules));
                $prizePayload = $this->resolvePrizePayload($head->lotteryPrize, $nextEdition);

                $luckies = [];
                foreach ($prizeLineRules as $rule) {
                    foreach ($rule->luckies as $lucky) {
                        $luckies[] = [
                            'id_lucky' => $lucky->id_lucky,
                            'status' => $this->luckyStatus($lucky),
                            'player_name' => $lucky->drawnParticipation?->player?->name,
                            'division_title' => $lucky->drawnParticipation?->eventDivision?->division_title,
                            'division_color' => $lucky->drawnParticipation?->eventDivision?->division?->color,
                        ];
                    }
                }

                $prizes[] = [
                    'id_lottery_prize' => $head->lottery_prize_id,
                    'id_lottery' => $head->lotteryPrize?->lottery_id,
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

    private function createFirstDraw(EventLottery $lottery): EventLotteryLucky
    {
        $event = $lottery->event()->firstOrFail();
        $firstRule = $this->sortedRulesForLottery($lottery)->first();

        if ($firstRule === null) {
            throw new RuntimeException('No lottery rules found for this lottery.');
        }

        $participation = $this->eligibleParticipationsQuery(
            (int) $event->id_event,
            $firstRule->event_division_id !== null ? (int) $firstRule->event_division_id : null,
            null,
            (int) $lottery->id_lottery,
        )->first();

        if ($participation === null) {
            throw new RuntimeException('No eligible participations for this edition.');
        }

        $start = $firstRule->eventDivision?->playing_starts_at;
        $target = $start !== null
            ? (int) $start + (20 * 60)
            : time() + 10;

        return EventLotteryLucky::query()->create([
            'lottery_rule_id' => $firstRule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
            'drawn_at' => time(),
            'available_at' => max(time() + 10, $target),
        ]);
    }

    private function currentPendingLucky(EventLottery $lottery): ?EventLotteryLucky
    {
        return $this->luckiesForLotteryQuery($lottery)
            ->whereNull('accepted_at')
            ->whereNull('rejected_at')
            ->orderByDesc('id_lucky')
            ->first();
    }

    private function lotteryLuckiesCount(EventLottery $lottery): int
    {
        return $this->luckiesForLotteryQuery($lottery)->count();
    }

    /**
     * @return Builder<EventLotteryLucky>
     */
    private function luckiesForLotteryQuery(EventLottery $lottery): Builder
    {
        return EventLotteryLucky::query()
            ->whereHas('lotteryRule.lotteryPrize', fn (Builder $q) => $q->where('lottery_id', $lottery->id_lottery));
    }

    /**
     * Next edition after $current: Upcoming (Bucharest day of event_starts_at > today)
     * and strictly later than $current. Never returns $current itself.
     */
    private function nextEdition(PadelEvent $current): ?PadelEvent
    {
        $upcomingFrom = EventCalendarTiming::endExclusiveUnix(EventCalendarTiming::todayYmd());

        return PadelEvent::query()
            ->where('id_event', '!=', $current->id_event)
            ->where('event_starts_at', '>=', $upcomingFrom)
            ->where('event_starts_at', '>', (int) $current->event_starts_at)
            ->orderBy('event_starts_at')
            ->first();
    }

    /**
     * @return array{id_event: int, name: mixed, date: string}
     */
    private function serializeEditionSummary(PadelEvent $event): array
    {
        return [
            'id_event' => $event->id_event,
            'name' => $event->name,
            'date' => EventCalendarTiming::dayYmd((int) $event->event_starts_at),
        ];
    }

    /**
     * @return Collection<int, EventLotteryRule>
     */
    private function sortedRulesForLottery(EventLottery $lottery): Collection
    {
        $rules = EventLotteryRule::query()
            ->whereHas('lotteryPrize', fn (Builder $q) => $q->where('lottery_id', $lottery->id_lottery))
            ->with([
                'lotteryPrize.prize',
                'lotteryPrize.sponsor',
                'eventDivision',
                'luckies.drawnParticipation.player',
                'luckies.drawnParticipation.eventDivision.division',
            ])
            ->get();

        return $rules->sortBy(function (EventLotteryRule $rule): array {
            $start = $rule->eventDivision?->playing_starts_at;

            return [
                $start === null ? 1 : 0,
                (int) ($start ?? 0),
                (int) $rule->orderliness,
                (int) $rule->id_lottery_rule,
            ];
        })->values();
    }

    private function computeNextAvailableAt(
        PadelEvent $event,
        EventParticipation $participation,
        ?int $now = null,
        ?EventLottery $lottery = null,
    ): int {
        $now = $now ?? time();
        $participation->loadMissing('eventDivision');
        $drawnStart = $participation->eventDivision?->playing_starts_at;
        $drawnStartUnix = $drawnStart !== null ? (int) $drawnStart : $now;

        $rulesQuery = EventLotteryRule::query()
            ->when(
                $lottery !== null,
                fn (Builder $q) => $q->whereHas('lotteryPrize', fn (Builder $lp) => $lp->where('lottery_id', $lottery->id_lottery)),
                fn (Builder $q) => $q->whereHas('lotteryPrize.lottery', fn (Builder $l) => $l->where('event_id', $event->id_event)),
            )
            ->where(function (Builder $q) use ($now, $drawnStartUnix): void {
                $q->whereNull('event_division_id')
                    ->orWhereHas('eventDivision', function (Builder $ed) use ($now, $drawnStartUnix): void {
                        $ed->where('playing_starts_at', '<=', $now)
                            ->orWhere('playing_starts_at', '=', $drawnStartUnix);
                    });
            });

        $sumOfPrizeQuantity = (int) $rulesQuery->sum('rule_prize_quantity');

        $luckiesQuery = EventLotteryLucky::query()
            ->when(
                $lottery !== null,
                fn (Builder $q) => $q->whereHas('lotteryRule.lotteryPrize', fn (Builder $lp) => $lp->where('lottery_id', $lottery->id_lottery)),
                fn (Builder $q) => $q->whereHas('lotteryRule.lotteryPrize.lottery', fn (Builder $l) => $l->where('event_id', $event->id_event)),
            )
            ->whereNull('rejected_at')
            ->whereHas('lotteryRule.eventDivision', function (Builder $ed) use ($now, $drawnStartUnix, $event): void {
                $ed->where('event_id', $event->id_event)
                    ->where(function (Builder $q) use ($now, $drawnStartUnix): void {
                        $q->where('playing_starts_at', '<=', $now)
                            ->orWhere('playing_starts_at', '=', $drawnStartUnix);
                    });
            });

        $nonRejectedCount = $luckiesQuery->count();

        $remaining = max(1, $sumOfPrizeQuantity - $nonRejectedCount);

        $endingEnds = [];
        $eventDivisions = EventDivision::query()
            ->where('event_id', $event->id_event)
            ->get(['playing_starts_at', 'playing_ends_at']);

        foreach ($eventDivisions as $ed) {
            $start = (int) $ed->playing_starts_at;
            $end = (int) $ed->playing_ends_at;
            $include = ($now >= $start && $now < $end) || $start === $drawnStartUnix;
            if (! $include) {
                continue;
            }

            $endingEnds[] = $end;
        }

        if ($endingEnds === []) {
            return $now + 10;
        }

        $adjustedNow = max($drawnStartUnix + (20 * 60), $now);

        $soonestEnd = min($endingEnds);
        $intervalEnding = max($now, $soonestEnd - (30 * 60));
        $secondsLeft = $intervalEnding - $adjustedNow;

        return $adjustedNow + (int) max(15, round($secondsLeft / $remaining));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLucky(EventLotteryLucky $lucky, PadelEvent $event, ?PadelEvent $nextEdition): array
    {
        $line = $lucky->lotteryRule?->lotteryPrize;
        $prize = $this->resolvePrizePayload($line, $nextEdition);
        $division = $lucky->drawnParticipation?->eventDivision;
        $acceptedForPrizeLine = EventLotteryLucky::query()
            ->whereHas('lotteryRule', fn (Builder $q) => $q->where('lottery_prize_id', $lucky->lotteryRule?->lottery_prize_id))
            ->whereNotNull('accepted_at')
            ->count();

        $player = $lucky->drawnParticipation?->player;
        $avatarUrl = null;
        if ($player !== null) {
            $collecting = (bool) ($lucky->drawnParticipation?->is_collecting_points ?? false);
            $cached = CachedPdfPlayerImage::query()
                ->where('player_user_id', (int) $player->id_user)
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
            'prize_index' => 1 + $acceptedForPrizeLine,
            'prize_quantity' => (int) ($line?->prize_quantity ?? 0),
            'player' => [
                'id_user' => $lucky->drawnParticipation?->player_user_id,
                'name' => $player?->name,
                'avatar_url' => $avatarUrl,
            ],
            'division' => [
                'event_division_id' => $division?->id_event_division,
                'division_title' => $division?->division_title,
                'color' => $division?->division?->color,
                'court' => $division?->eventDivisionCourts?->sortBy('display_order')->first()?->court?->name,
            ],
            'prize' => $prize,
        ];
    }

    /**
     * @return null|array<string, mixed>
     */
    private function resolvePrizePayload(?EventLotteryPrize $line, ?PadelEvent $nextEdition): ?array
    {
        $prize = $line?->prize;
        if ($line === null || $prize === null) {
            return null;
        }

        $overlay = null;
        if ($line->sponsor_id !== null) {
            $overlay = SponsorPrize::query()
                ->where('sponsor_id', $line->sponsor_id)
                ->where('prize_id', $prize->id_prize)
                ->first();
        }

        $mediaPath = $this->overlayOrCatalog($overlay?->media_path, $prize->media_path);
        $mediaUrl = null;
        if ($mediaPath) {
            $disk = $prize->storageDisk();
            $mediaUrl = Storage::disk($disk)->url($mediaPath);
        }

        $box1 = LotteryHtmlHelper::replaceEditionNextPlaceholders(
            $this->overlayOrCatalog($overlay?->box_1_text, $prize->box_1_text),
            $nextEdition
        );
        $box2 = LotteryHtmlHelper::replaceEditionNextPlaceholders(
            $this->overlayOrCatalog($overlay?->box_2_text, $prize->box_2_text),
            $nextEdition
        );

        $payload = [
            'id_prize' => $prize->id_prize,
            'name' => $prize->name,
            'media_path' => $mediaPath,
            'media_url' => $mediaUrl,
            'is_video' => LotteryHtmlHelper::isPrizeVideo($mediaPath),
            'template' => $this->overlayOrCatalog($overlay?->template, $prize->template),
            'box_1_text' => $box1,
            'box_1_bg_color' => $this->overlayOrCatalog($overlay?->box_1_bg_color, $prize->box_1_bg_color),
            'box_1_text_color' => $this->overlayOrCatalog($overlay?->box_1_text_color, $prize->box_1_text_color),
            'box_2_text' => $box2,
            'box_2_bg_color' => $this->overlayOrCatalog($overlay?->box_2_bg_color, $prize->box_2_bg_color),
            'box_2_text_color' => $this->overlayOrCatalog($overlay?->box_2_text_color, $prize->box_2_text_color),
        ];

        if ($line->sponsor_id !== null) {
            $sponsor = $line->sponsor;
            if ($sponsor !== null) {
                $payload['sponsor'] = [
                    'id_sponsor' => $sponsor->id_sponsor,
                    'name' => $sponsor->name,
                ];
            }
        }

        return $payload;
    }

    private function overlayOrCatalog(mixed $overlayValue, mixed $catalogValue): mixed
    {
        if ($overlayValue === null || $overlayValue === '') {
            return $catalogValue;
        }

        return $overlayValue;
    }

    private function luckyStatus(EventLotteryLucky $lucky): string
    {
        if ($lucky->accepted_at !== null) {
            return 'accepted';
        }
        if ($lucky->rejected_at !== null) {
            return 'rejected';
        }

        return 'pending';
    }

    private function prizesBoardIntervalKey(?int $start, ?int $end): string
    {
        return (string) ($start ?? 0) . '|' . (string) ($end ?? 0);
    }

    private function formatIntervalLabelFromKey(string $key): string
    {
        [$start, $end] = array_pad(explode('|', $key, 2), 2, '0');

        return $this->formatIntervalLabel(
            $start !== '' && $start !== '0' ? (int) $start : null,
            $end !== '' && $end !== '0' ? (int) $end : null,
        );
    }

    private function formatIntervalLabel(?int $start, ?int $end): string
    {
        $startLabel = $start !== null ? EventCalendarTiming::formatHi($start) : '?';
        $endLabel = $end !== null ? EventCalendarTiming::formatHi($end) : '?';

        return $startLabel . ' – ' . $endLabel;
    }
}
