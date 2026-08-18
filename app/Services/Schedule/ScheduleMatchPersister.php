<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionDivisionReplacement;
use App\Models\EditionParticipation;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\MatchSide;
use InvalidArgumentException;
use RuntimeException;

/**
 * Persists REGULAR matches from a ScheduleGenerationService preview payload.
 */
final class ScheduleMatchPersister
{
    /**
     * @param  array{
     *     matches_by_court: array<int, list<array{
     *         side_a: array{0: int, 1: int},
     *         side_b: array{0: int, 1: int},
     *         starts_at: string,
     *         ends_at: string,
     *         round_index: int,
     *         court_index: int,
     *         court_name: string
     *     }>>,
     *     points_per_match: int
     * }  $preview
     * @param  array<int, EditionParticipation>  $participationsByPlayerId
     * @return list<GameMatch>
     */
    public function persistRegularMatches(
        EditionDivision $editionDivision,
        array $preview,
        array $participationsByPlayerId,
    ): array {
        $courts = EditionDivisionCourt::query()
            ->where('edition_division_id', $editionDivision->id_edition_division)
            ->orderBy('display_order')
            ->orderBy('id_edition_division_court')
            ->get()
            ->values();

        if ($courts->isEmpty()) {
            throw new InvalidArgumentException('Edition division has no assigned courts.');
        }

        $pointsTarget = (int) ($preview['points_per_match'] ?? $editionDivision->points_per_match ?? 0);
        $created = [];

        foreach ($preview['matches_by_court'] as $courtIndex => $matches) {
            $edCourt = $courts->get((int) $courtIndex);
            if ($edCourt === null) {
                throw new RuntimeException(sprintf('Missing edition division court for court_index %d.', $courtIndex));
            }

            foreach ($matches as $matchData) {
                $sequenceNo = ((int) $matchData['round_index']) + 1;

                $match = GameMatch::create([
                    'edition_division_id' => $editionDivision->id_edition_division,
                    'edition_division_court_id' => $edCourt->id_edition_division_court,
                    'match_kind' => 'REGULAR',
                    'sequence_no' => $sequenceNo,
                    'scheduled_start' => $this->normalizeTime($matchData['starts_at']),
                    'scheduled_end' => $this->normalizeTime($matchData['ends_at']),
                    'points_target' => $pointsTarget > 0 ? $pointsTarget : null,
                ]);

                $left = MatchSide::create([
                    'match_id' => $match->id_match,
                    'side' => 'LEFT',
                    'score' => null,
                ]);
                $right = MatchSide::create([
                    'match_id' => $match->id_match,
                    'side' => 'RIGHT',
                    'score' => null,
                ]);

                $this->createMatchPlayers($left, $matchData['side_a'], $participationsByPlayerId);
                $this->createMatchPlayers($right, $matchData['side_b'], $participationsByPlayerId);

                $created[] = $match;
            }
        }

        return $created;
    }

    public function deleteRegularMatches(EditionDivision $editionDivision): int
    {
        $matchIds = GameMatch::query()
            ->where('edition_division_id', $editionDivision->id_edition_division)
            ->where('match_kind', 'REGULAR')
            ->pluck('id_match');

        if ($matchIds->isEmpty()) {
            return 0;
        }

        EditionDivisionReplacement::query()
            ->where('edition_division_id', $editionDivision->id_edition_division)
            ->delete();

        return GameMatch::query()->whereIn('id_match', $matchIds)->delete();
    }

    public function hasAnyScores(EditionDivision $editionDivision): bool
    {
        return MatchSide::query()
            ->whereNotNull('score')
            ->whereHas('match', function ($query) use ($editionDivision) {
                $query->where('edition_division_id', $editionDivision->id_edition_division);
            })
            ->exists();
    }

    public function hasStandings(EditionDivision $editionDivision): bool
    {
        return $editionDivision->standings()->exists();
    }

    public function hasRegularMatches(EditionDivision $editionDivision): bool
    {
        return GameMatch::query()
            ->where('edition_division_id', $editionDivision->id_edition_division)
            ->where('match_kind', 'REGULAR')
            ->exists();
    }

    /**
     * @param  list<int>  $playerIds
     * @param  array<int, EditionParticipation>  $participationsByPlayerId
     */
    private function createMatchPlayers(MatchSide $side, array $playerIds, array $participationsByPlayerId): void
    {
        foreach (array_values($playerIds) as $slot => $playerId) {
            $playerId = (int) $playerId;
            $participation = $participationsByPlayerId[$playerId] ?? null;
            if ($participation === null) {
                throw new InvalidArgumentException(sprintf('No participation mapped for player_id %d.', $playerId));
            }

            MatchPlayer::create([
                'match_side_id' => $side->id_match_side,
                'slot' => (int) $slot,
                'participation_id' => $participation->id_participation,
                'player_id' => $playerId,
            ]);
        }
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time . ':00';
        }

        return $time;
    }
}
