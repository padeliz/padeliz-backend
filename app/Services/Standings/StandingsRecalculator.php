<?php

declare(strict_types=1);

namespace App\Services\Standings;

use App\Models\EditionDivision;
use App\Models\EditionDivisionReplacement;
use App\Models\EditionDivisionStanding;
use App\Models\EditionParticipation;
use App\Models\GameMatch;
use Illuminate\Support\Facades\DB;

/**
 * Recalculates edition-division standings from REGULAR match scores.
 *
 * Rules:
 * - REGULAR matches only
 * - Higher side score → +1 win per eligible player on that side; tie → +0.5 each side
 * - points_total += side score for each counted match
 * - Skip slots covered by an edition_division_replacements row
 *   (same original participation; match_id NULL or this match)
 * - Skip participation.is_collecting_points = false
 * - Substitute is not credited for the replaced slot
 * - Rank by wins desc, then points_total desc; ties share rank
 */
final class StandingsRecalculator
{
    /**
     * @return list<EditionDivisionStanding>
     */
    public function recalculate(EditionDivision $editionDivision): array
    {
        return DB::transaction(function () use ($editionDivision): array {
            $participations = EditionParticipation::query()
                ->where('edition_division_id', $editionDivision->id_edition_division)
                ->get()
                ->keyBy('id_participation');

            /** @var array<int, array{points_total: int, wins: float}> $totals */
            $totals = [];
            foreach ($participations as $participation) {
                $totals[(int) $participation->id_participation] = [
                    'points_total' => 0,
                    'wins' => 0.0,
                ];
            }

            /** @var array<int, list<null|int>> $coveringByOriginal */
            $coveringByOriginal = [];
            $replacementRows = EditionDivisionReplacement::query()
                ->where('edition_division_id', $editionDivision->id_edition_division)
                ->get(['original_participation_id', 'match_id']);
            foreach ($replacementRows as $row) {
                $orig = (int) $row->original_participation_id;
                $coveringByOriginal[$orig][] = $row->match_id !== null ? (int) $row->match_id : null;
            }

            $matches = GameMatch::query()
                ->where('edition_division_id', $editionDivision->id_edition_division)
                ->where('match_kind', 'REGULAR')
                ->with(['sides.players.participation'])
                ->get();

            foreach ($matches as $match) {
                $left = $match->sides->firstWhere('side', 'LEFT');
                $right = $match->sides->firstWhere('side', 'RIGHT');

                if ($left === null || $right === null) {
                    continue;
                }
                if ($left->score === null || $right->score === null) {
                    continue;
                }

                $leftScore = (int) $left->score;
                $rightScore = (int) $right->score;

                $leftWinDelta = 0.0;
                $rightWinDelta = 0.0;
                if ($leftScore > $rightScore) {
                    $leftWinDelta = 1.0;
                } elseif ($rightScore > $leftScore) {
                    $rightWinDelta = 1.0;
                } else {
                    $leftWinDelta = 0.5;
                    $rightWinDelta = 0.5;
                }

                $matchId = (int) $match->id_match;
                $this->applySide($totals, $participations, $left, $leftScore, $leftWinDelta, $coveringByOriginal, $matchId);
                $this->applySide($totals, $participations, $right, $rightScore, $rightWinDelta, $coveringByOriginal, $matchId);
            }

            $rankByParticipation = StandingsRanker::assignCompetitionRanks($totals);

            EditionDivisionStanding::query()
                ->where('edition_division_id', $editionDivision->id_edition_division)
                ->delete();

            $standings = [];
            foreach ($totals as $participationId => $row) {
                $standings[] = EditionDivisionStanding::create([
                    'edition_division_id' => $editionDivision->id_edition_division,
                    'participation_id' => $participationId,
                    'points_total' => $row['points_total'],
                    'wins' => $row['wins'],
                    'rank' => $rankByParticipation[$participationId] ?? null,
                ]);
            }

            usort($standings, static fn (EditionDivisionStanding $a, EditionDivisionStanding $b): int => ($a->rank ?? PHP_INT_MAX) <=> ($b->rank ?? PHP_INT_MAX));

            return $standings;
        });
    }

    /**
     * @param  array<int, array{points_total: int, wins: float}>  $totals
     * @param  \Illuminate\Support\Collection<int, EditionParticipation>  $participations
     * @param  array<int, list<null|int>>  $coveringByOriginal
     */
    private function applySide(
        array &$totals,
        $participations,
        $side,
        int $sideScore,
        float $winDelta,
        array $coveringByOriginal,
        int $matchId,
    ): void {
        foreach ($side->players as $matchPlayer) {
            $participationId = $matchPlayer->participation_id;
            if ($participationId === null) {
                continue;
            }

            $id = (int) $participationId;
            if ($this->isCovered($coveringByOriginal, $id, $matchId)) {
                continue;
            }

            $participation = $participations->get($participationId) ?? $matchPlayer->participation;
            if ($participation === null || ! $participation->is_collecting_points) {
                continue;
            }

            if (! isset($totals[$id])) {
                $totals[$id] = ['points_total' => 0, 'wins' => 0.0];
            }

            $totals[$id]['points_total'] += $sideScore;
            $totals[$id]['wins'] += $winDelta;
        }
    }

    /**
     * @param  array<int, list<null|int>>  $coveringByOriginal
     */
    private function isCovered(array $coveringByOriginal, int $originalParticipationId, int $matchId): bool
    {
        foreach ($coveringByOriginal[$originalParticipationId] ?? [] as $scopeMatchId) {
            if ($scopeMatchId === null || $scopeMatchId === $matchId) {
                return true;
            }
        }

        return false;
    }
}
