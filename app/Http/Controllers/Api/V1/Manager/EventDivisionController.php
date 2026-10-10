<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EventDivisionResource;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
use App\Services\Pdf\EventDivisionPosterInvalidator;
use App\Services\Schedule\PlayingWindow;
use App\Traits\CachesManagerResourceReads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Divisions",
 *     description="(v1) API Endpoints for edition divisions"
 * )
 */
class EventDivisionController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    use CachesManagerResourceReads;
    /**
     * Schedule knobs moved to the edition division schedule row and are refused here.
     *
     * @var list<string>
     */
    private const MOVED_SCHEDULE_KEYS = [
        'player_opponents_nr',
        'player_matches_repeat',
        'include_scores',
        'has_demonstrative_match',
        'allow_replacements',
        'points_per_match',
        'final_points_target',
        'include_final',
        'template_version',
        'adjust',
    ];

    public function __construct(
        private readonly EventDivisionPosterInvalidator $posterInvalidator,
    ) {
        parent::__construct();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-divisions",
     *     summary="Get paginated list of edition divisions",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
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
     *         description="Search term for division_title field",
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
     *             @OA\Property(property="division_id", type="string", description="Filter by division ID"),
     *             @OA\Property(property="players_limit", type="string", description="Filter by players limit"),
     *             @OA\Property(property="skill_order", type="string", description="Filter by skill order")
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
     *             @OA\Property(property="id_event_division", type="string", enum={"asc", "desc"}, description="Sort by edition division ID"),
     *             @OA\Property(property="event_id", type="string", enum={"asc", "desc"}, description="Sort by edition ID"),
     *             @OA\Property(property="division_id", type="string", enum={"asc", "desc"}, description="Sort by division ID"),
     *             @OA\Property(property="players_limit", type="string", enum={"asc", "desc"}, description="Sort by players limit"),
     *             @OA\Property(property="division_title", type="string", enum={"asc", "desc"}, description="Sort by division title"),
     *             @OA\Property(property="playing_start_time", type="string", enum={"asc", "desc"}, description="Sort by playing start time"),
     *             @OA\Property(property="playing_end_time", type="string", enum={"asc", "desc"}, description="Sort by playing end time"),
     *             @OA\Property(property="skill_order", type="string", enum={"asc", "desc"}, description="Sort by skill order"),
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
     *                         @OA\Items(ref="#/components/schemas/EventDivision")
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
        $payload = $this->cachedManagerIndex('event-divisions', $request, ['event_id'], function () use ($request) {
            $query = EventDivision::with(['event', 'division', 'courts']);

            $this->applyAdvancedPagination(
                $query,
                $request,
                EventDivision::$searchableFields,
                EventDivision::$filterableFields,
                EventDivision::$sortableFields
            );

            $paginationParams = $this->getPaginationParams($request);

            if (! $request->has('sort_by')) {
                $query->orderBy('skill_order')->orderBy('id_event_division');
            }

            $eventDivisions = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

            return EventDivisionResource::collection($eventDivisions)->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-divisions/{eventDivision}",
     *     summary="Get edition division details",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="eventDivision",
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
     *                     @OA\Property(property="data", ref="#/components/schemas/EventDivision")
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
     *         description="PadelEvent division not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(EventDivision $eventDivision)
    {
        $eventDivision->load(['event', 'division', 'courts']);

        return $this->resourceResponse(
            new EventDivisionResource($eventDivision),
            'PadelEvent division details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-divisions",
     *     summary="Create a new edition division",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event_id", "division_id", "players_limit", "entry_fee", "playing_start_time", "playing_end_time"},
     *             @OA\Property(property="event_id", type="integer", description="PadelEvent ID"),
     *             @OA\Property(property="division_id", type="integer", description="Division ID"),
     *             @OA\Property(property="players_limit", type="integer", description="Players limit"),
     *             @OA\Property(property="entry_fee", type="integer", minimum=0, description="Entry fee in RON"),
     *             @OA\Property(property="division_title", type="string", maxLength=64, description="Division title. Defaults to the division name when omitted"),
     *             @OA\Property(property="playing_start_time", type="string", format="time", description="Playing start time"),
     *             @OA\Property(property="playing_end_time", type="string", format="time", description="Playing end time"),
     *             @OA\Property(property="skill_order", type="integer", description="Ordering of divisions by skill within the edition. Defaults to the next free slot in the edition")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="PadelEvent division created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EventDivision")
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
        $validated = $request->validate(
            array_merge([
                'event_id' => 'required|integer|exists:events,id_event',
                'division_id' => 'required|integer|exists:divisions,id_division',
                'division_title' => 'nullable|string|max:64',
                'players_limit' => 'required|integer|min:1',
                'entry_fee' => 'required|integer|min:0',
                'playing_start_time' => ['required', PlayingWindow::clockRegexRule()],
                'playing_end_time' => ['required', PlayingWindow::clockRegexRule()],
                'skill_order' => 'nullable|integer|min:0',
                'level_min' => 'nullable|numeric|between:0,9.99',
                'level_max' => 'nullable|numeric|between:0,9.99',
            ], $this->movedScheduleKeyRules()),
            $this->movedScheduleKeyMessages()
        );

        $event = PadelEvent::query()->find($validated['event_id']);
        if ($event?->finalized_at !== null) {
            return $this->errorResponse('PadelEvent divisions cannot be created after finalize.', 409);
        }

        if (($validated['division_title'] ?? null) === null || $validated['division_title'] === '') {
            $validated['division_title'] = (string) Division::query()
                ->where('id_division', $validated['division_id'])
                ->value('name');
        }

        $validated['skill_order'] = $validated['skill_order']
            ?? $this->nextSkillOrder((int) $validated['event_id']);
        $validated['playing_start_time'] = PlayingWindow::normalizeStoredTime((string) $validated['playing_start_time']);
        $validated['playing_end_time'] = PlayingWindow::normalizeStoredTime((string) $validated['playing_end_time']);
        $this->assertDistinctPlayingWindow(
            (string) $validated['playing_start_time'],
            (string) $validated['playing_end_time']
        );

        $eventDivision = EventDivision::create($validated);
        $eventDivision->load(['event', 'division', 'courts']);
        $this->posterInvalidator->clearAttendanceForEvent((int) $eventDivision->event_id);

        return $this->resourceResponse(
            new EventDivisionResource($eventDivision),
            'PadelEvent division created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/event-divisions/{eventDivision}",
     *     summary="Update edition division details (complete replacement)",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="eventDivision",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="event_id", type="integer", description="PadelEvent ID"),
     *             @OA\Property(property="division_id", type="integer", description="Division ID"),
     *             @OA\Property(property="players_limit", type="integer", description="Players limit"),
     *             @OA\Property(property="entry_fee", type="integer", minimum=0, description="Entry fee in RON"),
     *             @OA\Property(property="division_title", type="string", maxLength=64, description="Division title"),
     *             @OA\Property(property="playing_start_time", type="string", format="time", description="Playing start time"),
     *             @OA\Property(property="playing_end_time", type="string", format="time", description="Playing end time"),
     *             @OA\Property(property="skill_order", type="integer", description="Ordering of divisions by skill within the edition")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PadelEvent division updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EventDivision")
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
     *         description="PadelEvent division not found",
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
    public function update(Request $request, EventDivision $eventDivision)
    {
        $eventDivision->loadMissing('event');
        if ($eventDivision->event?->finalized_at !== null) {
            return $this->errorResponse('PadelEvent divisions cannot be updated after finalize.', 409);
        }

        $validated = $request->validate(
            array_merge([
                'event_id' => 'required|integer|exists:events,id_event',
                'division_id' => 'required|integer|exists:divisions,id_division',
                'division_title' => 'required|string|max:64',
                'players_limit' => 'required|integer|min:1',
                'entry_fee' => 'nullable|integer|min:0',
                'playing_start_time' => ['required', PlayingWindow::clockRegexRule()],
                'playing_end_time' => ['required', PlayingWindow::clockRegexRule()],
                'skill_order' => 'nullable|integer|min:0',
                'level_min' => 'nullable|numeric|between:0,9.99',
                'level_max' => 'nullable|numeric|between:0,9.99',
            ], $this->movedScheduleKeyRules()),
            $this->movedScheduleKeyMessages()
        );

        if (isset($validated['playing_start_time'])) {
            $validated['playing_start_time'] = PlayingWindow::normalizeStoredTime((string) $validated['playing_start_time']);
        }
        if (isset($validated['playing_end_time'])) {
            $validated['playing_end_time'] = PlayingWindow::normalizeStoredTime((string) $validated['playing_end_time']);
        }
        $this->assertDistinctPlayingWindow(
            (string) ($validated['playing_start_time'] ?? $eventDivision->getAttributes()['playing_start_time']),
            (string) ($validated['playing_end_time'] ?? $eventDivision->getAttributes()['playing_end_time'])
        );

        $this->applyAndInvalidatePosters($eventDivision, $validated);
        $eventDivision->load(['event', 'division', 'courts']);

        return $this->resourceResponse(
            new EventDivisionResource($eventDivision),
            'PadelEvent division updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/event-divisions/{eventDivision}",
     *     summary="Partially update edition division details",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="eventDivision",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="event_id", type="integer", description="PadelEvent ID"),
     *             @OA\Property(property="division_id", type="integer", description="Division ID"),
     *             @OA\Property(property="players_limit", type="integer", description="Players limit"),
     *             @OA\Property(property="entry_fee", type="integer", minimum=0, description="Entry fee in RON"),
     *             @OA\Property(property="division_title", type="string", maxLength=64, description="Division title"),
     *             @OA\Property(property="playing_start_time", type="string", format="time", description="Playing start time"),
     *             @OA\Property(property="playing_end_time", type="string", format="time", description="Playing end time"),
     *             @OA\Property(property="skill_order", type="integer", description="Ordering of divisions by skill within the edition")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PadelEvent division partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EventDivision")
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
     *         description="PadelEvent division not found",
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
    public function patch(Request $request, EventDivision $eventDivision)
    {
        $eventDivision->loadMissing('event');
        if ($eventDivision->event?->finalized_at !== null) {
            return $this->errorResponse('PadelEvent divisions cannot be updated after finalize.', 409);
        }

        $validated = $request->validate(
            array_merge([
                'event_id' => 'sometimes|integer|exists:events,id_event',
                'division_id' => 'sometimes|integer|exists:divisions,id_division',
                'division_title' => 'sometimes|string|max:64',
                'players_limit' => 'sometimes|integer|min:1',
                'entry_fee' => 'sometimes|integer|min:0',
                'playing_start_time' => ['sometimes', PlayingWindow::clockRegexRule()],
                'playing_end_time' => ['sometimes', PlayingWindow::clockRegexRule()],
                'skill_order' => 'sometimes|integer|min:0',
                'level_min' => 'sometimes|nullable|numeric|between:0,9.99',
                'level_max' => 'sometimes|nullable|numeric|between:0,9.99',
            ], $this->movedScheduleKeyRules()),
            $this->movedScheduleKeyMessages()
        );

        if (isset($validated['playing_start_time'])) {
            $validated['playing_start_time'] = PlayingWindow::normalizeStoredTime((string) $validated['playing_start_time']);
        }
        if (isset($validated['playing_end_time'])) {
            $validated['playing_end_time'] = PlayingWindow::normalizeStoredTime((string) $validated['playing_end_time']);
        }
        if (isset($validated['playing_start_time']) || isset($validated['playing_end_time'])) {
            $this->assertDistinctPlayingWindow(
                (string) ($validated['playing_start_time'] ?? $eventDivision->getAttributes()['playing_start_time']),
                (string) ($validated['playing_end_time'] ?? $eventDivision->getAttributes()['playing_end_time'])
            );
        }

        $this->applyAndInvalidatePosters($eventDivision, $validated);
        $eventDivision->load(['event', 'division', 'courts']);

        return $this->resourceResponse(
            new EventDivisionResource($eventDivision),
            'PadelEvent division partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/event-divisions/{eventDivision}",
     *     summary="Delete an edition division",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="eventDivision",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PadelEvent division deleted successfully",
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
     *         description="PadelEvent division not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="PadelEvent division cannot be deleted due to linked data",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="linked_data",
     *                         type="object",
     *                         description="Information about linked data preventing deletion",
     *                         @OA\Property(property="participations_count", type="integer", example=15)
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function destroy(EventDivision $eventDivision)
    {
        $eventDivision->loadMissing('event');
        if ($eventDivision->event?->finalized_at !== null) {
            return $this->errorResponse('PadelEvent divisions cannot be deleted after finalize.', 409);
        }

        // Check if edition division has related participations
        $participationsCount = EventParticipation::where(
            'event_division_id',
            $eventDivision->id_event_division
        )->count();

        if ($participationsCount > 0) {
            return $this->errorResponse(
                'PadelEvent division cannot be deleted because it has linked data. Please remove all associated participations first.',
                409,
                ['linked_data' => ['participations_count' => $participationsCount]]
            );
        }

        $eventDivision->delete();
        $this->posterInvalidator->clearAttendanceForEvent((int) $eventDivision->event_id);

        return $this->successResponse(null, 'PadelEvent division deleted successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/event-divisions/bulk",
     *     summary="Bulk delete edition divisions",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event_division_ids"},
     *             @OA\Property(
     *                 property="event_division_ids",
     *                 type="array",
     *                 description="Array of edition division IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All edition divisions deleted successfully",
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
     *         description="Some edition divisions failed to delete (Multi-Status)",
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
     *         description="No edition divisions provided for deletion",
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
     *         response=409,
     *         description="All edition divisions failed to delete due to linked data",
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
            'event_division_ids' => 'required|array|min:1',
            'event_division_ids.*' => 'integer',
        ]);

        $results = [
            'successful' => [],
            'failed' => [],
            'total_requested' => count($request->event_division_ids),
            'total_successful' => 0,
            'total_failed' => 0,
        ];

        foreach ($request->event_division_ids as $eventDivisionId) {
            try {
                $eventDivision = EventDivision::find($eventDivisionId);

                if (! $eventDivision) {
                    $results['failed'][] = [
                        'id' => $eventDivisionId,
                        'reason' => 'Resource not found',
                    ];
                    $results['total_failed']++;

                    continue;
                }

                $eventDivision->loadMissing('event');
                if ($eventDivision->event?->finalized_at !== null) {
                    $results['failed'][] = [
                        'id' => $eventDivisionId,
                        'reason' => 'PadelEvent divisions cannot be deleted after finalize.',
                    ];
                    $results['total_failed']++;

                    continue;
                }

                // Check if edition division has related participations
                $participationsCount = EventParticipation::where(
                    'event_division_id',
                    $eventDivision->id_event_division
                )->count();

                if ($participationsCount > 0) {
                    $results['failed'][] = [
                        'id' => $eventDivisionId,
                        'reason' => 'PadelEvent division cannot be deleted because it has linked data. Please remove all associated participations first.',
                        'linked_data' => ['participations_count' => $participationsCount],
                    ];
                    $results['total_failed']++;

                    continue;
                }

                $eventDivision->delete();

                $results['successful'][] = [
                    'id' => $eventDivisionId,
                    'name' => $eventDivision->division->name ?? $eventDivisionId,
                ];
                $results['total_successful']++;

            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $eventDivisionId,
                    'reason' => 'Unexpected error: ' . $e->getMessage(),
                ];
                $results['total_failed']++;
            }
        }

        // Determine response based on results
        if ($results['total_failed'] === 0) {
            return $this->successResponse(
                $results,
                'PadelEvent divisions deleted successfully'
            );
        } elseif ($results['total_successful'] === 0) {
            return $this->errorResponse(
                'All edition divisions failed to delete',
                409,
                $results
            );
        } else {
            return $this->errorResponse(
                'Some edition divisions failed to delete',
                207, // Multi-Status
                $results
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/event-divisions/reorder",
     *     summary="Rewrite skill_order for every division of one edition",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event_id", "divisions"},
     *             @OA\Property(property="event_id", type="integer", description="PadelEvent owning every listed division"),
     *             @OA\Property(
     *                 property="divisions",
     *                 type="array",
     *                 description="Must list every division of the edition exactly once",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"id_event_division", "skill_order"},
     *                     @OA\Property(property="id_event_division", type="integer"),
     *                     @OA\Property(property="skill_order", type="integer", minimum=0)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PadelEvent divisions reordered successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EventDivision"))
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
     *         response=409,
     *         description="PadelEvent is finalized",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:events,id_event',
            'divisions' => 'required|array|min:1',
            'divisions.*.id_event_division' => 'required|integer|distinct',
            'divisions.*.skill_order' => 'required|integer|min:0',
        ]);

        $editionId = (int) $validated['event_id'];

        $event = PadelEvent::query()->find($editionId);
        if ($event?->finalized_at !== null) {
            return $this->errorResponse('PadelEvent divisions cannot be reordered after finalize.', 409);
        }

        $existing = EventDivision::query()
            ->where('event_id', $editionId)
            ->get()
            ->keyBy('id_event_division');

        $requestedIds = array_map(
            static fn (array $row): int => (int) $row['id_event_division'],
            $validated['divisions']
        );

        $missing = $existing->keys()
            ->map(static fn ($id): int => (int) $id)
            ->diff($requestedIds)
            ->values()
            ->all();

        if ($missing !== []) {
            return $this->errorResponse(
                'divisions must include every edition division of this edition.',
                422,
                ['missing_event_division_ids' => $missing]
            );
        }

        $unknown = array_values(array_diff($requestedIds, $existing->keys()->map(static fn ($id): int => (int) $id)->all()));
        if ($unknown !== []) {
            return $this->errorResponse(
                'One or more edition divisions do not belong to this edition.',
                422,
                ['unknown_event_division_ids' => $unknown]
            );
        }

        $ordered = $validated['divisions'];
        usort($ordered, static function (array $left, array $right): int {
            return [(int) $left['skill_order'], (int) $left['id_event_division']]
                <=> [(int) $right['skill_order'], (int) $right['id_event_division']];
        });

        $reordered = DB::transaction(function () use ($ordered, $existing, $editionId) {
            foreach ($ordered as $index => $row) {
                $eventDivision = $existing->get((int) $row['id_event_division']);
                $eventDivision->skill_order = $index;
                $eventDivision->save();
            }

            return EventDivision::query()
                ->with(['event', 'division', 'courts'])
                ->where('event_id', $editionId)
                ->orderBy('skill_order')
                ->orderBy('id_event_division')
                ->get();
        });

        $this->posterInvalidator->clearAttendanceForEvent($editionId);

        return $this->successResponse(
            EventDivisionResource::collection($reordered),
            'PadelEvent divisions reordered successfully'
        );
    }

    /**
     * @return array<string, string>
     */
    private function movedScheduleKeyRules(): array
    {
        return array_fill_keys(self::MOVED_SCHEDULE_KEYS, 'prohibited');
    }

    /**
     * @return array<string, string>
     */
    private function movedScheduleKeyMessages(): array
    {
        $messages = [];
        foreach (self::MOVED_SCHEDULE_KEYS as $key) {
            $messages[$key . '.prohibited'] = sprintf(
                'The %s field moved to the edition division schedule. Send it to /event-divisions/{eventDivision}/schedule/form instead.',
                $key
            );
        }

        return $messages;
    }

    private function assertDistinctPlayingWindow(string $start, string $end): void
    {
        if (PlayingWindow::windowsAreDistinct($start, $end)) {
            return;
        }

        throw ValidationException::withMessages([
            'playing_end_time' => ['Playing start time and playing end time must differ.'],
        ]);
    }

    private function nextSkillOrder(int $editionId): int
    {
        $max = EventDivision::query()
            ->where('event_id', $editionId)
            ->max('skill_order');

        return $max === null ? 0 : ((int) $max + 1);
    }

    /**
     * Playing window edits change every generated poster, so they are cleared.
     *
     * @param  array<string, mixed>  $validated
     */
    private function applyAndInvalidatePosters(EventDivision $eventDivision, array $validated): void
    {
        $playingWindowChanged = false;
        $attendanceChanged = false;

        foreach (['playing_start_time', 'playing_end_time'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }
            $incoming = PlayingWindow::toClockString($validated[$field]);
            $current = PlayingWindow::toClockString($eventDivision->getAttribute($field));
            if ($incoming !== $current) {
                $playingWindowChanged = true;
            }
        }

        if (array_key_exists('division_title', $validated)
            && (string) $validated['division_title'] !== (string) $eventDivision->division_title) {
            $attendanceChanged = true;
        }

        $eventDivision->update($validated);

        if ($playingWindowChanged) {
            $this->posterInvalidator->clearMatchesAndRanking($eventDivision);
        }

        if ($attendanceChanged) {
            $this->posterInvalidator->clearAttendanceForEvent((int) $eventDivision->event_id);
        }
    }
}
