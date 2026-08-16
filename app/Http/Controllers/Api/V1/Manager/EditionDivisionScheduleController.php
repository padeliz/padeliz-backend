<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionDivisionResource;
use App\Http\Resources\EditionParticipationResource;
use App\Http\Resources\GameMatchResource;
use App\Models\CachedPdfPairImage;
use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use App\Models\GameMatch;
use App\Services\Pdf\EditionDivisionPosterInvalidator;
use App\Services\Schedule\EditionDivisionScheduleFormService;
use App\Services\Schedule\PlayingWindow;
use App\Services\Schedule\ScheduleBoardMetricsService;
use App\Services\Schedule\ScheduleGenerationService;
use App\Services\Schedule\ScheduleMatchPersister;
use App\Services\Schedule\ScheduleMatchReorderService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Edition Divisions",
 *     description="(v1) API Endpoints for edition divisions"
 * )
 */
class EditionDivisionScheduleController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function __construct(
        private readonly ScheduleGenerationService $scheduleGenerationService,
        private readonly EditionDivisionScheduleFormService $formService,
        private readonly ScheduleMatchPersister $matchPersister,
        private readonly ScheduleBoardMetricsService $boardMetricsService,
        private readonly EditionDivisionPosterInvalidator $posterInvalidator,
        private readonly ScheduleMatchReorderService $reorderService,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/schedule",
     *     summary="Get schedule form state and persisted matches",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Schedule retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function show(EditionDivision $editionDivision)
    {
        $editionDivision->load([
            'edition',
            'division',
            'courts',
            'editionDivisionCourts.court',
            'participations' => fn ($q) => $q->orderBy('sort_order')->orderBy('id_participation'),
            'participations.player.profileImages',
            'matches' => fn ($q) => $q->orderBy('sequence_no'),
            'matches.editionDivisionCourt.court',
            'matches.sides.players.player',
            'matches.sides.players.participation.player',
        ]);

        $payload = [
            'edition_division' => new EditionDivisionResource($editionDivision),
            'courts' => $editionDivision->courts,
            'participations' => EditionParticipationResource::collection($editionDivision->participations),
            'matches' => GameMatchResource::collection($editionDivision->matches),
            'has_regular_matches' => $this->matchPersister->hasRegularMatches($editionDivision),
            'has_scores' => $this->matchPersister->hasAnyScores($editionDivision),
            'has_standings' => $this->matchPersister->hasStandings($editionDivision),
            'is_finalized' => $editionDivision->edition?->finalized_at !== null,
            'board_metrics' => $this->boardMetricsService->buildMetrics($editionDivision),
        ];

        if (filter_var(request()->query('board'), FILTER_VALIDATE_BOOLEAN)) {
            $payload['players_board'] = $this->boardMetricsService->buildPlayersBoard($editionDivision);
        }

        if (filter_var(request()->query('scores'), FILTER_VALIDATE_BOOLEAN)) {
            $this->attachCoupleImageUrls($editionDivision->matches);
        }

        return $this->successResponse($payload, 'Schedule retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/schedule/form",
     *     summary="Persist schedule form fields (no match generation)",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="playing_start_time", type="string", example="10:00:00"),
     *             @OA\Property(property="playing_end_time", type="string", example="16:00:00"),
     *             @OA\Property(property="player_opponents_nr", type="integer"),
     *             @OA\Property(property="player_matches_repeat", type="integer"),
     *             @OA\Property(property="include_final", type="boolean"),
     *             @OA\Property(property="has_demonstrative_match", type="boolean"),
     *             @OA\Property(property="include_scores", type="boolean"),
     *             @OA\Property(property="allow_replacements", type="boolean"),
     *             @OA\Property(property="points_per_match", type="integer", nullable=true),
     *             @OA\Property(property="final_points_target", type="integer"),
     *             @OA\Property(property="court_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="participation_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="is_collecting_points", type="object", additionalProperties={"type":"boolean"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Schedule form saved"),
     *     @OA\Response(response=409, description="Edition division is finalized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateForm(Request $request, EditionDivision $editionDivision)
    {
        $editionDivision->loadMissing('edition');
        if ($editionDivision->edition?->finalized_at !== null) {
            return $this->errorResponse('Schedule form cannot be updated after finalize.', 409);
        }

        $validated = $this->validateFormPayload($request);

        try {
            $editionDivision = $this->formService->persist($editionDivision, $validated);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to persist schedule form: ' . $e->getMessage(), 409);
        }

        return $this->successResponse([
            'edition_division' => new EditionDivisionResource($editionDivision),
            'participations' => EditionParticipationResource::collection(
                $editionDivision->participations()->orderBy('sort_order')->orderBy('id_participation')->with('player')->get()
            ),
        ], 'Schedule form saved successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/schedule/form",
     *     summary="Partially persist schedule form fields",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Schedule form saved")
     * )
     */
    public function patchForm(Request $request, EditionDivision $editionDivision)
    {
        return $this->updateForm($request, $editionDivision);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/schedule/preview",
     *     summary="Preview schedule without persisting matches",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="participation_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="court_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="playing_start_time", type="string"),
     *             @OA\Property(property="playing_end_time", type="string"),
     *             @OA\Property(property="player_opponents_nr", type="integer"),
     *             @OA\Property(property="player_matches_repeat", type="integer"),
     *             @OA\Property(property="include_final", type="boolean"),
     *             @OA\Property(property="has_demonstrative_match", type="boolean"),
     *             @OA\Property(property="template_version", type="integer", nullable=true),
     *             @OA\Property(property="adjust", type="integer", description="Optional points_per_match adjustment")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Preview generated"),
     *     @OA\Response(response=422, description="Validation / generation error")
     * )
     */
    public function preview(Request $request, EditionDivision $editionDivision)
    {
        $validated = $request->validate([
            'participation_ids' => 'nullable|array|min:1',
            'participation_ids.*' => 'integer|exists:edition_participations,id_participation',
            'court_ids' => 'nullable|array|min:1',
            'court_ids.*' => 'integer|exists:courts,id_court',
            'playing_start_time' => ['nullable', PlayingWindow::clockRegexRule()],
            'playing_end_time' => ['nullable', PlayingWindow::clockRegexRule()],
            'player_opponents_nr' => 'nullable|integer|min:1',
            'player_matches_repeat' => 'nullable|integer|min:1',
            'include_final' => 'nullable|boolean',
            'has_demonstrative_match' => 'nullable|boolean',
            'template_version' => 'nullable|integer|min:1',
            'adjust' => 'nullable|integer',
        ]);

        try {
            $preview = $this->buildPreview($editionDivision, $validated);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($preview, 'Schedule preview generated successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/schedule/generate",
     *     summary="Persist form fields and generate REGULAR matches",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="confirm_replace", type="boolean", description="Required when REGULAR matches already exist"),
     *             @OA\Property(property="adjust", type="integer", description="Optional points_per_match adjustment after calculator"),
     *             @OA\Property(property="template_version", type="integer", nullable=true),
     *             @OA\Property(property="participation_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="court_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Schedule generated"),
     *     @OA\Response(response=409, description="Blocked by scores, standings, finalize, or missing confirm_replace"),
     *     @OA\Response(response=422, description="Validation / generation error")
     * )
     */
    public function generate(Request $request, EditionDivision $editionDivision)
    {
        $editionDivision->loadMissing('edition');
        if ($editionDivision->edition?->finalized_at !== null) {
            return $this->errorResponse('Cannot generate schedule after finalize.', 409);
        }

        if ($this->matchPersister->hasAnyScores($editionDivision)) {
            return $this->errorResponse('Cannot regenerate schedule because one or more matches have scores.', 409);
        }

        if ($this->matchPersister->hasStandings($editionDivision)) {
            return $this->errorResponse('Cannot regenerate schedule because standings already exist.', 409);
        }

        $hasRegular = $this->matchPersister->hasRegularMatches($editionDivision);
        $validated = $request->validate(array_merge($this->formValidationRules(), [
            'confirm_replace' => 'nullable|boolean',
            'adjust' => 'nullable|integer',
            'template_version' => 'nullable|integer|min:1',
        ]));

        if ($hasRegular && empty($validated['confirm_replace'])) {
            return $this->errorResponse(
                'Schedule already exists. Pass confirm_replace=true to replace REGULAR matches.',
                409,
                ['confirm_replace_required' => true]
            );
        }

        try {
            $result = DB::transaction(function () use ($editionDivision, $validated) {
                if ($this->matchPersister->hasRegularMatches($editionDivision)) {
                    $this->matchPersister->deleteRegularMatches($editionDivision);
                }

                $editionDivision = $this->formService->persist($editionDivision, $validated);
                $preview = $this->buildPreview($editionDivision, [
                    'template_version' => $validated['template_version'] ?? null,
                    'adjust' => $validated['adjust'] ?? null,
                ]);

                $participations = EditionParticipation::query()
                    ->where('edition_division_id', $editionDivision->id_edition_division)
                    ->orderBy('sort_order')
                    ->orderBy('id_participation')
                    ->get();

                $byPlayerId = [];
                foreach ($participations as $participation) {
                    $byPlayerId[(int) $participation->player_id] = $participation;
                }

                $matches = $this->matchPersister->persistRegularMatches($editionDivision, $preview, $byPlayerId);

                $editionDivision->update([
                    'points_per_match' => (int) $preview['points_per_match'],
                    'template_version' => (int) $preview['template_version'],
                ]);

                $this->posterInvalidator->clearMatchesAndRanking($editionDivision);

                $editionDivision->load([
                    'courts',
                    'participations' => fn ($q) => $q->orderBy('sort_order')->orderBy('id_participation'),
                    'participations.player',
                    'matches' => fn ($q) => $q->where('match_kind', 'REGULAR')->orderBy('sequence_no'),
                    'matches.editionDivisionCourt.court',
                    'matches.sides.players.player',
                    'matches.sides.players.participation',
                ]);

                return [
                    'edition_division' => $editionDivision,
                    'preview' => $preview,
                    'matches' => $editionDivision->matches,
                    'created_count' => count($matches),
                ];
            });
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to generate schedule: ' . $e->getMessage(), 422);
        }

        return $this->successResponse([
            'edition_division' => new EditionDivisionResource($result['edition_division']),
            'points_per_match' => $result['preview']['points_per_match'],
            'preview' => $result['preview'],
            'matches' => GameMatchResource::collection($result['matches']),
            'created_count' => $result['created_count'],
        ], 'Schedule generated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/schedule",
     *     summary="Delete REGULAR matches when eligible",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Schedule deleted"),
     *     @OA\Response(response=409, description="Blocked by scores, standings, or finalize")
     * )
     */
    public function destroy(EditionDivision $editionDivision)
    {
        $editionDivision->loadMissing('edition');
        if ($editionDivision->edition?->finalized_at !== null) {
            return $this->errorResponse('Cannot delete schedule after finalize.', 409);
        }

        if ($this->matchPersister->hasAnyScores($editionDivision)) {
            return $this->errorResponse('Cannot delete schedule because one or more matches have scores.', 409);
        }

        if ($this->matchPersister->hasStandings($editionDivision)) {
            return $this->errorResponse('Cannot delete schedule because standings already exist.', 409);
        }

        $deleted = $this->matchPersister->deleteRegularMatches($editionDivision);
        $editionDivision->update(['template_version' => null]);
        $this->posterInvalidator->clearMatches($editionDivision);

        return $this->successResponse(
            ['deleted_regular_matches' => $deleted],
            'Schedule deleted successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/schedule/matches/reorder",
     *     summary="Reorder complete REGULAR rounds across courts and recompute clocks",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"court", "match_ids"},
     *             @OA\Property(property="court", type="integer", description="edition_division_court id"),
     *             @OA\Property(property="match_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Matches reordered"),
     *     @OA\Response(response=409, description="Blocked by scores, standings, or finalize"),
     *     @OA\Response(response=422, description="Invalid complete-round set or sparse id")
     * )
     */
    public function reorderMatches(Request $request, EditionDivision $editionDivision)
    {
        $editionDivision->loadMissing('edition');
        if ($editionDivision->edition?->finalized_at !== null) {
            return $this->errorResponse('Cannot reorder matches after finalize.', 409);
        }

        if ($this->matchPersister->hasAnyScores($editionDivision)) {
            return $this->errorResponse('Cannot reorder matches because one or more matches have scores.', 409);
        }

        if ($this->matchPersister->hasStandings($editionDivision)) {
            return $this->errorResponse('Cannot reorder matches because standings already exist.', 409);
        }

        $validated = $request->validate([
            'court' => 'required|integer|exists:edition_division_courts,id_edition_division_court',
            'match_ids' => 'required|array|min:1',
            'match_ids.*' => 'integer|exists:matches,id_match',
        ]);

        try {
            $result = $this->reorderService->reorder(
                $editionDivision,
                (int) $validated['court'],
                array_values(array_map('intval', $validated['match_ids'])),
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to reorder matches: ' . $e->getMessage(), 422);
        }

        $editionDivision->load([
            'courts',
            'participations' => fn ($q) => $q->orderBy('sort_order')->orderBy('id_participation'),
            'participations.player',
            'matches' => fn ($q) => $q->where('match_kind', 'REGULAR')->orderBy('sequence_no'),
            'matches.sides.players.participation',
        ]);

        return $this->successResponse([
            'matches' => GameMatchResource::collection($result['matches']),
            'board_metrics' => $this->boardMetricsService->buildMetrics($editionDivision),
        ], 'Matches reordered successfully');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFormPayload(Request $request): array
    {
        return $request->validate($this->formValidationRules());
    }

    /**
     * @return array<string, string>
     */
    private function formValidationRules(): array
    {
        return [
            'playing_start_time' => ['nullable', PlayingWindow::clockRegexRule()],
            'playing_end_time' => ['nullable', PlayingWindow::clockRegexRule()],
            'player_opponents_nr' => 'nullable|integer|min:1',
            'player_matches_repeat' => 'nullable|integer|min:1',
            'include_final' => 'nullable|boolean',
            'has_demonstrative_match' => 'nullable|boolean',
            'include_scores' => 'nullable|boolean',
            'allow_replacements' => 'nullable|boolean',
            'points_per_match' => 'nullable|integer|min:0',
            'final_points_target' => 'nullable|integer|min:0',
            'court_ids' => 'nullable|array',
            'court_ids.*' => 'integer|exists:courts,id_court',
            'participation_ids' => 'nullable|array',
            'participation_ids.*' => 'integer|exists:edition_participations,id_participation',
            'is_collecting_points' => 'nullable|array',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function buildPreview(EditionDivision $editionDivision, array $overrides): array
    {
        $editionDivision->loadMissing(['courts', 'participations']);

        if (! empty($overrides['participation_ids'])) {
            $participations = EditionParticipation::query()
                ->where('edition_division_id', $editionDivision->id_edition_division)
                ->whereIn('id_participation', $overrides['participation_ids'])
                ->get()
                ->sortBy(fn ($p) => array_search((int) $p->id_participation, array_map('intval', $overrides['participation_ids']), true))
                ->values();
        } else {
            $participations = $editionDivision->participations()
                ->orderBy('sort_order')
                ->orderBy('id_participation')
                ->get();
        }

        if ($participations->isEmpty()) {
            throw new InvalidArgumentException('Edition division has no participations to schedule.');
        }

        $playerIds = $participations->pluck('player_id')->map(static fn ($id): int => (int) $id)->all();

        if (! empty($overrides['court_ids'])) {
            $courtNames = [];
            foreach ($overrides['court_ids'] as $courtId) {
                $court = $editionDivision->courts->firstWhere('id_court', (int) $courtId);
                if ($court === null) {
                    $court = \App\Models\Court::query()->find((int) $courtId);
                }
                if ($court === null) {
                    throw new InvalidArgumentException(sprintf('Court %d not found.', $courtId));
                }
                $courtNames[] = (string) $court->name;
            }
        } else {
            $courtNames = $editionDivision->courts()
                ->orderByPivot('display_order')
                ->get()
                ->pluck('name')
                ->map(static fn ($name): string => (string) $name)
                ->all();
        }

        if ($courtNames === []) {
            throw new InvalidArgumentException('At least one court is required for schedule preview.');
        }

        $timeStart = $this->toHi($overrides['playing_start_time'] ?? $editionDivision->formatted_playing_start_time);
        $timeEnd = $this->toHi($overrides['playing_end_time'] ?? $editionDivision->formatted_playing_end_time);
        $opponents = (int) ($overrides['player_opponents_nr'] ?? $editionDivision->player_opponents_nr ?? 0);
        $repeat = (int) ($overrides['player_matches_repeat'] ?? $editionDivision->player_matches_repeat ?? 1);
        $includeFinal = (bool) ($overrides['include_final'] ?? $editionDivision->include_final);
        $hasDemo = (bool) ($overrides['has_demonstrative_match'] ?? $editionDivision->has_demonstrative_match);

        if ($opponents < 1) {
            throw new InvalidArgumentException('player_opponents_nr must be >= 1.');
        }

        $preview = $this->scheduleGenerationService->preview(
            $playerIds,
            $opponents,
            $repeat,
            $courtNames,
            $timeStart,
            $timeEnd,
            $includeFinal,
            $hasDemo,
            isset($overrides['template_version']) ? (int) $overrides['template_version'] : null,
        );

        if (isset($overrides['adjust'])) {
            $preview['points_per_match'] = max(0, (int) $preview['points_per_match'] + (int) $overrides['adjust']);
        }

        return $preview;
    }

    /**
     * Cache-only couple URLs for Scores (no GD compose).
     *
     * @param  Collection<int, GameMatch>  $matches
     */
    private function attachCoupleImageUrls(Collection $matches): void
    {
        foreach ($matches as $match) {
            foreach ($match->sides as $side) {
                $players = $side->relationLoaded('players')
                    ? $side->players->values()
                    : collect();
                if ($players->count() < 2) {
                    $side->setAttribute('couple_image_url', null);

                    continue;
                }

                $a = $players[0];
                $b = $players[1];
                $idA = (int) ($a->player_id ?? $a->player?->id_player ?? 0);
                $idB = (int) ($b->player_id ?? $b->player?->id_player ?? 0);
                if ($idA < 1 || $idB < 1) {
                    $side->setAttribute('couple_image_url', null);

                    continue;
                }

                $collectA = (bool) ($a->participation?->is_collecting_points ?? true);
                $collectB = (bool) ($b->participation?->is_collecting_points ?? true);

                if ($idA <= $idB) {
                    $p1 = $idA;
                    $p2 = $idB;
                    $c1 = $collectA;
                    $c2 = $collectB;
                } else {
                    $p1 = $idB;
                    $p2 = $idA;
                    $c1 = $collectB;
                    $c2 = $collectA;
                }

                $cached = CachedPdfPairImage::query()
                    ->where('player_1_id', $p1)
                    ->where('player_2_id', $p2)
                    ->where('player_1_is_collecting_points', $c1)
                    ->where('player_2_is_collecting_points', $c2)
                    ->first();

                $side->setAttribute('couple_image_url', $cached?->url);
            }
        }
    }

    private function toHi(?string $time): string
    {
        if ($time === null || $time === '') {
            throw new InvalidArgumentException('Playing start/end time is required.');
        }

        $clock = PlayingWindow::toClockString($time);
        if ($clock === null || $clock === '') {
            throw new InvalidArgumentException('Playing start/end time is required.');
        }

        return substr($clock, 0, 5);
    }
}
