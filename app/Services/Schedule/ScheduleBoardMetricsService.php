<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\CachedPdfPlayerImage;
use App\Models\EditionDivision;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Services\Pdf\PdfHtmlHelper;
use Illuminate\Support\Collection;

/**
 * Board metrics + optional players_board for the schedule Matches UI.
 */
final class ScheduleBoardMetricsService
{
    public function __construct(
        private readonly PlayerDistributionScorer $distributionScorer = new PlayerDistributionScorer(),
    ) {
    }

    /**
     * @return null|array<string, mixed>
     */
    public function buildMetrics(EditionDivision $editionDivision): ?array
    {
        $matches = $this->regularMatches($editionDivision);
        if ($matches->isEmpty()) {
            return null;
        }

        $participations = $editionDivision->participations
            ?? $editionDivision->participations()->orderBy('sort_order')->orderBy('id_participation')->get();

        $matchesByCourt = $this->matchesByCourtStructure($matches);
        $partnersCount = $this->partnersCountFromMatches($matchesByCourt);
        $playersMet = $this->playersMetFromMatches($matchesByCourt);

        $partnerValues = array_values($partnersCount);
        $variation = $partnerValues === [] ? 0 : (max($partnerValues) - min($partnerValues));

        $meetingCounts = [];
        foreach ($playersMet as $playerId => $met) {
            $meetingCounts[(int) $playerId] = count($met);
        }
        $meetingValues = array_values($meetingCounts);

        $playerIds = $participations->pluck('player_id')->map(static fn ($id): int => (int) $id)->all();
        $scored = $this->distributionScorer->scoreAll($playerIds, $matchesByCourt);
        $classifiedPerPlayer = [];
        foreach ($scored['per_player'] as $playerId => $score) {
            $grade = $this->distributionScorer->classify((float) $score);
            $classifiedPerPlayer[(int) $playerId] = [
                'score' => (float) $score,
                'percentage' => $grade['percentage'],
                'css_class' => $grade['css_class'],
            ];
        }
        $avgGrade = $this->distributionScorer->classify((float) $scored['avg']);
        $distribution = [
            'per_player' => $classifiedPerPlayer,
            'min' => $scored['min'],
            'avg' => $scored['avg'],
            'percentage' => $avgGrade['percentage'],
            'css_class' => $avgGrade['css_class'],
        ];

        $courtsCount = $editionDivision->relationLoaded('courts')
            ? $editionDivision->courts->count()
            : $editionDivision->courts()->count();

        $schedule = $editionDivision->relationLoaded('schedule')
            ? $editionDivision->getRelation('schedule')
            : $editionDivision->schedule()->first();

        return [
            'players_count' => $participations->count(),
            'matches_count' => $matches->count(),
            'points_per_match' => $schedule?->points_per_match,
            'partners_count' => $partnersCount,
            'partners_count_variation' => $variation,
            'players_met' => $playersMet,
            'min_opponents_met' => $meetingValues === [] ? 0 : min($meetingValues),
            'max_opponents_met' => $meetingValues === [] ? 0 : max($meetingValues),
            'playing_distribution' => $distribution,
            'template_identity' => [
                'players' => $participations->count(),
                'partners' => $schedule?->player_opponents_nr,
                'repeat' => $schedule?->player_matches_repeat,
                'courts' => $courtsCount,
                'version' => $schedule?->template_version,
            ],
        ];
    }

    /**
     * @return list<array{participation_id: int, player_id: int, short_name: string, pdf_avatar_url: null|string, is_collecting_points: bool}>
     */
    public function buildPlayersBoard(EditionDivision $editionDivision): array
    {
        $participations = $editionDivision->participations
            ?? $editionDivision->participations()->orderBy('sort_order')->orderBy('id_participation')->get();

        $participations->loadMissing('player');

        $board = [];
        foreach ($participations as $participation) {
            $player = $participation->player;
            if ($player === null) {
                continue;
            }
            $name = (string) $player->name;
            $collecting = (bool) $participation->is_collecting_points;
            $board[] = [
                'participation_id' => (int) $participation->id_participation,
                'player_id' => (int) $player->id_player,
                'short_name' => PdfHtmlHelper::shortenPlayerName($name),
                'pdf_avatar_url' => $this->resolveAvatarUrl($player, $collecting),
                'is_collecting_points' => $collecting,
            ];
        }

        return $board;
    }

