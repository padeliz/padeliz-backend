<?php

declare(strict_types=1);

namespace App\Services\Standings;

use App\Models\EditionDivision;
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
 * - Skip slots with got_replaced or participation.is_collecting_points = false
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

                $this->applySide($totals, $participations, $left, $leftScore, $leftWinDelta);
                $this->applySide($totals, $participations, $right, $rightScore, $rightWinDelta);
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
     */
    private function applySide(array &$totals, $participations, $side, int $sideScore, float $winDelta): void
    {
        foreach ($side->players as $matchPlayer) {
            if ($matchPlayer->got_replaced) {
                continue;
            }

            $participationId = $matchPlayer->participation_id;
            if ($participationId === null) {
                continue;
            }

            $participation = $participations->get($participationId) ?? $matchPlayer->participation;
            if ($participation === null || ! $participation->is_collecting_points) {
                continue;
            }

            $id = (int) $participationId;
            if (! isset($totals[$id])) {
                $totals[$id] = ['points_total' => 0, 'wins' => 0.0];
            }

            $totals[$id]['points_total'] += $sideScore;
            $totals[$id]['wins'] += $winDelta;
        }
    }
}
