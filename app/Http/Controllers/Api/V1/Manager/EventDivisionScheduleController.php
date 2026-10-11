<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EventDivisionResource;
use App\Http\Resources\EventDivisionScheduleResource;
use App\Http\Resources\EventParticipationResource;
use App\Http\Resources\GameMatchResource;
use App\Models\CachedPdfPairImage;
use App\Models\EventDivision;
use App\Models\EventDivisionSchedule;
use App\Models\EventParticipation;
use App\Services\Cache\EventDivisionCacheInvalidator;
use App\Services\Cache\ScheduleReadCache;
use App\Services\Pdf\CachedPdfAvatarAttacher;
use App\Services\Pdf\EventDivisionPosterInvalidator;
use App\Services\Schedule\EventDivisionScheduleFormService;
use App\Services\Schedule\PointsPerMatchCalculator;
use App\Services\Schedule\ScheduleBoardMetricsService;
use App\Services\Schedule\ScheduleGenerationService;
use App\Services\Schedule\ScheduleMatchPersister;
use App\Services\Schedule\ScheduleMatchReorderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Throwable;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Divisions",
 *     description="(v1) API Endpoints for edition divisions"
 * )
 */
class EventDivisionScheduleController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function __construct(
        private readonly ScheduleGenerationService $scheduleGenerationService,
        private readonly EventDivisionScheduleFormService $formService,
        private readonly ScheduleMatchPersister $matchPersister,
        private readonly ScheduleBoardMetricsService $boardMetricsService,
        private readonly EventDivisionPosterInvalidator $posterInvalidator,
        private readonly ScheduleMatchReorderService $reorderService,
        private readonly ScheduleReadCache $scheduleReadCache,
        private readonly EventDivisionCacheInvalidator $cacheInvalidator,
        private readonly CachedPdfAvatarAttacher $pdfAvatarAttacher,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/schedule",
     *     summary="Get schedule form state and persisted matches",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Schedule retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function show(EventDivision $eventDivision)
    {
        $board = filter_var(request()->query('board'), FILTER_VALIDATE_BOOLEAN);
        $scores = filter_var(request()->query('scores'), FILTER_VALIDATE_BOOLEAN);

        $payload = $this->scheduleReadCache->rememberScheduleShow(
            (int) $eventDivision->id_event_division,
            $board,
            $scores,
            fn () => $this->buildScheduleShowPayload($eventDivision, $board, $scores),
        );

        if ($scores) {
            $this->overlayCoupleImageUrlsOnPayload($payload);
        }

        $this->overlayParticipationPdfAvatarsOnSchedulePayload($payload);

        return $this->successResponse($payload, 'Schedule retrieved successfully');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildScheduleShowPayload(EventDivision $eventDivision, bool $board, bool $scores): array
    {
        $eventDivision->load([
            'event',
            'division',
            'courts',
            'eventDivisionCourts.court',
            'participations' => fn ($q) => $q->orderBy('sort_order')->orderBy('id_participation'),
            'participations.player.profileImages',
            'matches' => fn ($q) => $q->orderBy('sequence_no'),
            'matches.eventDivisionCourt.court',
            'matches.sides.players.player',
            'matches.sides.players.participation.player',
        ]);

        $schedule = $eventDivision->schedule()->first();

        $payload = [
            'event_division' => (new EventDivisionResource($eventDivision))->resolve(),
            'schedule' => $schedule !== null ? (new EventDivisionScheduleResource($schedule))->resolve() : null,
            'courts' => $eventDivision->courts->toArray(),
            'participations' => EventParticipationResource::collection($eventDivision->participations)->resolve(),
            'matches' => json_decode(
                GameMatchResource::collection($eventDivision->matches)->toJson(),
                true
            ) ?? [],
            'has_regular_matches' => $this->matchPersister->hasRegularMatches($eventDivision),
            'has_scores' => $this->matchPersister->hasAnyScores($eventDivision),
            'has_standings' => $this->matchPersister->hasStandings($eventDivision),
            'is_finalized' => $eventDivision->event?->finalized_at !== null,
            'board_metrics' => $this->boardMetricsService->buildMetrics($eventDivision),
        ];

        if ($board) {
            $payload['players_board'] = $this->boardMetricsService->buildPlayersBoard($eventDivision);
        }

        return $payload;
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/schedule/form",
     *     summary="Persist schedule form fields (no match generation)",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="player_opponents_nr", type="integer"),
     *             @OA\Property(property="player_matches_repeat", type="integer"),
     *             @OA\Property(property="include_final", type="boolean"),
     *             @OA\Property(property="has_demonstrative_match", type="boolean"),
     *             @OA\Property(property="include_scores", type="boolean"),
     *             @OA\Property(property="allow_replacements", type="boolean"),
     *             @OA\Property(property="points_per_match", type="integer", nullable=true),
     *             @OA\Property(property="final_points_target", type="integer"),
     *             @OA\Property(property="adjust", type="integer", description="One of -6,-4,-2,0,2,4,6"),
     *             @OA\Property(property="court_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="participation_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="is_collecting_points", type="object", additionalProperties={"type":"boolean"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Schedule form saved"),
     *     @OA\Response(response=409, description="PadelEvent division is finalized"),
     *     @OA\Response(response=422, description="Validation error or playing times sent")
     * )
     */
    public function updateForm(Request $request, EventDivision $eventDivision)
    {
        $eventDivision->loadMissing('event');
        if ($eventDivision->event?->finalized_at !== null) {
            return $this->errorResponse('Schedule form cannot be updated after finalize.', 409);
        }

        $rejected = $this->rejectPlayingTimes($request);
        if ($rejected !== null) {
            return $rejected;
        }

        $validated = $this->validateFormPayload($request);

        try {
            $eventDivision = $this->formService->persist($eventDivision, $validated);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to persist schedule form: ' . $e->getMessage(), 409);
        }

        $schedule = $eventDivision->schedule()->first();

        $participations = $eventDivision->participations()
            ->orderBy('sort_order')
            ->orderBy('id_participation')
            ->with('player')
            ->get();
        $this->pdfAvatarAttacher->hydrateParticipations($participations);

        return $this->successResponse([
            'event_division' => new EventDivisionResource($eventDivision),
            'schedule' => $schedule !== null ? new EventDivisionScheduleResource($schedule) : null,
            'participations' => EventParticipationResource::collection($participations),
        ], 'Schedule form saved successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/schedule/form",
     *     summary="Partially persist schedule form fields",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Schedule form saved")
     * )
     */
    public function patchForm(Request $request, EventDivision $eventDivision)
    {
        return $this->updateForm($request, $eventDivision);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/schedule/preview",
     *     summary="Preview schedule without persisting matches",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="participation_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="court_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="player_opponents_nr", type="integer"),
     *             @OA\Property(property="player_matches_repeat", type="integer"),
     *             @OA\Property(property="include_final", type="boolean"),
     *             @OA\Property(property="has_demonstrative_match", type="boolean"),
     *             @OA\Property(property="template_version", type="integer", nullable=true),
     *             @OA\Property(property="adjust", type="integer", description="One of -6,-4,-2,0,2,4,6; ignored after finalize")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Preview generated"),
     *     @OA\Response(response=422, description="Validation / generation error")
     * )
     */
    public function preview(Request $request, EventDivision $eventDivision)
    {
        $rejected = $this->rejectPlayingTimes($request);
        if ($rejected !== null) {
            return $rejected;
        }

        $validated = $request->validate([
            'participation_ids' => 'nullable|array|min:1',
            'participation_ids.*' => 'integer|exists:event_participations,id_participation',
            'court_ids' => 'nullable|array|min:1',
            'court_ids.*' => 'integer|exists:courts,id_court',
            'player_opponents_nr' => 'nullable|integer|min:1',
            'player_matches_repeat' => 'nullable|integer|min:1',
            'include_final' => 'nullable|boolean',
            'has_demonstrative_match' => 'nullable|boolean',
            'template_version' => 'nullable|integer|min:1',
            'adjust' => ['nullable', 'integer', Rule::in(EventDivisionSchedule::ADJUST_VALUES)],
        ]);

        $eventDivision->loadMissing('event');
        $isFinalized = $eventDivision->event?->finalized_at !== null;

        try {
            if ($isFinalized) {
                // Finalized editions are frozen: no schedule upsert and no adjust override.
                $schedule = $eventDivision->requireSchedule();
            } else {
                $eventDivision = $this->formService->persist(
                    $eventDivision,
                    $this->scheduleKnobs($validated)
                );
                $schedule = $eventDivision->requireSchedule();
            }

            $preview = $this->buildPreview($eventDivision, $validated, $schedule);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($preview, 'Schedule preview generated successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/schedule/generate",
     *     summary="Persist form fields and generate REGULAR matches",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="confirm_replace", type="boolean", description="Required when REGULAR matches already exist"),
     *             @OA\Property(property="adjust", type="integer", description="One of -6,-4,-2,0,2,4,6; applied after the calculator"),
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
    public function generate(Request $request, EventDivision $eventDivision)
    {
        $eventDivision->loadMissing('event');
        if ($eventDivision->event?->finalized_at !== null) {
            return $this->errorResponse('Cannot generate schedule after finalize.', 409);
        }

        if ($this->matchPersister->hasAnyScores($eventDivision)) {
            return $this->errorResponse('Cannot regenerate schedule because one or more matches have scores.', 409);
        }

        if ($this->matchPersister->hasStandings($eventDivision)) {
            return $this->errorResponse('Cannot regenerate schedule because standings already exist.', 409);
        }

        $hasRegular = $this->matchPersister->hasRegularMatches($eventDivision);
        $validated = $request->validate(array_merge($this->formValidationRules(), [
            'confirm_replace' => 'nullable|boolean',
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
            $result = DB::transaction(function () use ($eventDivision, $validated) {
                if ($this->matchPersister->hasRegularMatches($eventDivision)) {
                    $this->matchPersister->deleteRegularMatches($eventDivision);
                }

                $eventDivision = $this->formService->persist($eventDivision, $validated);
                $schedule = $eventDivision->requireSchedule();
                $preview = $this->buildPreview($eventDivision, [
                    'template_version' => $validated['template_version'] ?? null,
                ], $schedule);

                $participations = EventParticipation::query()
                    ->where('event_division_id', $eventDivision->id_event_division)
                    ->orderBy('sort_order')
                    ->orderBy('id_participation')
                    ->get();

                $byPlayerId = [];
                foreach ($participations as $participation) {
                    $byPlayerId[(int) $participation->player_user_id] = $participation;
                }

                $matches = $this->matchPersister->persistRegularMatches($eventDivision, $preview, $byPlayerId);

                $schedule->update([
                    'points_per_match' => (int) $preview['points_per_match'],
                    'template_version' => (int) $preview['template_version'],
                ]);

                $this->posterInvalidator->clearMatchesAndRanking($eventDivision);
                $this->cacheInvalidator->invalidateSchedule((int) $eventDivision->id_event_division);

                $eventDivision->load([
                    'schedule',
                    'courts',
                    'participations' => fn ($q) => $q->orderBy('sort_order')->orderBy('id_participation'),
                    'participations.player',
                    'matches' => fn ($q) => $q->where('match_kind', 'REGULAR')->orderBy('sequence_no'),
                    'matches.eventDivisionCourt.court',
                    'matches.sides.players.player',
                    'matches.sides.players.participation',
                ]);

                return [
                    'event_division' => $eventDivision,
                    'schedule' => $schedule->fresh(),
                    'preview' => $preview,
                    'matches' => $eventDivision->matches,
                    'created_count' => count($matches),
                ];
            });
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to generate schedule: ' . $e->getMessage(), 422);
        }

        return $this->successResponse([
            'event_division' => new EventDivisionResource($result['event_division']),
            'schedule' => new EventDivisionScheduleResource($result['schedule']),
            'points_per_match' => $result['preview']['points_per_match'],
            'preview' => $result['preview'],
            'matches' => GameMatchResource::collection($result['matches']),
            'created_count' => $result['created_count'],
        ], 'Schedule generated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/schedule",
     *     summary="Delete REGULAR matches when eligible",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Schedule deleted"),
     *     @OA\Response(response=409, description="Blocked by scores, standings, or finalize")
     * )
     */
    public function destroy(EventDivision $eventDivision)
    {
        $eventDivision->loadMissing('event');
        if ($eventDivision->event?->finalized_at !== null) {
            return $this->errorResponse('Cannot delete schedule after finalize.', 409);
        }

        if ($this->matchPersister->hasAnyScores($eventDivision)) {
            return $this->errorResponse('Cannot delete schedule because one or more matches have scores.', 409);
        }

        if ($this->matchPersister->hasStandings($eventDivision)) {
            return $this->errorResponse('Cannot delete schedule because standings already exist.', 409);
        }

        $deleted = $this->matchPersister->deleteRegularMatches($eventDivision);
        // The schedule row itself survives; only the generated template identity is dropped.
        $eventDivision->schedule()->first()?->update(['template_version' => null]);
        $this->posterInvalidator->clearMatches($eventDivision);
        $this->cacheInvalidator->invalidateSchedule((int) $eventDivision->id_event_division);

        return $this->successResponse(
            ['deleted_regular_matches' => $deleted],
            'Schedule deleted successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/schedule/matches/reorder",
     *     summary="Reorder complete REGULAR rounds across courts and recompute clocks",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"court", "match_ids"},
     *             @OA\Property(property="court", type="integer", description="event_division_court id"),
     *             @OA\Property(property="match_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Matches reordered"),
     *     @OA\Response(response=409, description="Blocked by scores, standings, or finalize"),
     *     @OA\Response(response=422, description="Invalid complete-round set or sparse id")
     * )
     */
    public function reorderMatches(Request $request, EventDivision $eventDivision)
    {
        $eventDivision->loadMissing('event');
        if ($eventDivision->event?->finalized_at !== null) {
            return $this->errorResponse('Cannot reorder matches after finalize.', 409);
        }

        if ($this->matchPersister->hasAnyScores($eventDivision)) {
            return $this->errorResponse('Cannot reorder matches because one or more matches have scores.', 409);
        }

        if ($this->matchPersister->hasStandings($eventDivision)) {
            return $this->errorResponse('Cannot reorder matches because standings already exist.', 409);
        }

        $validated = $request->validate([
            'court' => 'required|integer|exists:event_division_courts,id_event_division_court',
            'match_ids' => 'required|array|min:1',
            'match_ids.*' => 'integer|exists:matches,id_match',
        ]);

        try {
            $result = $this->reorderService->reorder(
                $eventDivision,
                (int) $validated['court'],
                array_values(array_map('intval', $validated['match_ids'])),
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to reorder matches: ' . $e->getMessage(), 422);
        }

        $eventDivision->load([
            'courts',
            'participations' => fn ($q) => $q->orderBy('sort_order')->orderBy('id_participation'),
            'participations.player',
            'matches' => fn ($q) => $q->where('match_kind', 'REGULAR')->orderBy('sequence_no'),
            'matches.sides.players.participation',
        ]);

        return $this->successResponse([
            'matches' => GameMatchResource::collection($result['matches']),
            'board_metrics' => $this->boardMetricsService->buildMetrics($eventDivision),
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
     * @return array<string, mixed>
     */
    private function formValidationRules(): array
    {
        return [
            'player_opponents_nr' => 'nullable|integer|min:1',
            'player_matches_repeat' => 'nullable|integer|min:1',
            'include_final' => 'nullable|boolean',
            'has_demonstrative_match' => 'nullable|boolean',
            'include_scores' => 'nullable|boolean',
            'allow_replacements' => 'nullable|boolean',
            'points_per_match' => 'nullable|integer|min:0',
            'final_points_target' => 'nullable|integer|min:0',
            'adjust' => ['nullable', 'integer', Rule::in(EventDivisionSchedule::ADJUST_VALUES)],
            'court_ids' => 'nullable|array',
            'court_ids.*' => 'integer|exists:courts,id_court',
            'participation_ids' => 'nullable|array',
            'participation_ids.*' => 'integer|exists:event_participations,id_participation',
            'is_collecting_points' => 'nullable|array',
        ];
    }

    /**
     * Playing times belong to the edition division and are rejected on schedule endpoints.
     */
    private function rejectPlayingTimes(Request $request)
    {
        foreach (['playing_start_time', 'playing_end_time', 'playing_starts_at', 'playing_ends_at', 'check_in_at'] as $key) {
            if ($request->has($key)) {
                return $this->errorResponse(
                    'Playing times are managed on the edition division (playing_starts_at / playing_ends_at), not on the schedule.',
                    422
                );
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function scheduleKnobs(array $validated): array
    {
        return array_intersect_key($validated, array_flip([
            'player_opponents_nr',
            'player_matches_repeat',
            'include_final',
            'has_demonstrative_match',
            'adjust',
        ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function buildPreview(
        EventDivision $eventDivision,
        array $overrides,
        EventDivisionSchedule $schedule,
    ): array {
        $eventDivision->loadMissing(['courts', 'participations']);

        if (! empty($overrides['participation_ids'])) {
            $participations = EventParticipation::query()
                ->where('event_division_id', $eventDivision->id_event_division)
                ->whereIn('id_participation', $overrides['participation_ids'])
                ->get()
                ->sortBy(fn ($p) => array_search((int) $p->id_participation, array_map('intval', $overrides['participation_ids']), true))
                ->values();
        } else {
            $participations = $eventDivision->participations()
                ->orderBy('sort_order')
                ->orderBy('id_participation')
                ->get();
        }

        if ($participations->isEmpty()) {
            throw new InvalidArgumentException('PadelEvent division has no participations to schedule.');
        }

        $playerIds = $participations->pluck('player_user_id')->map(static fn ($id): int => (int) $id)->all();

        if (! empty($overrides['court_ids'])) {
            $courtNames = [];
            foreach ($overrides['court_ids'] as $courtId) {
                $court = $eventDivision->courts->firstWhere('id_court', (int) $courtId);
                if ($court === null) {
                    $court = \App\Models\Court::query()->find((int) $courtId);
                }
                if ($court === null) {
                    throw new InvalidArgumentException(sprintf('Court %d not found.', $courtId));
                }
                $courtNames[] = (string) $court->name;
            }
        } else {
            $courtNames = $eventDivision->courts()
                ->orderByPivot('display_order')
                ->get()
                ->pluck('name')
                ->map(static fn ($name): string => (string) $name)
                ->all();
        }

        if ($courtNames === []) {
            throw new InvalidArgumentException('At least one court is required for schedule preview.');
        }

        $timeStart = $this->playingClockHi((int) ($eventDivision->playing_starts_at ?? 0));
        $timeEnd = $this->playingClockHi((int) ($eventDivision->playing_ends_at ?? 0));
        $opponents = (int) ($schedule->player_opponents_nr ?? 0);
        $repeat = (int) ($schedule->player_matches_repeat ?? 1);
        $includeFinal = (bool) $schedule->include_final;
        $hasDemo = (bool) $schedule->has_demonstrative_match;

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

        return PointsPerMatchCalculator::finalizePreviewPoints(
            $preview,
            (int) ($schedule->adjust ?? 0),
        );
    }

    /**
     * Attach participation PDF avatars after schedule cache read (avatars change out-of-band).
     *
     * @param  array<string, mixed>  $payload
     */
    private function overlayParticipationPdfAvatarsOnSchedulePayload(array &$payload): void
    {
        $participations = $payload['participations'] ?? null;
        if (! is_array($participations)) {
            return;
        }

        $this->pdfAvatarAttacher->overlayParticipationsOnArray($participations);
        $payload['participations'] = $participations;
    }

    /**
     * Attach couple URLs for Scores after schedule cache read (pair images change out-of-band).
     *
     * @param  array<string, mixed>  $payload
     */
    private function overlayCoupleImageUrlsOnPayload(array &$payload): void
    {
        $matches = $payload['matches'] ?? null;

        if (! is_array($matches)) {
            return;
        }

        foreach ($matches as &$match) {
            if (! is_array($match) || ! isset($match['sides']) || ! is_array($match['sides'])) {
                continue;
            }

            foreach ($match['sides'] as &$side) {
                if (! is_array($side)) {
                    continue;
                }

                $side['couple_image_url'] = $this->resolveCoupleImageUrlForSide($side);
            }
            unset($side);
        }
        unset($match);

        $payload['matches'] = $matches;
    }

    /**
     * @param  array<string, mixed>  $side
     */
    private function resolveCoupleImageUrlForSide(array $side): ?string
    {
        $players = $side['players'] ?? null;

        if (! is_array($players) || count($players) < 2) {
            return null;
        }

        $players = array_values($players);
        $a = $players[0];
        $b = $players[1];

        if (! is_array($a) || ! is_array($b)) {
            return null;
        }

        $idA = (int) ($a['player_user_id'] ?? $a['player']['id_user'] ?? 0);
        $idB = (int) ($b['player_user_id'] ?? $b['player']['id_user'] ?? 0);

        if ($idA < 1 || $idB < 1) {
            return null;
        }

        $collectA = (bool) ($a['participation']['is_collecting_points'] ?? true);
        $collectB = (bool) ($b['participation']['is_collecting_points'] ?? true);

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
            ->where('player_1_user_id', $p1)
            ->where('player_2_user_id', $p2)
            ->where('player_1_is_collecting_points', $c1)
            ->where('player_2_is_collecting_points', $c2)
            ->first();

        return $cached?->url;
    }

    /**
     * Mode A adapter: schedule generation still consumes local H:i clocks.
     */
    private function playingClockHi(int $unix): string
    {
        if ($unix < 1) {
            throw new InvalidArgumentException('Playing start/end time is required.');
        }

        $tz = (string) config('app.timezone', 'Europe/Bucharest');

        return Carbon::createFromTimestamp($unix, $tz)->format('H:i');
    }
}
