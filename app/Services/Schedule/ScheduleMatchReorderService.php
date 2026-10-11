<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\GameMatch;
use App\Services\EventCalendarTiming;
use App\Services\Pdf\EventDivisionPosterInvalidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reorders complete REGULAR rounds across all courts, then recomputes clocks.
 */
final class ScheduleMatchReorderService
{
    public function __construct(
        private readonly EventDivisionPosterInvalidator $posterInvalidator = new EventDivisionPosterInvalidator(),
    ) {
    }

    /**
     * @param  list<int>  $matchIds  Complete-round REGULAR ids on the reference court, in new order
     * @return array{matches: Collection<int, GameMatch>}
     */
    public function reorder(
        EventDivision $eventDivision,
        int $eventDivisionCourtId,
        array $matchIds,
    ): array {
        return DB::transaction(function () use ($eventDivision, $eventDivisionCourtId, $matchIds): array {
            $courts = EventDivisionCourt::query()
                ->where('event_division_id', $eventDivision->id_event_division)
                ->orderBy('display_order')
                ->orderBy('id_event_division_court')
                ->get()
                ->values();

            if ($courts->isEmpty()) {
                throw new InvalidArgumentException('PadelEvent division has no assigned courts.');
            }

            $referenceCourt = $courts->firstWhere('id_event_division_court', $eventDivisionCourtId);
            if ($referenceCourt === null) {
                throw new InvalidArgumentException('Court does not belong to this edition division.');
            }

            $byCourt = [];
            foreach ($courts as $court) {
                $byCourt[(int) $court->id_event_division_court] = GameMatch::query()
                    ->where('event_division_id', $eventDivision->id_event_division)
                    ->where('event_division_court_id', $court->id_event_division_court)
                    ->where('match_kind', 'REGULAR')
                    ->orderBy('sequence_no')
                    ->get()
                    ->values();
            }

            $completeCount = $this->completeRoundCount($byCourt);
            if ($completeCount < 1) {
                throw new InvalidArgumentException('No complete rounds available to reorder.');
            }

            $refId = (int) $referenceCourt->id_event_division_court;
            $refComplete = $byCourt[$refId]->filter(
                static fn (GameMatch $m): bool => (int) $m->sequence_no >= 1 && (int) $m->sequence_no <= $completeCount
            )->values();

            $expectedIds = $refComplete->pluck('id_match')->map(static fn ($id): int => (int) $id)->all();
            $incomingIds = array_values(array_map('intval', $matchIds));

            if (count($incomingIds) !== count($expectedIds)
                || count(array_unique($incomingIds)) !== count($incomingIds)
                || array_diff($incomingIds, $expectedIds) !== []
                || array_diff($expectedIds, $incomingIds) !== []) {
                throw new InvalidArgumentException(
                    'match_ids must be a permutation of the complete-round REGULAR matches on the reference court.'
                );
            }

            $sparseOnRef = $byCourt[$refId]->filter(
                static fn (GameMatch $m): bool => (int) $m->sequence_no > $completeCount
            );
            foreach ($sparseOnRef as $sparse) {
                if (in_array((int) $sparse->id_match, $incomingIds, true)) {
                    throw new InvalidArgumentException('Sparse-round matches cannot be reordered.');
                }
            }

            $idToOldIndex = [];
            foreach ($refComplete as $index => $match) {
                $idToOldIndex[(int) $match->id_match] = $index;
            }
            $newPositionOfOldIndex = [];
            foreach ($incomingIds as $newPos => $matchId) {
                $newPositionOfOldIndex[$idToOldIndex[$matchId]] = $newPos;
            }

            $allMatches = GameMatch::query()
                ->where('event_division_id', $eventDivision->id_event_division)
                ->where('match_kind', 'REGULAR')
                ->get();

            $tempBase = 10_000;
            foreach ($allMatches as $i => $match) {
                $match->sequence_no = $tempBase + $i;
                $match->save();
            }

            foreach ($courts as $court) {
                $courtId = (int) $court->id_event_division_court;
                $courtMatches = $byCourt[$courtId];
                $complete = $courtMatches->filter(
                    static fn (GameMatch $m): bool => (int) $m->sequence_no >= 1 && (int) $m->sequence_no <= $completeCount
                )->sortBy('sequence_no')->values();

                // Reload after temp rewrite — use original collection order by old sequence
                $completeOrdered = $complete->values();
                $reordered = [];
                for ($newPos = 0; $newPos < $completeCount; $newPos++) {
                    $oldIndex = array_search($newPos, $newPositionOfOldIndex, true);
                    if ($oldIndex === false) {
                        throw new InvalidArgumentException('Invalid reorder permutation.');
                    }
                    $reordered[$newPos] = $completeOrdered[$oldIndex];
                }

                foreach ($reordered as $newPos => $match) {
                    $fresh = GameMatch::query()->findOrFail($match->id_match);
                    $fresh->sequence_no = $newPos + 1;
                    $fresh->save();
                }

                $sparse = $courtMatches->filter(
                    static fn (GameMatch $m): bool => (int) $m->sequence_no > $completeCount
                )->sortBy('sequence_no')->values();

                foreach ($sparse as $offset => $match) {
                    $fresh = GameMatch::query()->findOrFail($match->id_match);
                    $fresh->sequence_no = $completeCount + $offset + 1;
                    $fresh->save();
                }
            }

            $roundsTotal = 0;
            foreach ($byCourt as $courtMatches) {
                $roundsTotal = max($roundsTotal, $courtMatches->count());
            }

            $schedule = $eventDivision->requireSchedule();

            $slots = RoundClockCalculator::slots(
                EventCalendarTiming::formatHi((int) $eventDivision->playing_starts_at),
                EventCalendarTiming::formatHi((int) $eventDivision->playing_ends_at),
                $roundsTotal,
                (bool) $schedule->include_final,
                (bool) $schedule->has_demonstrative_match,
            );

            $regulars = GameMatch::query()
                ->where('event_division_id', $eventDivision->id_event_division)
                ->where('match_kind', 'REGULAR')
                ->orderBy('sequence_no')
                ->get();

            foreach ($regulars as $match) {
                $seq = (int) $match->sequence_no;
                $slot = $slots[$seq - 1] ?? null;
                if ($slot === null) {
                    continue;
                }
                $match->scheduled_start = $slot['start'] . ':00';
                $match->scheduled_end = $slot['end'] . ':00';
                $match->save();
            }

            $this->posterInvalidator->clearMatches($eventDivision);

            $matches = GameMatch::query()
                ->where('event_division_id', $eventDivision->id_event_division)
                ->where('match_kind', 'REGULAR')
                ->orderBy('sequence_no')
                ->with([
                    'eventDivisionCourt.court',
                    'sides.players.player',
                    'sides.players.participation.player',
                ])
                ->get();

            return ['matches' => $matches];
        });
    }

    /**
     * @param  array<int, Collection<int, GameMatch>>  $byCourt
     */
    private function completeRoundCount(array $byCourt): int
    {
        if ($byCourt === []) {
            return 0;
        }

        $min = null;
        foreach ($byCourt as $courtMatches) {
            $count = $courtMatches->count();
            $min = $min === null ? $count : min($min, $count);
        }

        return (int) $min;
    }
}