    /**
     * Read-only lookup: the board never generates cached PDF images, it only surfaces already-cached ones.
     */
    private function resolveAvatarUrl(mixed $player, bool $collecting): ?string
    {
        $cached = CachedPdfPlayerImage::query()
            ->where('player_id', (int) $player->id_player)
            ->where('player_is_collecting_points', $collecting)
            ->first();

        return $cached?->url;
    }

    /**
     * @return Collection<int, GameMatch>
     */
    private function regularMatches(EditionDivision $editionDivision): Collection
    {
        if ($editionDivision->relationLoaded('matches')) {
            return $editionDivision->matches
                ->where('match_kind', 'REGULAR')
                ->sortBy('sequence_no')
                ->values();
        }

        return $editionDivision->matches()
            ->where('match_kind', 'REGULAR')
            ->orderBy('sequence_no')
            ->with(['sides.players.participation'])
            ->get();
    }

    /**
     * @param  Collection<int, GameMatch>  $matches
     * @return array<int, array<int, array{0: array{0: int, 1: int}, 1: array{0: int, 1: int}}>>
     */
    public function matchesByCourtStructure(Collection $matches): array
    {
        $byCourt = [];
        foreach ($matches as $match) {
            $courtKey = (int) $match->edition_division_court_id;
            $seq = (int) $match->sequence_no;
            if ($seq < 1) {
                continue;
            }
            $round = $seq - 1;
            $sides = $this->sidePlayerIds($match);
            if ($sides === null) {
                continue;
            }
            if (! isset($byCourt[$courtKey])) {
                $byCourt[$courtKey] = [];
            }
            $byCourt[$courtKey][$round] = $sides;
        }

        foreach ($byCourt as $courtKey => $rounds) {
            ksort($rounds);
            $dense = [];
            $max = $rounds === [] ? -1 : max(array_keys($rounds));
            for ($i = 0; $i <= $max; $i++) {
                if (isset($rounds[$i])) {
                    $dense[$i] = $rounds[$i];
                }
            }
            $byCourt[$courtKey] = $dense;
        }

        return $byCourt;
    }

    /**
     * @return null|array{0: array{0: int, 1: int}, 1: array{0: int, 1: int}}
     */
    private function sidePlayerIds(GameMatch $match): ?array
    {
        $left = [];
        $right = [];
        foreach ($match->sides as $side) {
            $ids = [];
            foreach ($side->players as $mp) {
                /** @var MatchPlayer $mp */
                $playerId = $mp->participation?->player_id ?? $mp->participation?->player?->id_player;
                if ($playerId === null) {
                    return null;
                }
                $ids[] = (int) $playerId;
            }
            if (count($ids) !== 2) {
                return null;
            }
            if (($side->side ?? '') === 'RIGHT') {
                $right = $ids;
            } else {
                $left = $ids;
            }
        }
        if (count($left) !== 2 || count($right) !== 2) {
            return null;
        }

        return [$left, $right];
    }

    /**
     * @param  array<int, array<int, array{0: array{0: int, 1: int}, 1: array{0: int, 1: int}}>>  $matchesByCourt
     * @return array<int, int>
     */
    private function partnersCountFromMatches(array $matchesByCourt): array
    {
        $pairs = [];
        foreach ($matchesByCourt as $rounds) {
            foreach ($rounds as $match) {
                foreach ([0, 1] as $side) {
                    $a = (int) $match[$side][0];
                    $b = (int) $match[$side][1];
                    $key = $a < $b ? "{$a}:{$b}" : "{$b}:{$a}";
                    $pairs[$key] = true;
                }
            }
        }

        $counts = [];
        foreach (array_keys($pairs) as $key) {
            [$a, $b] = array_map('intval', explode(':', $key));
            $counts[$a] = ($counts[$a] ?? 0) + 1;
            $counts[$b] = ($counts[$b] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  array<int, array<int, array{0: array{0: int, 1: int}, 1: array{0: int, 1: int}}>>  $matchesByCourt
     * @return array<int, array<int, int>>
     */
    private function playersMetFromMatches(array $matchesByCourt): array
    {
        $met = [];
        foreach ($matchesByCourt as $rounds) {
            foreach ($rounds as $match) {
                $all = [
                    (int) $match[0][0],
                    (int) $match[0][1],
                    (int) $match[1][0],
                    (int) $match[1][1],
                ];
                foreach ($all as $a) {
                    foreach ($all as $b) {
                        if ($a === $b) {
                            continue;
                        }
                        $met[$a][$b] = ($met[$a][$b] ?? 0) + 1;
                    }
                }
            }
        }

        return $met;
    }
}
