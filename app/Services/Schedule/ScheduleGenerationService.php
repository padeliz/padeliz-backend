<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use InvalidArgumentException;

/**
 * Builds a structured schedule preview from ordered player ids and a mixed template.
 *
 * Persistence is left to a later controller; this service only loads/remaps templates.
 */
final class ScheduleGenerationService
{
    public function __construct(
        private readonly ?TemplateMatchesRepository $repository = null,
    ) {
    }

    /**
     * @param  list<int>  $playerIds  Ordered player ids (template seat order).
     * @param  list<string>  $courtNames
     * @return array{
     *     player_ids: list<int>,
     *     opponents_per_player: int,
     *     repeat_partners: int,
     *     court_names: list<string>,
     *     courts_count: int,
     *     time_start: string,
     *     time_end: string,
     *     include_final: bool,
     *     has_demonstrative_match: bool,
     *     template_version: int,
     *     template_file: string,
     *     rounds_total: int,
     *     matches_count: int,
     *     points_per_match: int,
     *     average_playing_time_minutes: float,
     *     partners_count_variation: int,
     *     partners_count: array<int|string, int>,
     *     players_met: array<int|string, array<int|string, int>>,
     *     matches_by_court: array<int, list<array{
     *         side_a: array{0: int, 1: int},
     *         side_b: array{0: int, 1: int},
     *         starts_at: string,
     *         ends_at: string,
     *         round_index: int,
     *         court_index: int,
     *         court_name: string
     *     }>>
     * }
     */
    public function preview(
        array $playerIds,
        int $opponentsPerPlayer,
        int $repeatPartners,
        array $courtNames,
        string $timeStart,
        string $timeEnd,
        bool $includeFinal,
        bool $hasDemonstrativeMatch = false,
        ?int $templateVersion = null,
    ): array {
        $playerIds = array_values(array_map(static fn ($id): int => (int) $id, $playerIds));
        $courtNames = array_values(array_map(static fn ($name): string => (string) $name, $courtNames));

        if ($playerIds === []) {
            throw new InvalidArgumentException('At least one player id is required.');
        }
        if ($courtNames === []) {
            throw new InvalidArgumentException('At least one court name is required.');
        }
        if ($opponentsPerPlayer < 1) {
            throw new InvalidArgumentException('opponentsPerPlayer must be >= 1.');
        }
        if ($repeatPartners < 1) {
            throw new InvalidArgumentException('repeatPartners must be >= 1.');
        }

        $repository = $this->repository ?? new TemplateMatchesRepository();

        $generator = new MatchesGenerator(
            $playerIds,
            $opponentsPerPlayer,
            $repeatPartners,
            count($courtNames),
            $timeStart,
            $timeEnd,
            $includeFinal,
            $hasDemonstrativeMatch,
            $templateVersion,
            $repository,
        );

        $rawByCourt = $generator->getMatches();
        $structured = [];

        foreach ($rawByCourt as $courtIndex => $rounds) {
            $courtName = $courtNames[$courtIndex] ?? ('Court ' . ((int) $courtIndex + 1));
            $courtMatches = [];

            foreach ($rounds as $roundIndex => $match) {
                $courtMatches[] = [
                    'side_a' => [(int) $match[0][0], (int) $match[0][1]],
                    'side_b' => [(int) $match[1][0], (int) $match[1][1]],
                    'starts_at' => (string) $match[2],
                    'ends_at' => (string) $match[3],
                    'round_index' => (int) $roundIndex,
                    'court_index' => (int) $courtIndex,
                    'court_name' => $courtName,
                ];
            }

            $structured[(int) $courtIndex] = $courtMatches;
        }

        $roundsTotal = $generator->getRoundsTotal();
        $templateFile = $repository->filenameForIdentity(
            count($playerIds),
            $opponentsPerPlayer,
            $repeatPartners,
            count($courtNames),
            false,
        );

        return [
            'player_user_ids' => $playerIds,
            'opponents_per_player' => $opponentsPerPlayer,
            'repeat_partners' => $repeatPartners,
            'court_names' => $courtNames,
            'courts_count' => count($courtNames),
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'include_final' => $includeFinal,
            'has_demonstrative_match' => $hasDemonstrativeMatch,
            'template_version' => $generator->getTemplateVersion(),
            'template_file' => $templateFile,
            'rounds_total' => $roundsTotal,
            'matches_count' => $generator->getMatchesCount(),
            'points_per_match' => PointsPerMatchCalculator::calculate($timeStart, $timeEnd, $roundsTotal),
            'average_playing_time_minutes' => AveragePlayingTimeCalculator::calculate(
                $timeStart,
                $timeEnd,
                $roundsTotal,
            ),
            'partners_count_variation' => $generator->partnersCountVariation(),
            'partners_count' => $generator->getPartnersCount(),
            'players_met' => $generator->getPlayersMet(),
            'matches_by_court' => $structured,
        ];
    }
}
