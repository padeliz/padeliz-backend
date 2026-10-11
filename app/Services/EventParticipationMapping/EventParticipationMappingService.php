<?php

declare(strict_types=1);

namespace App\Services\EventParticipationMapping;

use App\Models\EventDivision;
use App\Models\EventParticipation;
use App\Models\EventRegistration;
use App\Models\PadelEvent;
use App\Services\EventParticipationLotteryGuard;
use App\Services\Pdf\EventDivisionPosterInvalidator;
use App\Services\PlayerEventHistoryService;
use App\Services\Schedule\ScheduleMatchPersister;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EventParticipationMappingService
{
    private const WORST_SKILL = 999999.0;

    public function __construct(
        private readonly ScheduleMatchPersister $matchPersister,
        private readonly EventParticipationLotteryGuard $lotteryGuard,
        private readonly PlayerEventHistoryService $playerEditionHistory,
        private readonly EventDivisionPosterInvalidator $posterInvalidator,
    ) {
    }

    /**
     * @return array{
     *     can_map: bool,
     *     disabled_reason: null|string,
     *     existing_participations_count: int,
     *     registered_player_count: int,
     *     division_specific_registration_count: int,
     *     event_wide_registration_count: int
     * }
     */
    public function preview(PadelEvent $event): array
    {
        $event->loadMissing('divisions');
        $counts = $this->registrationCounts($event);
        $disabledReason = $this->disabledReason($event);

        return [
            'can_map' => $disabledReason === null,
            'disabled_reason' => $disabledReason,
            'existing_participations_count' => EventParticipation::query()
                ->where('event_id', $event->id_event)
                ->count(),
            'registered_player_count' => $counts['registered_player_count'],
            'division_specific_registration_count' => $counts['division_specific_registration_count'],
            'event_wide_registration_count' => $counts['event_wide_registration_count'],
        ];
    }

    /**
     * @return array{
     *     created_count: int,
     *     by_edition_division: list<array{event_division_id: int, count: int}>
     * }
     */
    public function map(PadelEvent $event, bool $confirmOverwrite): array
    {
        $preview = $this->preview($event);
        if (! $preview['can_map']) {
            throw new EventParticipationMappingException(
                $this->messageForDisabledReason((string) $preview['disabled_reason']),
                409
            );
        }

        $existingCount = (int) $preview['existing_participations_count'];
        if ($existingCount > 0 && ! $confirmOverwrite) {
            throw new EventParticipationMappingException(
                'Existing participations must be confirmed for overwrite. Pass confirm_overwrite=true.',
                409
            );
        }

        $event->loadMissing('divisions');

        return DB::transaction(function () use ($event) {
            $editionId = (int) $event->id_event;
            $touchedEdIds = EventParticipation::query()
                ->where('event_id', $editionId)
                ->pluck('event_division_id')
                ->all();

            EventParticipation::query()
                ->where('event_id', $editionId)
                ->delete();

            $divisions = $event->divisions
                ->sortBy(fn (EventDivision $ed) => [(int) $ed->skill_order, (int) $ed->id_event_division])
                ->values();

            /** @var array<int, int> $countsByEdId */
            $countsByEdId = [];
            foreach ($divisions as $division) {
                $countsByEdId[(int) $division->id_event_division] = 0;
            }

            $created = 0;
            $placedPlayerIds = [];

            $divisionSpecific = EventRegistration::query()
                ->active()
                ->where('event_id', $editionId)
                ->whereNotNull('event_division_id')
                ->orderBy('id_registration')
                ->get();

            foreach ($divisionSpecific as $registration) {
                $edId = (int) $registration->event_division_id;
                $playerId = (int) $registration->player_user_id;
                $this->createParticipation($editionId, $edId, $playerId, $countsByEdId);
                $placedPlayerIds[$playerId] = true;
                $touchedEdIds[] = $edId;
                $created++;
            }

            $editionWide = EventRegistration::query()
                ->active()
                ->where('event_id', $editionId)
                ->whereNull('event_division_id')
                ->orderBy('id_registration')
                ->get()
                ->filter(fn (EventRegistration $r) => ! isset($placedPlayerIds[(int) $r->player_user_id]))
                ->values();

            if ($editionWide->isNotEmpty()) {
                $playerIds = $editionWide->pluck('player_user_id')->map(fn ($id) => (int) $id)->all();
                $histories = $this->playerEditionHistory->forPlayers(
                    $playerIds,
                    $this->playerEditionHistory->cutoffDateForEdition($event)
                );

                $sortedRegistrations = $editionWide
                    ->sort(function (EventRegistration $a, EventRegistration $b) use ($histories) {
                        $scoreA = $this->skillScore($histories[(int) $a->player_user_id] ?? []);
                        $scoreB = $this->skillScore($histories[(int) $b->player_user_id] ?? []);
                        if ($scoreA !== $scoreB) {
                            return $scoreA <=> $scoreB;
                        }
                        $partA = (int) ($histories[(int) $a->player_user_id]['participated_count'] ?? 0);
                        $partB = (int) ($histories[(int) $b->player_user_id]['participated_count'] ?? 0);
                        if ($partA !== $partB) {
                            return $partB <=> $partA;
                        }

                        return ((int) $a->player_user_id) <=> ((int) $b->player_user_id);
                    })
                    ->values();

                foreach ($sortedRegistrations as $registration) {
                    $playerId = (int) $registration->player_user_id;
                    $history = $histories[$playerId] ?? [];
                    $target = $this->pickEventDivision(
                        $divisions,
                        $countsByEdId,
                        $history['last_played'] ?? null
                    );
                    $edId = (int) $target->id_event_division;
                    $this->createParticipation($editionId, $edId, $playerId, $countsByEdId);
                    $touchedEdIds[] = $edId;
                    $created++;
                }
            }

            foreach (array_unique($touchedEdIds) as $edId) {
                $division = $divisions->firstWhere('id_event_division', $edId);
                if ($division instanceof EventDivision) {
                    $this->posterInvalidator->clearMatchesAndRanking($division);
                }
            }

            $this->posterInvalidator->clearAttendanceForEvent($editionId);

            $byEd = [];
            foreach ($countsByEdId as $edId => $count) {
                if ($count > 0) {
                    $byEd[] = [
                        'event_division_id' => (int) $edId,
                        'count' => (int) $count,
                    ];
                }
            }

            return [
                'created_count' => $created,
                'by_event_division' => $byEd,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $history
     */
    public function skillScore(array $history): float
    {
        $lastPlayed = $history['last_played'] ?? null;
        if (! is_array($lastPlayed)) {
            return self::WORST_SKILL;
        }
        $rank = $lastPlayed['rank'] ?? null;
        if ($rank === null) {
            return self::WORST_SKILL;
        }
        $participantCount = (int) ($lastPlayed['participant_count'] ?? 0);
        if ($participantCount <= 0) {
            return self::WORST_SKILL;
        }

        return (float) $rank / (float) $participantCount;
    }

    /**
     * @param  array<int, int>  $countsByEdId
     */
    private function createParticipation(
        int $editionId,
        int $eventDivisionId,
        int $playerId,
        array &$countsByEdId
    ): void {
        $sortOrder = $countsByEdId[$eventDivisionId] ?? 0;
        EventParticipation::query()->create([
            'event_id' => $editionId,
            'event_division_id' => $eventDivisionId,
            'player_user_id' => $playerId,
            'sort_order' => $sortOrder,
            'is_collecting_points' => true,
        ]);
        $countsByEdId[$eventDivisionId] = $sortOrder + 1;
    }

    /**
     * @param  Collection<int, EventDivision>  $divisions
     * @param  array<int, int>  $countsByEdId
     * @param  null|array{division_id?: int, division_title?: string, rank?: null|int, participant_count?: int}  $lastPlayed
     */
    public function pickEventDivision(
        Collection $divisions,
        array $countsByEdId,
        ?array $lastPlayed
    ): EventDivision {
        $withCapacity = $divisions->filter(function (EventDivision $ed) use ($countsByEdId) {
            $count = $countsByEdId[(int) $ed->id_event_division] ?? 0;

            return $count < (int) $ed->players_limit;
        });

        if ($lastPlayed !== null && isset($lastPlayed['division_id']) && (int) $lastPlayed['division_id'] > 0) {
            $divisionId = (int) $lastPlayed['division_id'];
            $candidates = $divisions->filter(
                fn (EventDivision $ed) => (int) $ed->division_id === $divisionId
            );
            if ($candidates->isNotEmpty()) {
                $pool = $this->preferCapacityWithinCandidates($candidates, $withCapacity);

                return $this->bestTitleThenSkillOrder($pool, (string) ($lastPlayed['division_title'] ?? ''));
            }
        }

        if ($lastPlayed !== null && ($lastPlayed['division_title'] ?? '') !== '') {
            $title = $this->normalizeTitle((string) $lastPlayed['division_title']);
            $candidates = $divisions->filter(
                fn (EventDivision $ed) => $this->normalizeTitle((string) $ed->division_title) === $title
            );
            if ($candidates->isNotEmpty()) {
                $pool = $this->preferCapacityWithinCandidates($candidates, $withCapacity);

                return $this->lowestSkillOrder($pool);
            }
        }

        $pool = $withCapacity->isNotEmpty() ? $withCapacity : $divisions;

        return $this->lowestSkillOrder($pool);
    }

    /**
     * Prefer history-matched candidates that still have capacity. When all matched
     * candidates are full but other edition divisions still have space, fall back
     * to the matched candidates anyway (over-limit placement).
     *
     * @param  Collection<int, EventDivision>  $candidates
     * @param  Collection<int, EventDivision>  $withCapacity
     * @return Collection<int, EventDivision>
     */
    private function preferCapacityWithinCandidates(
        Collection $candidates,
        Collection $withCapacity
    ): Collection {
        if ($withCapacity->isEmpty()) {
            return $candidates;
        }

        $withCapacityAmongCandidates = $this->filterCandidates($candidates, $withCapacity);

        return $withCapacityAmongCandidates->isNotEmpty()
            ? $withCapacityAmongCandidates
            : $candidates;
    }

    /**
     * @param  Collection<int, EventDivision>  $candidates
     * @param  Collection<int, EventDivision>  $allowed
     * @return Collection<int, EventDivision>
     */
    private function filterCandidates(Collection $candidates, Collection $allowed): Collection
    {
        $allowedIds = $allowed->pluck('id_event_division')->all();

        return $candidates->filter(
            fn (EventDivision $ed) => in_array($ed->id_event_division, $allowedIds, true)
        );
    }

    /**
     * @param  Collection<int, EventDivision>  $candidates
     */
    private function bestTitleThenSkillOrder(Collection $candidates, string $targetTitle): EventDivision
    {
        $normalizedTarget = $this->normalizeTitle($targetTitle);
        $exact = $candidates->filter(
            fn (EventDivision $ed) => $this->normalizeTitle((string) $ed->division_title) === $normalizedTarget
        );
        if ($exact->isNotEmpty()) {
            return $this->lowestSkillOrder($exact);
        }

        return $this->lowestSkillOrder($candidates);
    }

    /**
     * @param  Collection<int, EventDivision>  $candidates
     */
    private function lowestSkillOrder(Collection $candidates): EventDivision
    {
        $sorted = $candidates->sortBy(
            fn (EventDivision $ed) => [(int) $ed->skill_order, (int) $ed->id_event_division]
        )->values();

        $first = $sorted->first();
        if (! $first instanceof EventDivision) {
            throw new EventParticipationMappingException('No edition division available for placement.', 422);
        }

        return $first;
    }

    private function normalizeTitle(string $title): string
    {
        return mb_strtolower(trim($title));
    }

    private function disabledReason(PadelEvent $event): ?string
    {
        if ($event->finalized_at !== null) {
            return 'finalized';
        }

        if ($event->divisions->isEmpty()) {
            return 'no_divisions';
        }

        if ($this->lotteryGuard->hasLotteryLuckiesForEdition((int) $event->id_event)) {
            return 'lottery_exists';
        }

        foreach ($event->divisions as $division) {
            if ($this->matchPersister->hasRegularMatches($division)) {
                return 'schedule_exists';
            }
        }

        return null;
    }

    private function messageForDisabledReason(string $reason): string
    {
        return match ($reason) {
            'finalized' => 'PadelEvent is finalized.',
            'no_divisions' => 'PadelEvent has no edition divisions.',
            'lottery_exists' => 'Participations cannot be remapped after lottery draws exist for this edition.',
            'schedule_exists' => 'Participations cannot be remapped after a schedule has been generated.',
            default => 'Participations cannot be mapped for this edition.',
        };
    }

    /**
     * @return array{
     *     registered_player_count: int,
     *     division_specific_registration_count: int,
     *     event_wide_registration_count: int
     * }
     */
    private function registrationCounts(PadelEvent $event): array
    {
        $registrations = EventRegistration::query()
            ->active()
            ->where('event_id', $event->id_event)
            ->get(['player_user_id', 'event_division_id']);

        $playerIds = $registrations->pluck('player_user_id')->unique()->count();
        $divisionSpecific = $registrations->whereNotNull('event_division_id')->count();
        $editionWide = $registrations->whereNull('event_division_id')->count();

        return [
            'registered_player_count' => (int) $playerIds,
            'division_specific_registration_count' => (int) $divisionSpecific,
            'event_wide_registration_count' => (int) $editionWide,
        ];
    }
}
