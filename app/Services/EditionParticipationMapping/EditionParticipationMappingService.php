<?php

declare(strict_types=1);

namespace App\Services\EditionParticipationMapping;

use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use App\Models\EditionRegistration;
use App\Services\EditionParticipationLotteryGuard;
use App\Services\Pdf\EditionDivisionPosterInvalidator;
use App\Services\PlayerEditionHistoryService;
use App\Services\Schedule\ScheduleMatchPersister;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EditionParticipationMappingService
{
    private const WORST_SKILL = 999999.0;

    public function __construct(
        private readonly ScheduleMatchPersister $matchPersister,
        private readonly EditionParticipationLotteryGuard $lotteryGuard,
        private readonly PlayerEditionHistoryService $playerEditionHistory,
        private readonly EditionDivisionPosterInvalidator $posterInvalidator,
    ) {
    }

    /**
     * @return array{
     *     can_map: bool,
     *     disabled_reason: null|string,
     *     existing_participations_count: int,
     *     registered_player_count: int,
     *     division_specific_registration_count: int,
     *     edition_wide_registration_count: int
     * }
     */
    public function preview(Edition $edition): array
    {
        $edition->loadMissing('divisions');
        $counts = $this->registrationCounts($edition);
        $disabledReason = $this->disabledReason($edition);

        return [
            'can_map' => $disabledReason === null,
            'disabled_reason' => $disabledReason,
            'existing_participations_count' => EditionParticipation::query()
                ->where('edition_id', $edition->id_edition)
                ->count(),
            'registered_player_count' => $counts['registered_player_count'],
            'division_specific_registration_count' => $counts['division_specific_registration_count'],
            'edition_wide_registration_count' => $counts['edition_wide_registration_count'],
        ];
    }

    /**
     * @return array{
     *     created_count: int,
     *     by_edition_division: list<array{edition_division_id: int, count: int}>
     * }
     */
    public function map(Edition $edition, bool $confirmOverwrite): array
    {
        $preview = $this->preview($edition);
        if (! $preview['can_map']) {
            throw new EditionParticipationMappingException(
                $this->messageForDisabledReason((string) $preview['disabled_reason']),
                409
            );
        }

        $existingCount = (int) $preview['existing_participations_count'];
        if ($existingCount > 0 && ! $confirmOverwrite) {
            throw new EditionParticipationMappingException(
                'Existing participations must be confirmed for overwrite. Pass confirm_overwrite=true.',
                409
            );
        }

        $edition->loadMissing('divisions');

        return DB::transaction(function () use ($edition) {
            $editionId = (int) $edition->id_edition;
            $touchedEdIds = EditionParticipation::query()
                ->where('edition_id', $editionId)
                ->pluck('edition_division_id')
                ->all();

            EditionParticipation::query()
                ->where('edition_id', $editionId)
                ->delete();

            $divisions = $edition->divisions
                ->sortBy(fn (EditionDivision $ed) => [(int) $ed->skill_order, (int) $ed->id_edition_division])
                ->values();

            /** @var array<int, int> $countsByEdId */
            $countsByEdId = [];
            foreach ($divisions as $division) {
                $countsByEdId[(int) $division->id_edition_division] = 0;
            }

            $created = 0;
            $placedPlayerIds = [];

            $divisionSpecific = EditionRegistration::query()
                ->where('edition_id', $editionId)
                ->whereNotNull('edition_division_id')
                ->orderBy('id_registration')
                ->get();

            foreach ($divisionSpecific as $registration) {
                $edId = (int) $registration->edition_division_id;
                $playerId = (int) $registration->player_id;
                $this->createParticipation($editionId, $edId, $playerId, $countsByEdId);
                $placedPlayerIds[$playerId] = true;
                $touchedEdIds[] = $edId;
                $created++;
            }

            $editionWide = EditionRegistration::query()
                ->where('edition_id', $editionId)
                ->whereNull('edition_division_id')
                ->orderBy('id_registration')
                ->get()
                ->filter(fn (EditionRegistration $r) => ! isset($placedPlayerIds[(int) $r->player_id]))
                ->values();

            if ($editionWide->isNotEmpty()) {
                $playerIds = $editionWide->pluck('player_id')->map(fn ($id) => (int) $id)->all();
                $histories = $this->playerEditionHistory->forPlayers(
                    $playerIds,
                    $this->playerEditionHistory->cutoffDateForEdition($edition)
                );

                $sortedRegistrations = $editionWide
                    ->sort(function (EditionRegistration $a, EditionRegistration $b) use ($histories) {
                        $scoreA = $this->skillScore($histories[(int) $a->player_id] ?? []);
                        $scoreB = $this->skillScore($histories[(int) $b->player_id] ?? []);
                        if ($scoreA !== $scoreB) {
                            return $scoreA <=> $scoreB;
                        }
                        $partA = (int) ($histories[(int) $a->player_id]['participated_count'] ?? 0);
                        $partB = (int) ($histories[(int) $b->player_id]['participated_count'] ?? 0);
                        if ($partA !== $partB) {
                            return $partB <=> $partA;
                        }

                        return ((int) $a->player_id) <=> ((int) $b->player_id);
                    })
                    ->values();

                foreach ($sortedRegistrations as $registration) {
                    $playerId = (int) $registration->player_id;
                    $history = $histories[$playerId] ?? [];
                    $target = $this->pickEditionDivision(
                        $divisions,
                        $countsByEdId,
                        $history['last_played'] ?? null
                    );
                    $edId = (int) $target->id_edition_division;
                    $this->createParticipation($editionId, $edId, $playerId, $countsByEdId);
                    $touchedEdIds[] = $edId;
                    $created++;
                }
            }

            foreach (array_unique($touchedEdIds) as $edId) {
                $division = $divisions->firstWhere('id_edition_division', $edId);
                if ($division instanceof EditionDivision) {
                    $this->posterInvalidator->clearMatchesAndRanking($division);
                }
            }

            $this->posterInvalidator->clearAttendanceForEdition($editionId);

            $byEd = [];
            foreach ($countsByEdId as $edId => $count) {
                if ($count > 0) {
                    $byEd[] = [
                        'edition_division_id' => (int) $edId,
                        'count' => (int) $count,
                    ];
                }
            }

            return [
                'created_count' => $created,
                'by_edition_division' => $byEd,
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
        int $editionDivisionId,
        int $playerId,
        array &$countsByEdId
    ): void {
        $sortOrder = $countsByEdId[$editionDivisionId] ?? 0;
        EditionParticipation::query()->create([
            'edition_id' => $editionId,
            'edition_division_id' => $editionDivisionId,
            'player_id' => $playerId,
            'sort_order' => $sortOrder,
            'is_collecting_points' => true,
        ]);
        $countsByEdId[$editionDivisionId] = $sortOrder + 1;
    }

    /**
     * @param  Collection<int, EditionDivision>  $divisions
     * @param  array<int, int>  $countsByEdId
     * @param  null|array{division_id?: int, division_title?: string, rank?: null|int, participant_count?: int}  $lastPlayed
     */
    public function pickEditionDivision(
        Collection $divisions,
        array $countsByEdId,
        ?array $lastPlayed
    ): EditionDivision {
        $withCapacity = $divisions->filter(function (EditionDivision $ed) use ($countsByEdId) {
            $count = $countsByEdId[(int) $ed->id_edition_division] ?? 0;

            return $count < (int) $ed->players_limit;
        });

        if ($lastPlayed !== null && isset($lastPlayed['division_id']) && (int) $lastPlayed['division_id'] > 0) {
            $divisionId = (int) $lastPlayed['division_id'];
            $candidates = $divisions->filter(
                fn (EditionDivision $ed) => (int) $ed->division_id === $divisionId
            );
            if ($candidates->isNotEmpty()) {
                $pool = $this->preferCapacityWithinCandidates($candidates, $withCapacity);

                return $this->bestTitleThenSkillOrder($pool, (string) ($lastPlayed['division_title'] ?? ''));
            }
        }

        if ($lastPlayed !== null && ($lastPlayed['division_title'] ?? '') !== '') {
            $title = $this->normalizeTitle((string) $lastPlayed['division_title']);
            $candidates = $divisions->filter(
                fn (EditionDivision $ed) => $this->normalizeTitle((string) $ed->division_title) === $title
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
     * @param  Collection<int, EditionDivision>  $candidates
     * @param  Collection<int, EditionDivision>  $withCapacity
     * @return Collection<int, EditionDivision>
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
     * @param  Collection<int, EditionDivision>  $candidates
     * @param  Collection<int, EditionDivision>  $allowed
     * @return Collection<int, EditionDivision>
     */
    private function filterCandidates(Collection $candidates, Collection $allowed): Collection
    {
        $allowedIds = $allowed->pluck('id_edition_division')->all();

        return $candidates->filter(
            fn (EditionDivision $ed) => in_array($ed->id_edition_division, $allowedIds, true)
        );
    }

    /**
     * @param  Collection<int, EditionDivision>  $candidates
     */
    private function bestTitleThenSkillOrder(Collection $candidates, string $targetTitle): EditionDivision
    {
        $normalizedTarget = $this->normalizeTitle($targetTitle);
        $exact = $candidates->filter(
            fn (EditionDivision $ed) => $this->normalizeTitle((string) $ed->division_title) === $normalizedTarget
        );
        if ($exact->isNotEmpty()) {
            return $this->lowestSkillOrder($exact);
        }

        return $this->lowestSkillOrder($candidates);
    }

    /**
     * @param  Collection<int, EditionDivision>  $candidates
     */
    private function lowestSkillOrder(Collection $candidates): EditionDivision
    {
        $sorted = $candidates->sortBy(
            fn (EditionDivision $ed) => [(int) $ed->skill_order, (int) $ed->id_edition_division]
        )->values();

        $first = $sorted->first();
        if (! $first instanceof EditionDivision) {
            throw new EditionParticipationMappingException('No edition division available for placement.', 422);
        }

        return $first;
    }

    private function normalizeTitle(string $title): string
    {
        return mb_strtolower(trim($title));
    }

    private function disabledReason(Edition $edition): ?string
    {
        if ($edition->finalized_at !== null) {
            return 'finalized';
        }

        if ($edition->divisions->isEmpty()) {
            return 'no_divisions';
        }

        if ($this->lotteryGuard->hasLotteryLuckiesForEdition((int) $edition->id_edition)) {
            return 'lottery_exists';
        }

        foreach ($edition->divisions as $division) {
            if ($this->matchPersister->hasRegularMatches($division)) {
                return 'schedule_exists';
            }
        }

        return null;
    }

    private function messageForDisabledReason(string $reason): string
    {
        return match ($reason) {
            'finalized' => 'Edition is finalized.',
            'no_divisions' => 'Edition has no edition divisions.',
            'lottery_exists' => 'Participations cannot be remapped after lottery draws exist for this edition.',
            'schedule_exists' => 'Participations cannot be remapped after a schedule has been generated.',
            default => 'Participations cannot be mapped for this edition.',
        };
    }

    /**
     * @return array{
     *     registered_player_count: int,
     *     division_specific_registration_count: int,
     *     edition_wide_registration_count: int
     * }
     */
    private function registrationCounts(Edition $edition): array
    {
        $registrations = EditionRegistration::query()
            ->where('edition_id', $edition->id_edition)
            ->get(['player_id', 'edition_division_id']);

        $playerIds = $registrations->pluck('player_id')->unique()->count();
        $divisionSpecific = $registrations->whereNotNull('edition_division_id')->count();
        $editionWide = $registrations->whereNull('edition_division_id')->count();

        return [
            'registered_player_count' => (int) $playerIds,
            'division_specific_registration_count' => (int) $divisionSpecific,
            'edition_wide_registration_count' => (int) $editionWide,
        ];
    }
}
