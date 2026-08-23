<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\Club;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionDivisionPoster;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Organizer;
use App\Models\Player;
use App\Services\Schedule\PlayingWindow;
use App\Services\Schedule\ScheduleGenerationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class PosterPdfService
{
    /** @var list<string> */
    private array $ephemeralTemps = [];

    public function __construct(
        private readonly CachedPdfPairImageService $pairImages = new CachedPdfPairImageService(),
        private readonly CachedPdfPlayerImageService $playerImages = new CachedPdfPlayerImageService(),
        private readonly PdfCachedImageGenerator $imageGenerator = new PdfCachedImageGenerator(),
        private readonly ?ScheduleGenerationService $schedule = null,
    ) {
    }

    public function generateMatchesForEditionDivision(
        EditionDivision $editionDivision,
        EditionDivisionCourt $court,
    ): PosterPdfResult {
        $this->ephemeralTemps = [];

        try {
            $editionDivision->loadMissing([
                'edition.organizer',
                'edition.location.club',
                'schedule',
                'division',
                'participations.player.profileImages',
                'editionDivisionCourts.court',
            ]);

            $schedule = $editionDivision->requireSchedule();
            $branding = $this->editionBranding($editionDivision->edition);

            $matches = GameMatch::query()
                ->with(['sides.players.participation.player.profileImages'])
                ->where('edition_division_id', $editionDivision->id_edition_division)
                ->where('edition_division_court_id', $court->id_edition_division_court)
                ->where('match_kind', 'REGULAR')
                ->orderBy('sequence_no')
                ->get();

            $rows = $matches->map(function (GameMatch $match): array {
                $left = $match->sides->firstWhere('side', 'LEFT');
                $right = $match->sides->firstWhere('side', 'RIGHT');
                $leftPlayers = $left?->players?->sortBy('slot')->values() ?? collect();
                $rightPlayers = $right?->players?->sortBy('slot')->values() ?? collect();

                return [
                    'side_a' => [
                        $this->playerPayloadFromMatchPlayer($leftPlayers->get(0)),
                        $this->playerPayloadFromMatchPlayer($leftPlayers->get(1)),
                    ],
                    'side_b' => [
                        $this->playerPayloadFromMatchPlayer($rightPlayers->get(0)),
                        $this->playerPayloadFromMatchPlayer($rightPlayers->get(1)),
                    ],
                    'starts_at' => $this->formatTime($match->scheduled_start),
                    'ends_at' => $this->formatTime($match->scheduled_end),
                ];
            })->all();

            $court->loadMissing('court');
            $payload = $this->buildMatchesViewData(
                title: (string) $editionDivision->division_title,
                color: (string) ($editionDivision->division?->color ?? '#7ab857'),
                editionLabel: (string) ($editionDivision->edition?->name ?? ''),
                organizerImage: $branding['organizer_image'],
                clubHostImage: $branding['club_host_image'],
                showEditionLabel: $branding['show_edition_label'],
                courtName: (string) ($court->court?->name ?? 'Court'),
                pointsPerMatch: (int) ($schedule->points_per_match ?? 0),
                includeScores: (bool) $schedule->include_scores,
                includeFinal: (bool) $schedule->include_final,
                hasDemonstrativeMatch: (bool) $schedule->has_demonstrative_match,
                allowReplacements: (bool) $schedule->allow_replacements,
                finalPointsTarget: (int) ($schedule->final_points_target ?? 24),
                timeEnd: $this->formatTime($editionDivision->playing_end_time),
                matchRows: $rows,
                persistCachedPdfImages: true,
            );

            $bytes = $this->renderMatchesPdf($payload);
            $filename = $this->matchesFilename($payload['title']);
            $poster = $this->persistGeneratedPoster(
                $editionDivision,
                'MATCHES',
                $court->id_edition_division_court,
                $bytes,
                $filename,
            );

            return new PosterPdfResult($bytes, $filename, $poster);
        } finally {
            $this->imageGenerator->unlinkTemps($this->ephemeralTemps);
            $this->ephemeralTemps = [];
        }
    }

    public function generateRankingForEditionDivision(EditionDivision $editionDivision): PosterPdfResult
    {
        $this->ephemeralTemps = [];

        try {
            $editionDivision->loadMissing([
                'edition.organizer',
                'edition.location.club',
                'schedule',
                'division',
                'participations.player.profileImages',
                'editionDivisionCourts.court',
            ]);

            $schedule = $editionDivision->requireSchedule();
            $branding = $this->editionBranding($editionDivision->edition);

            $players = $editionDivision->participations
                ->filter(fn ($p) => (bool) $p->is_collecting_points)
                ->map(fn ($p) => $this->playerPayload($p->player, [(int) $p->player_id => true]))
                ->sortBy(fn (array $p) => mb_strtolower($p['name']))
                ->values()
                ->all();

            $opponents = (int) ($schedule->player_opponents_nr ?? 0);
            $repeat = (int) ($schedule->player_matches_repeat ?? 1);
            $playerMatchesCount = max(1, $opponents * max(1, $repeat));

            $courtNames = $editionDivision->editionDivisionCourts
                ->sortBy('display_order')
                ->map(fn ($edc) => (string) ($edc->court?->name ?? 'Court'))
                ->values()
                ->all();

            $payload = $this->buildRankingViewData(
                title: (string) $editionDivision->division_title,
                color: (string) ($editionDivision->division?->color ?? '#7ab857'),
                editionLabel: (string) ($editionDivision->edition?->name ?? ''),
                organizerImage: $branding['organizer_image'],
                clubHostImage: $branding['club_host_image'],
                showEditionLabel: $branding['show_edition_label'],
                courtLabel: implode(' / ', $courtNames) ?: 'Courts',
                pointsPerMatch: (int) ($schedule->points_per_match ?? 0),
                includeScores: (bool) $schedule->include_scores,
                includeFinal: (bool) $schedule->include_final,
                allowReplacements: (bool) $schedule->allow_replacements,
                players: $players,
                playerMatchesCount: $playerMatchesCount,
                persistCachedPdfImages: true,
            );

            $bytes = $this->renderRankingPdf($payload);
            $filename = $this->rankingFilename($payload['title']);
            $poster = $this->persistGeneratedPoster(
                $editionDivision,
                'RANKING',
                null,
                $bytes,
                $filename,
            );

            return new PosterPdfResult($bytes, $filename, $poster);
        } finally {
            $this->imageGenerator->unlinkTemps($this->ephemeralTemps);
            $this->ephemeralTemps = [];
        }
    }

    public function generateAttendanceForEdition(Edition $edition): PosterPdfResult
    {
        $this->ephemeralTemps = [];

        try {
            $edition->loadMissing(['organizer', 'divisions.division', 'divisions.participations.player.profileImages', 'divisions.editionDivisionCourts.court']);

            $divisions = $edition->divisions
                ->sortBy(fn (EditionDivision $ed) => [(int) $ed->skill_order, (int) $ed->id_edition_division])
                ->values()
                ->all();

            $payload = $this->buildAttendanceViewData($edition, $divisions, false);
            $bytes = $this->renderAttendancePdf($payload);
            $filename = $this->attendanceFilename((string) $edition->name);
            $poster = $this->persistEditionPoster($edition, 'ATTENDANCE', $bytes, $filename);

            return new PosterPdfResult($bytes, $filename, $poster);
        } finally {
            $this->imageGenerator->unlinkTemps($this->ephemeralTemps);
            $this->ephemeralTemps = [];
        }
    }

    public function renderAttendanceHtmlForEdition(Edition $edition): string
    {
        $this->ephemeralTemps = [];

        try {
            $edition->loadMissing(['organizer', 'divisions.division', 'divisions.participations.player.profileImages', 'divisions.editionDivisionCourts.court']);

            $divisions = $edition->divisions
                ->sortBy(fn (EditionDivision $ed) => [(int) $ed->skill_order, (int) $ed->id_edition_division])
                ->values()
                ->all();

            $payload = $this->buildAttendanceViewData($edition, $divisions, true);

            return View::make('pdf.attendance', $payload)->render();
        } finally {
            $this->imageGenerator->unlinkTemps($this->ephemeralTemps);
            $this->ephemeralTemps = [];
        }
    }

    /**
     * Mode B: stream-only MATCHES PDF from scratch schedule knobs (no DB writes).
     *
     * @param  array{
     *     player_ids: list<int>,
     *     collecting_player_ids?: list<int>,
     *     court_names: list<string>,
     *     court_index: int,
     *     time_start: string,
     *     time_end: string,
     *     opponents_per_player: int,
     *     repeat_partners: int,
     *     include_final?: bool,
     *     has_demonstrative_match?: bool,
     *     include_scores?: bool,
     *     allow_replacements?: bool,
     *     title: string,
     *     color?: string,
     *     edition_label?: string,
     *     organizer_id?: null|int,
     *     club_id?: null|int,
     *     points_per_match?: null|int,
     *     final_points_target?: int,
     *     template_version?: null|int
     * }  $input
     */
    public function generateMatchesFromScratch(array $input): PosterPdfResult
    {
        $playerIds = array_values(array_map('intval', $input['player_ids']));
        $players = $this->loadPlayersByIds($playerIds);
        $collecting = $this->collectingMap($playerIds, $input['collecting_player_ids'] ?? $playerIds);
        $branding = $this->scratchBranding($input);

        $this->ephemeralTemps = [];

        try {
            $preview = $this->scratchPreview($input);
            $courtIndex = (int) $input['court_index'];
            $courtMatches = $preview['matches_by_court'][$courtIndex] ?? [];
            if ($courtMatches === []) {
                throw new RuntimeException('No matches for the requested court index.');
            }

            $rows = array_map(function (array $match) use ($players, $collecting): array {
                return [
                    'side_a' => [
                        $this->playerPayload($players->get($match['side_a'][0]), $collecting),
                        $this->playerPayload($players->get($match['side_a'][1]), $collecting),
                    ],
                    'side_b' => [
                        $this->playerPayload($players->get($match['side_b'][0]), $collecting),
                        $this->playerPayload($players->get($match['side_b'][1]), $collecting),
                    ],
                    'starts_at' => $this->formatClock($match['starts_at']),
                    'ends_at' => $this->formatClock($match['ends_at']),
                ];
            }, $courtMatches);

            $payload = $this->buildMatchesViewData(
                title: (string) $input['title'],
                color: (string) ($input['color'] ?? '#7ab857'),
                editionLabel: (string) ($input['edition_label'] ?? 'Scratch'),
                organizerImage: $branding['organizer_image'],
                clubHostImage: $branding['club_host_image'],
                showEditionLabel: $branding['show_edition_label'],
                courtName: (string) ($preview['court_names'][$courtIndex] ?? 'Court'),
                pointsPerMatch: (int) ($input['points_per_match'] ?? $preview['points_per_match']),
                includeScores: (bool) ($input['include_scores'] ?? true),
                includeFinal: (bool) ($input['include_final'] ?? false),
                hasDemonstrativeMatch: (bool) ($input['has_demonstrative_match'] ?? false),
                allowReplacements: (bool) ($input['allow_replacements'] ?? true),
                finalPointsTarget: (int) ($input['final_points_target'] ?? 24),
                timeEnd: $this->formatClock((string) $input['time_end']),
                matchRows: $rows,
                persistCachedPdfImages: false,
            );

            $bytes = $this->renderMatchesPdf($payload);

            return new PosterPdfResult($bytes, $this->matchesFilename($payload['title']));
        } finally {
            $this->imageGenerator->unlinkTemps($this->ephemeralTemps);
            $this->ephemeralTemps = [];
        }
    }

    /**
     * Mode B: stream-only RANKING PDF (no DB writes).
     *
     * @param  array{
     *     player_ids: list<int>,
     *     collecting_player_ids?: list<int>,
     *     court_names: list<string>,
     *     time_start: string,
     *     time_end: string,
     *     opponents_per_player: int,
     *     repeat_partners: int,
     *     include_final?: bool,
     *     include_scores?: bool,
     *     allow_replacements?: bool,
     *     title: string,
     *     color?: string,
     *     edition_label?: string,
     *     organizer_id?: null|int,
     *     club_id?: null|int,
     *     points_per_match?: null|int,
     *     template_version?: null|int
     * }  $input
     */
    public function generateRankingFromScratch(array $input): PosterPdfResult
    {
        $playerIds = array_values(array_map('intval', $input['player_ids']));
        $playersById = $this->loadPlayersByIds($playerIds);
        $collectingIds = $input['collecting_player_ids'] ?? $playerIds;
        $collecting = $this->collectingMap($playerIds, $collectingIds);
        $branding = $this->scratchBranding($input);

        $this->ephemeralTemps = [];

        try {
            $players = collect($collectingIds)
                ->map(fn ($id) => $this->playerPayload($playersById->get((int) $id), $collecting))
                ->filter()
                ->sortBy(fn (array $p) => mb_strtolower($p['name']))
                ->values()
                ->all();

            $preview = $this->scratchPreview($input);

            $payload = $this->buildRankingViewData(
                title: (string) $input['title'],
                color: (string) ($input['color'] ?? '#7ab857'),
                editionLabel: (string) ($input['edition_label'] ?? 'Scratch'),
                organizerImage: $branding['organizer_image'],
                clubHostImage: $branding['club_host_image'],
                showEditionLabel: $branding['show_edition_label'],
                courtLabel: implode(' / ', $preview['court_names']),
                pointsPerMatch: (int) ($input['points_per_match'] ?? $preview['points_per_match']),
                includeScores: (bool) ($input['include_scores'] ?? true),
                includeFinal: (bool) ($input['include_final'] ?? false),
                allowReplacements: (bool) ($input['allow_replacements'] ?? true),
                players: $players,
                playerMatchesCount: max(1, (int) $preview['opponents_per_player'] * max(1, (int) $preview['repeat_partners'])),
                persistCachedPdfImages: false,
            );

            $bytes = $this->renderRankingPdf($payload);

            return new PosterPdfResult($bytes, $this->rankingFilename($payload['title']));
        } finally {
            $this->imageGenerator->unlinkTemps($this->ephemeralTemps);
            $this->ephemeralTemps = [];
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function scratchPreview(array $input): array
    {
        $schedule = $this->schedule ?? new ScheduleGenerationService();

        return $schedule->preview(
            array_values(array_map('intval', $input['player_ids'])),
            (int) $input['opponents_per_player'],
            (int) $input['repeat_partners'],
            array_values(array_map('strval', $input['court_names'])),
            (string) $input['time_start'],
            (string) $input['time_end'],
            (bool) ($input['include_final'] ?? false),
            (bool) ($input['has_demonstrative_match'] ?? false),
            isset($input['template_version']) ? (int) $input['template_version'] : null,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $matchRows
     * @return array<string, mixed>
     */
    private function buildMatchesViewData(
        string $title,
        string $color,
        string $editionLabel,
        ?string $organizerImage,
        ?string $clubHostImage,
        bool $showEditionLabel,
        string $courtName,
        int $pointsPerMatch,
        bool $includeScores,
        bool $includeFinal,
        bool $hasDemonstrativeMatch,
        bool $allowReplacements,
        int $finalPointsTarget,
        string $timeEnd,
        array $matchRows,
        bool $persistCachedPdfImages,
    ): array {
        $activitiesCount = count($matchRows) + ($includeFinal ? 2 : 0) + ($hasDemonstrativeMatch ? 1 : 0);
        $marginTop = PdfHtmlHelper::getActivitiesMarginTop($activitiesCount);
        $matchBreakColumn = (int) ceil(count($matchRows) / 2) - 1;
        $matchesWithoutMarginTop = [0];
        if ($matchBreakColumn >= 0) {
            $matchesWithoutMarginTop[] = $matchBreakColumn + 1;
        }

        $enriched = [];
        foreach ($matchRows as $row) {
            $enriched[] = [
                ...$row,
                'team_a_image' => $this->pairImagePath($row['side_a'][0], $row['side_a'][1], $persistCachedPdfImages),
                'team_b_image' => $this->pairImagePath($row['side_b'][0], $row['side_b'][1], $persistCachedPdfImages),
            ];
        }

        return [
            'title' => $title,
            'color' => $color,
            'edition_label' => $editionLabel,
            'organizer_image' => $organizerImage,
            'club_host_image' => $clubHostImage,
            'show_edition_label' => $showEditionLabel,
            'court_name' => $courtName,
            'points_per_match_print' => PdfHtmlHelper::evenRoundPointsForPrint($pointsPerMatch),
            'include_scores' => $includeScores,
            'include_final' => $includeFinal,
            'has_demonstrative_match' => $hasDemonstrativeMatch,
            'allow_replacements' => $allowReplacements,
            'final_points_target' => $finalPointsTarget,
            'time_end' => $timeEnd,
            'matches' => $enriched,
            'margin_top' => $marginTop,
            'match_break_column' => $matchBreakColumn,
            'matches_without_margin_top' => $matchesWithoutMarginTop,
            'stretching_image' => public_path('media/prints/MiniTour-step-stretching.jpg'),
            'finalist_image' => public_path('media/prints/MiniTour-finalist-team.jpg'),
            'best_teams_image' => public_path('media/prints/MiniTour-best-teams.png'),
            'special_guest_image' => public_path('media/prints/special-guests/1.png'),
            'demo_time' => $this->minusMinutes($timeEnd, 15),
            'stretch_time' => isset($enriched[0]['starts_at']) ? $this->minusMinutes($enriched[0]['starts_at'], 15) : '',
            'final_time' => $enriched !== [] ? (string) $enriched[array_key_last($enriched)]['ends_at'] : '',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $players
     * @return array<string, mixed>
     */
    private function buildRankingViewData(
        string $title,
        string $color,
        string $editionLabel,
        ?string $organizerImage,
        ?string $clubHostImage,
        bool $showEditionLabel,
        string $courtLabel,
        int $pointsPerMatch,
        bool $includeScores,
        bool $includeFinal,
        bool $allowReplacements,
        array $players,
        int $playerMatchesCount,
        bool $persistCachedPdfImages,
    ): array {
        $matchesRows = PdfHtmlHelper::splitMatchRankingRows(count($players), $playerMatchesCount);
        $nrOfRows = count($matchesRows);
        $marginTop = PdfHtmlHelper::getPlayersMarginTop(count($players), $nrOfRows);
        $sizing = PdfHtmlHelper::PLAYERS_ROWS_SIZING[(string) $nrOfRows] ?? PdfHtmlHelper::PLAYERS_ROWS_SIZING['1'];

        foreach ($players as &$player) {
            $player['avatar_path'] = $this->playerImages->resolvePath(
                (int) $player['id'],
                (string) $player['profile_abs_path'],
                (bool) $player['is_collecting_points'],
                $persistCachedPdfImages,
                $player['source_player_profile_image_id'] ?? null,
                $player['player_updated_at'] ?? null,
            );
            $player['short_name'] = PdfHtmlHelper::shortenPlayerName($player['name']);
            $player['html_short_name'] = PdfHtmlHelper::htmlShortName($player['short_name'], $player['is_collecting_points']);
            $player['name_font_size'] = PdfHtmlHelper::getFittedFontSize($player['short_name'], 153, 33);
        }
        unset($player);

        return [
            'title' => $title,
            'color' => $color,
            'edition_label' => $editionLabel,
            'organizer_image' => $organizerImage,
            'club_host_image' => $clubHostImage,
            'show_edition_label' => $showEditionLabel,
            'court_label' => $courtLabel,
            'points_per_match_print' => PdfHtmlHelper::evenRoundPointsForPrint($pointsPerMatch),
            'include_scores' => $includeScores,
            'include_final' => $includeFinal,
            'allow_replacements' => $allowReplacements,
            'players' => $players,
            'player_matches_count' => $playerMatchesCount,
            'matches_rows' => $matchesRows,
            'nr_of_rows' => $nrOfRows,
            'margin_top' => $marginTop,
            'sizing' => $sizing,
            'show_column_headers' => count($players) < 12,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderMatchesPdf(array $payload): string
    {
        $html = View::make('pdf.matches', $payload)->render();
        $mpdf = $this->makeMpdf();
        $this->applyMatchesFooter($mpdf, $payload);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderRankingPdf(array $payload): string
    {
        $html = View::make('pdf.ranking', $payload)->render();
        $mpdf = $this->makeMpdf();
        $this->applyRankingFooter($mpdf, $payload);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function makeMpdf(): Mpdf
    {
        $tempDir = storage_path('app/tmp/mpdf');
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0777, true) && ! is_dir($tempDir)) {
            throw new RuntimeException('Failed to create mPDF temp directory.');
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A2-P',
            'autoPageBreak' => false,
            'nonPrintMargin' => 0,
            'simpleTable' => true,
            'tableMinSizePriority' => true,
            'keepColumns' => true,
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'tempDir' => $tempDir,
        ]);
        $mpdf->SetMargins(0, 0, 0);

        return $mpdf;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyMatchesFooter(Mpdf $mpdf, array $payload): void
    {
        $matchesCount = count($payload['matches']) + (! empty($payload['has_demonstrative_match']) ? 1 : 0);
        if ($matchesCount >= 25) {
            return;
        }

        $oddFooter = [];
        if (! empty($payload['allow_replacements'])) {
            $oddFooter['L']['content'] = '<b>If someone is missing</b> from the next match, you can take their place <u>without receiving points</u> for that match.';
        } elseif (empty($payload['include_scores'])) {
            $oddFooter['L']['content'] = "The score doesn't matter, but if you prefer, you can keep it during the match.";
        }

        if (! empty($oddFooter['L']['content'])) {
            $oddFooter['R'] = [
                'content' => 'Two serves per player/team. The team on the left serves first.',
            ];
        } else {
            $oddFooter['C'] = [
                'content' => 'Two serves per player/team. The team on the left serves first.',
            ];
        }

        $mpdf->SetFooter(['odd' => $oddFooter]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyRankingFooter(Mpdf $mpdf, array $payload): void
    {
        $mpdf->SetFooter([
            'odd' => [
                'L' => [
                    'content' => ! empty($payload['allow_replacements'])
                        ? '<b>If replacing someone in a match</b>, instead of the score, put just a dash (-) for her/him, and nothing for yourself.'
                        : '',
                ],
                'R' => [
                    'content' => ! empty($payload['include_final'])
                        ? 'The final is played by the 4 players with the most matches won.'
                        : '',
                ],
            ],
        ]);
    }

    private function persistGeneratedPoster(
        EditionDivision $editionDivision,
        string $kind,
        ?int $courtId,
        string $bytes,
        string $filename,
    ): EditionDivisionPoster {
        $poster = EditionDivisionPoster::query()
            ->where('edition_division_id', $editionDivision->id_edition_division)
            ->where('poster_kind', $kind)
            ->when(
                $courtId === null,
                fn ($query) => $query->whereNull('edition_division_court_id'),
                fn ($query) => $query->where('edition_division_court_id', $courtId)
            )
            ->orderByDesc('id_edition_division_poster')
            ->first();

        if ($poster === null) {
            $poster = new EditionDivisionPoster();
            $poster->edition_division_id = $editionDivision->id_edition_division;
            $poster->edition_division_court_id = $courtId;
            $poster->poster_kind = $kind;
        }

        $poster->edition_id = (int) $editionDivision->edition_id;
        if (! $poster->exists) {
            $poster->save();
        }

        $disk = $poster->storageDisk();
        $relative = 'edition-division-posters/' . $poster->id_edition_division_poster . '/generated/' . $filename;
        Storage::disk($disk)->put($relative, $bytes, ['visibility' => 'public']);
        $poster->generated_path = $relative;
        $poster->save();

        return $poster->fresh();
    }

    private function persistEditionPoster(
        Edition $edition,
        string $kind,
        string $bytes,
        string $filename,
    ): EditionDivisionPoster {
        $poster = EditionDivisionPoster::query()
            ->where('edition_id', $edition->id_edition)
            ->whereNull('edition_division_id')
            ->where('poster_kind', $kind)
            ->whereNull('edition_division_court_id')
            ->orderByDesc('id_edition_division_poster')
            ->first();

        if ($poster === null) {
            $poster = new EditionDivisionPoster();
            $poster->edition_id = (int) $edition->id_edition;
            $poster->edition_division_id = null;
            $poster->poster_kind = $kind;
            $poster->edition_division_court_id = null;
            $poster->save();
        }

        $disk = $poster->storageDisk();
        $relative = 'edition-division-posters/' . $poster->id_edition_division_poster . '/generated/' . $filename;
        Storage::disk($disk)->put($relative, $bytes, ['visibility' => 'public']);
        $poster->generated_path = $relative;
        $poster->save();

        return $poster->fresh();
    }

    /**
     * @param  list<EditionDivision>  $divisions
     * @return array<string, mixed>
     */
    private function buildAttendanceViewData(Edition $edition, array $divisions, bool $forBrowserPreview = false): array
    {
        $headerLeft = $edition->organizer_id !== null
            ? trim(((string) ($edition->organizer?->name ?? '')) . ' ' . ((string) $edition->name))
            : (string) $edition->name;

        $headerDate = AttendancePdfLayout::formatOrdinalHeaderDate((string) $edition->date);
        $arriving = $this->formatTime($edition->arriving_time);
        $closing = $this->formatTime($edition->closing_time);
        $headerTimes = trim($arriving . ' – ' . $closing);

        $pages = [];
        foreach (AttendancePdfLayout::pageBatches($divisions) as $batch) {
            $cells = [];

            foreach ($batch as $editionDivision) {
                $participations = AttendancePdfLayout::sortParticipationsByPlayerName(
                    $editionDivision->participations ?? collect()
                );

                $participants = [];
                foreach ($participations as $participation) {
                    $player = $participation->player;
                    if ($player === null) {
                        continue;
                    }

                    $collecting = (bool) $participation->is_collecting_points;
                    if ($forBrowserPreview) {
                        $avatarSrc = $this->imageGenerator->generatePlayerAvatarUrl($player, $collecting) ?? '';
                    } else {
                        $avatarSrc = $this->imageGenerator->generatePlayerAvatar($player, $collecting, true);
                    }

                    $participants[] = [
                        'name' => (string) $player->name,
                        'avatar_src' => $avatarSrc,
                    ];
                }

                $courtNames = ($editionDivision->editionDivisionCourts ?? collect())
                    ->sortBy('display_order')
                    ->map(fn ($edc) => (string) ($edc->court?->name ?? 'Court'))
                    ->values()
                    ->all();

                $cells[] = [
                    'color' => (string) ($editionDivision->division?->color ?? '#7ab857'),
                    'division_title' => (string) $editionDivision->division_title,
                    'courts_label' => implode(', ', $courtNames),
                    'participants' => $participants,
                ];
            }

            $columnCount = AttendancePdfLayout::columnCountForEdCount(count($cells));
            $rawCellRows = AttendancePdfLayout::rowBatches($cells, $columnCount);

            $cellRows = [];
            foreach ($rawCellRows as $rowCells) {
                $cellRows[] = [
                    'cells' => $rowCells,
                ];
            }

            $pages[] = [
                'header_left' => $headerLeft,
                'header_date' => $headerDate,
                'header_times' => $headerTimes,
                'column_count' => $columnCount,
                'column_width_percent' => AttendancePdfLayout::columnWidthPercent($columnCount),
                'cell_rows' => $cellRows,
            ];
        }

        return ['pages' => $pages];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderAttendancePdf(array $payload): string
    {
        $html = View::make('pdf.attendance', $payload)->render();
        $mpdf = $this->makeAttendanceMpdf();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function makeAttendanceMpdf(): Mpdf
    {
        $tempDir = storage_path('app/tmp/mpdf');
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0777, true) && ! is_dir($tempDir)) {
            throw new RuntimeException('Failed to create mPDF temp directory.');
        }

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-P',
            'autoPageBreak' => true,
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 10,
            'margin_bottom' => 0,
            'tempDir' => $tempDir,
            'shrink_tables_to_fit' => 0,
        ]);
    }

    private function attendanceFilename(string $editionName): string
    {
        return sprintf('A4 Carton Color - %s Attendance.pdf', $editionName);
    }

    /**
     * @param  array<string, mixed>  $playerA
     * @param  array<string, mixed>  $playerB
     */
    private function pairImagePath(array $playerA, array $playerB, bool $persist): string
    {
        return $this->pairImages->resolvePath(
            (int) $playerA['id'],
            (int) $playerB['id'],
            (string) $playerA['profile_abs_path'],
            (string) $playerB['profile_abs_path'],
            (bool) $playerA['is_collecting_points'],
            (bool) $playerB['is_collecting_points'],
            $persist,
            $playerA['source_player_profile_image_id'] ?? null,
            $playerB['source_player_profile_image_id'] ?? null,
            $playerA['player_updated_at'] ?? null,
            $playerB['player_updated_at'] ?? null,
        );
    }

    /**
     * Mode A MATCHES: resolve the person only through match-player participation.
     *
     * @return array{id: int, name: string, is_collecting_points: bool, profile_abs_path: string, short_name?: string, html_short_name?: string, name_font_size?: int}
     */
    private function playerPayloadFromMatchPlayer(?MatchPlayer $matchPlayer): array
    {
        $participation = $matchPlayer?->participation;
        $player = $participation?->player;
        if ($matchPlayer === null || $participation === null || $player === null) {
            throw new MissingMatchParticipationException();
        }

        $collecting = (bool) $participation->is_collecting_points;

        return $this->playerPayload($player, [(int) $player->id_player => $collecting]);
    }

    /**
     * @param  array<int, bool>  $collectingByPlayerId
     * @return array{id: int, name: string, is_collecting_points: bool, profile_abs_path: string, source_player_profile_image_id: ?int, player_updated_at: ?int, short_name?: string, html_short_name?: string, name_font_size?: int}
     */
    private function playerPayload(?Player $player, array $collectingByPlayerId): array
    {
        if ($player === null) {
            throw new RuntimeException('Match is missing a player slot.');
        }

        $photo = $this->imageGenerator->resolvePhotoForPoster($player, $this->ephemeralTemps);

        $collecting = $collectingByPlayerId[(int) $player->id_player] ?? true;
        $name = (string) $player->name;
        $short = PdfHtmlHelper::shortenPlayerName($name);
        $updatedAt = $player->updated_at;
        $updatedAtInt = ($updatedAt === null || $updatedAt === '') ? null : (int) $updatedAt;

        return [
            'id' => (int) $player->id_player,
            'name' => $name,
            'is_collecting_points' => $collecting,
            'profile_abs_path' => $photo['absolute_path'],
            'source_player_profile_image_id' => $photo['source_player_profile_image_id'],
            'player_updated_at' => $updatedAtInt,
            'short_name' => $short,
            'html_short_name' => PdfHtmlHelper::htmlShortName($short, $collecting),
            'name_font_size' => PdfHtmlHelper::getFittedFontSize($short, 210, $collecting ? 43 : 32),
        ];
    }

    /**
     * @param  list<int>  $playerIds
     * @return Collection<int, Player>
     */
    private function loadPlayersByIds(array $playerIds): Collection
    {
        return Player::query()
            ->with('profileImages')
            ->whereIn('id_player', $playerIds)
            ->get()
            ->keyBy('id_player');
    }

    /**
     * @param  list<int>  $allIds
     * @param  list<int>  $collectingIds
     * @return array<int, bool>
     */
    private function collectingMap(array $allIds, array $collectingIds): array
    {
        $set = array_fill_keys(array_map('intval', $collectingIds), true);
        $map = [];
        foreach ($allIds as $id) {
            $map[(int) $id] = isset($set[(int) $id]);
        }

        return $map;
    }

    /**
     * Mode A branding: organizer from the edition, club host from the edition location.
     *
     * @return array{organizer_image: null|string, club_host_image: null|string, show_edition_label: bool}
     */
    private function editionBranding(?Edition $edition): array
    {
        $organizer = $edition?->organizer;
        $club = (bool) ($edition?->include_club_host_on_pdf ?? false)
            ? $edition?->location?->club
            : null;

        return $this->brandingPayload($organizer, $club);
    }

    /**
     * Mode B branding: explicit organizer/club ids, both optional.
     *
     * @param  array<string, mixed>  $input
     * @return array{organizer_image: null|string, club_host_image: null|string, show_edition_label: bool}
     */
    private function scratchBranding(array $input): array
    {
        $organizerId = $input['organizer_id'] ?? null;
        $clubId = $input['club_id'] ?? null;

        return $this->brandingPayload(
            $organizerId === null ? null : Organizer::query()->find((int) $organizerId),
            $clubId === null ? null : Club::query()->find((int) $clubId),
        );
    }

    /**
     * @return array{organizer_image: null|string, club_host_image: null|string, show_edition_label: bool}
     */
    private function brandingPayload(?Organizer $organizer, ?Club $club): array
    {
        return [
            'organizer_image' => $organizer === null
                ? null
                : $this->logoAbsolutePath($organizer->logo_path, $organizer->storageDisk()),
            'club_host_image' => $club === null
                ? null
                : $this->logoAbsolutePath($club->logo_path, $club->storageDisk()),
            'show_edition_label' => (bool) ($organizer?->show_edition_label ?? false),
        ];
    }

    private function logoAbsolutePath(?string $relative, string $disk): ?string
    {
        if ($relative === null || $relative === '') {
            return null;
        }

        $path = Storage::disk($disk)->path($relative);

        return is_file($path) ? $path : null;
    }

    private function matchesFilename(string $title): string
    {
        return 'A2 Hârtie Color Laminat - ' . $title . ' Matches.pdf';
    }

    private function rankingFilename(string $title): string
    {
        return 'A2 Hârtie Color Laminat - ' . $title . ' Ranking.pdf';
    }

    private function formatTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $clock = PlayingWindow::toClockString($value);
        if ($clock === null) {
            return '';
        }

        return substr($clock, 0, 5);
    }

    private function formatClock(string $value): string
    {
        $clock = PlayingWindow::toClockString($value);

        return $clock !== null ? substr($clock, 0, 5) : trim($value);
    }

    private function minusMinutes(string $time, int $minutes): string
    {
        $dt = \DateTime::createFromFormat('H:i', $this->formatClock($time))
            ?: \DateTime::createFromFormat('H:i:s', $time);
        if ($dt === false) {
            return $this->formatClock($time);
        }
        $dt->modify(sprintf('-%d minutes', $minutes));

        return $dt->format('H:i');
    }
}
