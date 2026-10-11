<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\EventParticipationResource;
use App\Models\{EventDivision, EventParticipation, PadelEvent, Player};
use App\Services\EventParticipationFlagService;
use App\Services\EventParticipationLotteryGuard;
use App\Services\Pdf\AttendancePosterInvalidation;
use App\Services\Pdf\CachedPdfAvatarAttacher;
use App\Services\Pdf\EventDivisionPosterInvalidator;
use App\Services\PlayerCustomField\PlayerCustomFieldParticipationResolver;
use App\Services\PlayerEventHistoryService;
use App\Traits\CachesManagerResourceReads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Participations",
 *     description="(v1) PadelEvent participation management and operations"
 * )
 */
class EventParticipationController extends V1BaseController
{
    use CachesManagerResourceReads;

    public function __construct(
        private readonly EventDivisionPosterInvalidator $posterInvalidator,
        private readonly AttendancePosterInvalidation $attendanceInvalidation,
        private readonly PlayerCustomFieldParticipationResolver $customFieldResolver,
        private readonly PlayerEventHistoryService $playerEditionHistory,
        private readonly EventParticipationFlagService $participationFlags,
        private readonly EventParticipationLotteryGuard $lotteryGuard,
        private readonly CachedPdfAvatarAttacher $pdfAvatarAttacher,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-participations",
     *     summary="Get all edition participations",
     *     tags={"🐧 Manager: PadelEvent Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term for absence_reason, ban_reason fields",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         description="Filter parameters",
     *         required=false,
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(
     *             type="object",
     *             additionalProperties=false,
     *             @OA\Property(property="event_id", type="string", description="Filter by edition ID"),
     *             @OA\Property(property="player_user_id", type="string", description="Filter by player ID"),
     *             @OA\Property(property="event_division_id", type="string", description="Filter by edition division ID"),
     *             @OA\Property(property="sort_order", type="string", description="Filter by sort order"),
     *             @OA\Property(property="is_collecting_points", type="string", description="Filter by collecting points (use 0 or 1)")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(
     *             type="object",
     *             additionalProperties=false,
     *             @OA\Property(property="id_participation", type="string", enum={"asc", "desc"}, description="Sort by participation ID"),
     *             @OA\Property(property="event_id", type="string", enum={"asc", "desc"}, description="Sort by edition ID"),
     *             @OA\Property(property="player_user_id", type="string", enum={"asc", "desc"}, description="Sort by player ID"),
     *             @OA\Property(property="event_division_id", type="string", enum={"asc", "desc"}, description="Sort by edition division ID"),
     *             @OA\Property(property="sort_order", type="string", enum={"asc", "desc"}, description="Sort by sort order"),
     *             @OA\Property(property="is_collecting_points", type="string", enum={"asc", "desc"}, description="Sort by collecting points"),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}, description="Sort by creation date"),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"}, description="Sort by update date")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/EventParticipation")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function index(Request $request)
    {
        $payload = $this->cachedManagerIndex('event-participations', $request, ['event_id'], function () use ($request) {
            $query = EventParticipation::with(['player', 'eventDivision', 'event']);

            $this->applyAdvancedPagination(
                $query,
                $request,
                EventParticipation::$searchableFields,
                EventParticipation::$filterableFields,
                EventParticipation::$sortableFields
            );

            $paginationParams = $this->getPaginationParams($request);

            if (! $request->has('sort_by')) {
                $query->orderBy('id_participation', 'desc');
            }

            $eventParticipations = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

            $filters = $request->input('filters', []);
            $editionId = isset($filters['event_id']) ? (int) $filters['event_id'] : 0;
            $editionSummary = null;
            if ($editionId > 0) {
                $event = PadelEvent::query()->find($editionId);
                if ($event !== null) {
                    $histories = $this->playerEditionHistory->forPlayers(
                        $eventParticipations->getCollection()->pluck('player_user_id')->all(),
                        $this->playerEditionHistory->cutoffDateForEdition($event)
                    );
                    $flagPayload = $this->participationFlags->forEventParticipations(
                        $editionId,
                        $eventParticipations->getCollection()
                    );
                    $editionSummary = $flagPayload['event_summary'];
                    $flagsById = $flagPayload['flags_by_participation_id'];
                    $customFieldIds = $this->parseCustomFieldIds($request, $editionId);
                    $customFieldsByPlayer = $customFieldIds !== []
                        ? $this->customFieldResolver->resolveForPlayers(
                            $editionId,
                            $eventParticipations->getCollection()->pluck('player_user_id')->map(static fn ($id): int => (int) $id)->all(),
                            $customFieldIds,
                            $this->playerEditionHistory->cutoffDateForEdition($event)
                        )
                        : [];

                    $eventParticipations->getCollection()->transform(function (EventParticipation $p) use ($histories, $flagsById, $customFieldsByPlayer) {
                        $p->setAttribute(
                            'player_history',
                            $histories[(int) $p->player_user_id] ?? [
                                'last_played' => null,
                                'participated_count' => 0,
                                'banned_count' => 0,
                                'absented_count' => 0,
                                'played_recent' => [],
                                'participated_tooltip' => ['lines' => []],
                                'banned_recent' => [],
                                'absented_recent' => [],
                            ]
                        );
                        $p->setAttribute(
                            'flags',
                            $flagsById[(int) $p->id_participation] ?? []
                        );
                        $p->setAttribute(
                            'custom_field_values',
                            $customFieldsByPlayer[(int) $p->player_user_id] ?? []
                        );

                        return $p;
                    });
                }
            }

            $payload = EventParticipationResource::collection($eventParticipations)->response()->getData(true);
            if ($editionSummary !== null) {
                $payload['meta'] = array_merge($payload['meta'] ?? [], [
                    'event_summary' => $editionSummary,
                ]);
            }

            return $payload;
        });

        if (is_array($payload) && isset($payload['data']) && is_array($payload['data'])) {
            $this->pdfAvatarAttacher->overlayParticipationsOnArray($payload['data']);
        }

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-participations/{editionParticipation}",
     *     summary="Get edition participation details",
     *     tags={"🐧 Manager: PadelEvent Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="editionParticipation",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EventParticipation")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Participation not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(EventParticipation $eventParticipation)
    {
        $eventParticipation->load(['player', 'eventDivision', 'event']);
        $this->pdfAvatarAttacher->hydrateParticipation($eventParticipation);

        return $this->resourceResponse(
            new EventParticipationResource($eventParticipation),
            'Participation details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-participations",
     *     summary="Create edition participation",
     *     tags={"🐧 Manager: PadelEvent Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event_id", "player_user_id", "event_division_id"},
     *             @OA\Property(property="event_id", type="integer"),
     *             @OA\Property(property="player_user_id", type="integer"),
     *             @OA\Property(property="event_division_id", type="integer"),
     *             @OA\Property(property="sort_order", type="integer", description="Sort order", default=0),
     *             @OA\Property(property="is_collecting_points", type="boolean", description="Is collecting points", default=true),
     *             @OA\Property(property="absence_reason", type="string", enum={"SICK", "FORGOT", "EXAMS", "RECONSIDERED"}, description="Absence reason"),
     *             @OA\Property(property="ban_reason", type="string", enum={"LEFT SUDDENLY", "AGGRESSIVE PLAYER", "LIBIDINOUS", ""}, description="Ban reason")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Player added to edition successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EventParticipation")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id_event',
            'player_user_id' => 'required|exists:users,id_user',
            'player_id' => 'prohibited',
            'event_division_id' => 'required|exists:event_divisions,id_event_division',
            'sort_order' => 'integer|min:0',
            'is_collecting_points' => 'boolean',
            'absence_reason' => 'nullable|in:SICK,FORGOT,EXAMS,RECONSIDERED',
            'ban_reason' => 'nullable|in:LEFT SUDDENLY,AGGRESSIVE PLAYER,LIBIDINOUS,',
        ]);

        $event = PadelEvent::query()->find($validated['event_id']);
        if ($event?->finalized_at !== null) {
            return $this->errorResponse('Participations cannot be created after finalize.', 409);
        }

        // Check if player is already participating in this edition division
        $existingParticipation = EventParticipation::where([
            'event_division_id' => $validated['event_division_id'],
            'player_user_id' => $validated['player_user_id'],
        ])->first();

        if ($existingParticipation) {
            return $this->errorResponse('This player is already participating in this edition division', 422);
        }

        // Set default values for optional fields
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_collecting_points'] = $validated['is_collecting_points'] ?? true;

        $eventParticipation = EventParticipation::create($validated);

        $eventParticipation->load(['player', 'eventDivision', 'event']);
        if ($eventParticipation->eventDivision !== null) {
            $this->posterInvalidator->clearMatchesAndRanking($eventParticipation->eventDivision);
        }
        $this->posterInvalidator->clearAttendanceForEvent((int) $eventParticipation->event_id);
        $this->pdfAvatarAttacher->hydrateParticipation($eventParticipation);

        return $this->resourceResponse(
            new EventParticipationResource($eventParticipation),
            'Participation created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/event-participations/{editionParticipation}",
     *     summary="Update edition participation details (complete replacement)",
     *     tags={"🐧 Manager: PadelEvent Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="editionParticipation",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="player_user_id", type="integer", description="Player ID"),
     *             @OA\Property(property="event_division_id", type="integer", description="PadelEvent division ID"),
     *             @OA\Property(property="sort_order", type="integer", description="Sort order"),
     *             @OA\Property(property="is_collecting_points", type="boolean", description="Is collecting points"),
     *             @OA\Property(property="absence_reason", type="string", enum={"SICK", "FORGOT", "EXAMS", "RECONSIDERED"}, description="Absence reason"),
     *             @OA\Property(property="ban_reason", type="string", enum={"LEFT SUDDENLY", "AGGRESSIVE PLAYER", "LIBIDINOUS", ""}, description="Ban reason")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Participation updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EventParticipation")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PadelEvent or participation not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function update(Request $request, EventParticipation $eventParticipation)
    {
        $eventParticipation->loadMissing('event');
        if ($eventParticipation->event?->finalized_at !== null) {
            return $this->errorResponse('Participations cannot be updated after finalize.', 409);
        }

        $validated = $request->validate([
            'event_id' => 'required|exists:events,id_event',
            'player_user_id' => 'required|exists:users,id_user',
            'player_id' => 'prohibited',
            'event_division_id' => 'required|exists:event_divisions,id_event_division',
            'sort_order' => 'required|integer|min:0',
            'is_collecting_points' => 'required|boolean',
            'absence_reason' => 'nullable|in:SICK,FORGOT,EXAMS,RECONSIDERED',
            'ban_reason' => 'nullable|in:LEFT SUDDENLY,AGGRESSIVE PLAYER,LIBIDINOUS,',
        ]);

        // Check if player is already participating in this edition division (excluding current record)
        $existingParticipation = EventParticipation::where('id_participation', '!=', $eventParticipation->id_participation)
            ->where('event_division_id', $validated['event_division_id'])
            ->where('player_user_id', $validated['player_user_id'])
            ->first();

        if ($existingParticipation) {
            return $this->errorResponse('This player is already participating in this edition division', 422);
        }

        $shouldInvalidate = $this->participationUpdateShouldInvalidate($eventParticipation, $validated);
        $shouldInvalidateAttendance = $this->attendanceInvalidation
            ->shouldInvalidateForParticipationChange($eventParticipation, $validated);
        $eventParticipation->update($validated);
        $eventParticipation->load(['player', 'eventDivision', 'event']);
        if ($shouldInvalidate && $eventParticipation->eventDivision !== null) {
            $this->posterInvalidator->clearMatchesAndRanking($eventParticipation->eventDivision);
        }
        if ($shouldInvalidateAttendance) {
            $this->posterInvalidator->clearAttendanceForEvent((int) $eventParticipation->event_id);
        }
        $this->pdfAvatarAttacher->hydrateParticipation($eventParticipation);

        return $this->resourceResponse(
            new EventParticipationResource($eventParticipation),
            'Participation updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/event-participations/{editionParticipation}",
     *     summary="Partially update edition participation details",
     *     tags={"🐧 Manager: PadelEvent Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="editionParticipation",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="player_user_id", type="integer", description="Player ID"),
     *             @OA\Property(property="event_division_id", type="integer", description="PadelEvent division ID"),
     *             @OA\Property(property="sort_order", type="integer", description="Sort order"),
     *             @OA\Property(property="is_collecting_points", type="boolean", description="Is collecting points"),
     *             @OA\Property(property="absence_reason", type="string", enum={"SICK", "FORGOT", "EXAMS", "RECONSIDERED"}, description="Absence reason"),
     *             @OA\Property(property="ban_reason", type="string", enum={"LEFT SUDDENLY", "AGGRESSIVE PLAYER", "LIBIDINOUS", ""}, description="Ban reason")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Participation partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EventParticipation")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PadelEvent or participation not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function patch(Request $request, EventParticipation $eventParticipation)
    {
        $eventParticipation->loadMissing('event');
        if ($eventParticipation->event?->finalized_at !== null) {
            return $this->errorResponse('Participations cannot be updated after finalize.', 409);
        }

        $validated = $request->validate([
            'event_id' => 'sometimes|exists:events,id_event',
            'player_user_id' => 'sometimes|exists:users,id_user',
            'player_id' => 'prohibited',
            'event_division_id' => 'sometimes|exists:event_divisions,id_event_division',
            'sort_order' => 'integer|min:0',
            'is_collecting_points' => 'boolean',
            'absence_reason' => 'nullable|in:SICK,FORGOT,EXAMS,RECONSIDERED',
            'ban_reason' => 'nullable|in:LEFT SUDDENLY,AGGRESSIVE PLAYER,LIBIDINOUS,',
        ]);

        // Check if player is already participating in this edition division (excluding current record)
        if (isset($validated['player_user_id']) || isset($validated['event_division_id'])) {
            $existingParticipation = EventParticipation::where('id_participation', '!=', $eventParticipation->id_participation)
                ->where('event_division_id', $validated['event_division_id'] ?? $eventParticipation->event_division_id)
                ->where('player_user_id', $validated['player_user_id'] ?? $eventParticipation->player_user_id)
                ->first();

            if ($existingParticipation) {
                return $this->errorResponse('This player is already participating in this edition division', 422);
            }
        }

        $shouldInvalidate = $this->participationUpdateShouldInvalidate($eventParticipation, $validated);
        $shouldInvalidateAttendance = $this->attendanceInvalidation
            ->shouldInvalidateForParticipationChange($eventParticipation, $validated);
        $eventParticipation->update($validated);
        $eventParticipation->load(['player', 'eventDivision', 'event']);
        if ($shouldInvalidate && $eventParticipation->eventDivision !== null) {
            $this->posterInvalidator->clearMatchesAndRanking($eventParticipation->eventDivision);
        }
        if ($shouldInvalidateAttendance) {
            $this->posterInvalidator->clearAttendanceForEvent((int) $eventParticipation->event_id);
        }
        $this->pdfAvatarAttacher->hydrateParticipation($eventParticipation);

        return $this->resourceResponse(
            new EventParticipationResource($eventParticipation),
            'Participation partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/event-participations/{editionParticipation}",
     *     summary="Delete edition participation",
     *     tags={"🐧 Manager: PadelEvent Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="editionParticipation",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player removed from edition successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Participation not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function destroy(EventParticipation $eventParticipation)
    {
        $eventParticipation->loadMissing(['event', 'eventDivision']);
        if ($eventParticipation->event?->finalized_at !== null) {
            return $this->errorResponse('Participations cannot be deleted after finalize.', 409);
        }

        if ($this->lotteryGuard->hasLotteryLuckiesForEdition((int) $eventParticipation->event_id)) {
            return $this->errorResponse(
                'Participations cannot be deleted after lottery draws exist for this edition.',
                409
            );
        }

        $eventDivision = $eventParticipation->eventDivision;

        $response = $this->deleteResourceWithResponse(
            $eventParticipation,
            [],
            'participation',
            'Participation deleted successfully'
        );

        if ($eventDivision !== null) {
            $this->posterInvalidator->clearMatchesAndRanking($eventDivision);
        }
        $this->posterInvalidator->clearAttendanceForEvent((int) $eventParticipation->event_id);

        return $response;
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/event-participations/bulk",
     *     summary="Bulk delete edition participations",
     *     tags={"🐧 Manager: PadelEvent Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"participation_ids"},
     *             @OA\Property(
     *                 property="participation_ids",
     *                 type="array",
     *                 description="Array of participation IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All participations deleted successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="successful", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="failed", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="total_requested", type="integer"),
     *                         @OA\Property(property="total_successful", type="integer"),
     *                         @OA\Property(property="total_failed", type="integer")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=207,
     *         description="Some participations failed to delete (Multi-Status)",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         @OA\Property(property="successful", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="failed", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="total_requested", type="integer"),
     *                         @OA\Property(property="total_successful", type="integer"),
     *                         @OA\Property(property="total_failed", type="integer")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No participations provided for deletion",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PadelEvent not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'participation_ids' => 'required|array|min:1',
            'participation_ids.*' => 'integer',
        ]);

        $results = [
            'successful' => [],
            'failed' => [],
            'total_requested' => count($request->participation_ids),
            'total_successful' => 0,
            'total_failed' => 0,
        ];

        /** @var array<int, EventDivision> $divisionsToClear */
        $divisionsToClear = [];
        /** @var array<int, true> $editionsToClearAttendance */
        $editionsToClearAttendance = [];

        foreach ($request->participation_ids as $eventParticipationId) {
            try {
                $eventParticipation = EventParticipation::find($eventParticipationId);

                if (! $eventParticipation) {
                    $results['failed'][] = [
                        'id' => $eventParticipationId,
                        'reason' => 'Resource not found',
                    ];
                    $results['total_failed']++;

                    continue;
                }

                $eventParticipation->loadMissing(['event', 'eventDivision']);
                if ($eventParticipation->event?->finalized_at !== null) {
                    $results['failed'][] = [
                        'id' => $eventParticipationId,
                        'reason' => 'Participations cannot be deleted after finalize.',
                    ];
                    $results['total_failed']++;

                    continue;
                }

                if ($this->lotteryGuard->hasLotteryLuckiesForEdition((int) $eventParticipation->event_id)) {
                    $results['failed'][] = [
                        'id' => $eventParticipationId,
                        'reason' => 'Participations cannot be deleted after lottery draws exist for this edition.',
                    ];
                    $results['total_failed']++;

                    continue;
                }

                if ($eventParticipation->eventDivision !== null) {
                    $divisionsToClear[(int) $eventParticipation->event_division_id] = $eventParticipation->eventDivision;
                }
                $editionsToClearAttendance[(int) $eventParticipation->event_id] = true;

                $eventParticipation->delete();

                $results['successful'][] = [
                    'id' => $eventParticipationId,
                    'name' => $eventParticipation->player->name ?? $eventParticipationId,
                ];
                $results['total_successful']++;

            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $eventParticipationId,
                    'reason' => 'Unexpected error: ' . $e->getMessage(),
                ];
                $results['total_failed']++;
            }
        }

        foreach ($divisionsToClear as $division) {
            $this->posterInvalidator->clearMatchesAndRanking($division);
        }
        foreach (array_keys($editionsToClearAttendance) as $editionIdToClear) {
            $this->posterInvalidator->clearAttendanceForEvent((int) $editionIdToClear);
        }

        // Determine response based on results
        if ($results['total_failed'] === 0) {
            return $this->successResponse(
                $results,
                'Participations deleted successfully'
            );
        } elseif ($results['total_successful'] === 0) {
            return $this->errorResponse(
                'All participations failed to delete',
                409,
                $results
            );
        } else {
            return $this->errorResponse(
                'Some participations failed to delete',
                207, // Multi-Status
                $results
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/event-participations/reorder",
     *     summary="Reorder / move edition participations (affected rows only)",
     *     tags={"🐧 Manager: PadelEvent Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"participations"},
     *             @OA\Property(
     *                 property="participations",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"id_participation", "event_division_id", "sort_order"},
     *                     @OA\Property(property="id_participation", type="integer"),
     *                     @OA\Property(property="event_division_id", type="integer"),
     *                     @OA\Property(property="sort_order", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Participations reordered"),
     *     @OA\Response(response=409, description="PadelEvent division is finalized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'participations' => 'required|array|min:1',
            'participations.*.id_participation' => 'required|integer|distinct',
            'participations.*.event_division_id' => 'required|integer',
            'participations.*.sort_order' => 'required|integer|min:0',
        ]);

        $rows = $validated['participations'];
        $participationIds = array_map(
            static fn (array $row): int => (int) $row['id_participation'],
            $rows
        );
        $targetDivisionIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['event_division_id'],
            $rows
        )));

        $participations = EventParticipation::query()
            ->whereIn('id_participation', $participationIds)
            ->get()
            ->keyBy('id_participation');

        if ($participations->count() !== count($participationIds)) {
            return $this->errorResponse('One or more participations were not found.', 422);
        }

        $sourceDivisionIds = $participations
            ->pluck('event_division_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $allDivisionIds = array_values(array_unique(array_merge($sourceDivisionIds, $targetDivisionIds)));

        $divisions = EventDivision::query()
            ->whereIn('id_event_division', $allDivisionIds)
            ->get()
            ->keyBy('id_event_division');

        if ($divisions->count() !== count($allDivisionIds)) {
            return $this->errorResponse('One or more edition divisions were not found.', 422);
        }

        $editionIds = $divisions->pluck('event_id')->unique()->values();
        if ($editionIds->count() !== 1) {
            return $this->errorResponse('All edition divisions must belong to the same edition.', 422);
        }

        $editionId = (int) $editionIds->first();
        foreach ($participations as $participation) {
            if ((int) $participation->event_id !== $editionId) {
                return $this->errorResponse('Participation does not belong to the edition.', 422);
            }
        }

        $event = PadelEvent::query()->find($editionId);
        if ($event?->finalized_at !== null) {
            return $this->errorResponse(
                'Cannot reorder participations after finalize.',
                409
            );
        }

        $updated = DB::transaction(function () use ($rows, $participations) {
            $touchedIds = [];
            foreach ($rows as $row) {
                $participation = $participations->get((int) $row['id_participation']);
                $participation->event_division_id = (int) $row['event_division_id'];
                $participation->sort_order = (int) $row['sort_order'];
                $participation->save();
                $touchedIds[] = (int) $participation->id_participation;
            }

            return EventParticipation::query()
                ->with(['player', 'eventDivision', 'event'])
                ->whereIn('id_participation', $touchedIds)
                ->orderBy('event_division_id')
                ->orderBy('sort_order')
                ->orderBy('id_participation')
                ->get();
        });

        $this->posterInvalidator->clearAttendanceForEvent($editionId);

        $this->pdfAvatarAttacher->hydrateParticipations($updated);

        return $this->successResponse(
            EventParticipationResource::collection($updated),
            'Participations reordered successfully'
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function participationUpdateShouldInvalidate(EventParticipation $participation, array $validated): bool
    {
        if (array_key_exists('player_user_id', $validated)
            && (int) $validated['player_user_id'] !== (int) $participation->player_user_id) {
            return true;
        }

        if (array_key_exists('is_collecting_points', $validated)
            && (bool) $validated['is_collecting_points'] !== (bool) $participation->is_collecting_points) {
            return true;
        }

        if (array_key_exists('event_division_id', $validated)
            && (int) $validated['event_division_id'] !== (int) $participation->event_division_id) {
            return true;
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function parseCustomFieldIds(Request $request, int $editionId): array
    {
        if ($editionId <= 0 || ! $request->has('custom_field_ids')) {
            return [];
        }

        $raw = $request->input('custom_field_ids', []);
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (is_numeric($value)) {
                $id = (int) $value;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
