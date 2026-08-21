<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionDivisionResource;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use App\Services\Pdf\EditionDivisionPosterInvalidator;
use App\Services\Schedule\PlayingWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Edition Divisions",
 *     description="(v1) API Endpoints for edition divisions"
 * )
 */
class EditionDivisionController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
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
        private readonly EditionDivisionPosterInvalidator $posterInvalidator,
    ) {
        parent::__construct();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-divisions",
     *     summary="Get paginated list of edition divisions",
     *     tags={"🐧 Manager: Edition Divisions"},
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
     *             @OA\Property(property="edition_id", type="string", description="Filter by edition ID"),
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
     *             @OA\Property(property="id_edition_division", type="string", enum={"asc", "desc"}, description="Sort by edition division ID"),
     *             @OA\Property(property="edition_id", type="string", enum={"asc", "desc"}, description="Sort by edition ID"),
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
     *                         @OA\Items(ref="#/components/schemas/EditionDivision")
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
        $query = EditionDivision::with(['edition', 'division', 'courts']);

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            EditionDivision::$searchableFields,
            EditionDivision::$filterableFields,
            EditionDivision::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('skill_order')->orderBy('id_edition_division');
        }

        $editionDivisions = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return EditionDivisionResource::collection($editionDivisions);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}",
     *     summary="Get edition division details",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="editionDivision",
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
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionDivision")
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
     *         description="Edition division not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(EditionDivision $editionDivision)
    {
        $editionDivision->load(['edition', 'division', 'courts']);

        return $this->resourceResponse(
            new EditionDivisionResource($editionDivision),
            'Edition division details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-divisions",
     *     summary="Create a new edition division",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"edition_id", "division_id", "players_limit", "playing_start_time", "playing_end_time"},
     *             @OA\Property(property="edition_id", type="integer", description="Edition ID"),
     *             @OA\Property(property="division_id", type="integer", description="Division ID"),
     *             @OA\Property(property="players_limit", type="integer", description="Players limit"),
     *             @OA\Property(property="division_title", type="string", maxLength=64, description="Division title. Defaults to the division name when omitted"),
     *             @OA\Property(property="playing_start_time", type="string", format="time", description="Playing start time"),
     *             @OA\Property(property="playing_end_time", type="string", format="time", description="Playing end time"),
     *             @OA\Property(property="skill_order", type="integer", description="Ordering of divisions by skill within the edition. Defaults to the next free slot in the edition")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Edition division created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionDivision")
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
                'edition_id' => 'required|integer|exists:editions,id_edition',
                'division_id' => 'required|integer|exists:divisions,id_division',
                'division_title' => 'nullable|string|max:64',
                'players_limit' => 'required|integer|min:1',
                'playing_start_time' => ['required', PlayingWindow::clockRegexRule()],
                'playing_end_time' => ['required', PlayingWindow::clockRegexRule()],
                'skill_order' => 'nullable|integer|min:0',
            ], $this->movedScheduleKeyRules()),
            $this->movedScheduleKeyMessages()
        );

        $edition = Edition::query()->find($validated['edition_id']);
        if ($edition?->finalized_at !== null) {
            return $this->errorResponse('Edition divisions cannot be created after finalize.', 409);
        }

        // Check if this edition-division combination already exists
        $existingDivision = EditionDivision::where('edition_id', $validated['edition_id'])
            ->where('division_id', $validated['division_id'])
            ->first();

        if ($existingDivision) {
            return $this->errorResponse(
                'This division is already assigned to this edition',
                422
            );
        }

        if (($validated['division_title'] ?? null) === null || $validated['division_title'] === '') {
            $validated['division_title'] = (string) Division::query()
                ->where('id_division', $validated['division_id'])
                ->value('name');
        }

        $validated['skill_order'] = $validated['skill_order']
            ?? $this->nextSkillOrder((int) $validated['edition_id']);
        $validated['playing_start_time'] = PlayingWindow::normalizeStoredTime((string) $validated['playing_start_time']);
        $validated['playing_end_time'] = PlayingWindow::normalizeStoredTime((string) $validated['playing_end_time']);

        $editionDivision = EditionDivision::create($validated);
        $editionDivision->load(['edition', 'division', 'courts']);

        return $this->resourceResponse(
            new EditionDivisionResource($editionDivision),
            'Edition division created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}",
     *     summary="Update edition division details (complete replacement)",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="editionDivision",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="edition_id", type="integer", description="Edition ID"),
     *             @OA\Property(property="division_id", type="integer", description="Division ID"),
     *             @OA\Property(property="players_limit", type="integer", description="Players limit"),
     *             @OA\Property(property="division_title", type="string", maxLength=64, description="Division title"),
     *             @OA\Property(property="playing_start_time", type="string", format="time", description="Playing start time"),
     *             @OA\Property(property="playing_end_time", type="string", format="time", description="Playing end time"),
     *             @OA\Property(property="skill_order", type="integer", description="Ordering of divisions by skill within the edition")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Edition division updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionDivision")
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
     *         description="Edition division not found",
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
    public function update(Request $request, EditionDivision $editionDivision)
    {
        $editionDivision->loadMissing('edition');
        if ($editionDivision->edition?->finalized_at !== null) {
            return $this->errorResponse('Edition divisions cannot be updated after finalize.', 409);
        }

        $validated = $request->validate(
            array_merge([
                'edition_id' => 'required|integer|exists:editions,id_edition',
                'division_id' => 'required|integer|exists:divisions,id_division',
                'division_title' => 'required|string|max:64',
                'players_limit' => 'required|integer|min:1',
                'playing_start_time' => ['required', PlayingWindow::clockRegexRule()],
                'playing_end_time' => ['required', PlayingWindow::clockRegexRule()],
                'skill_order' => 'nullable|integer|min:0',
            ], $this->movedScheduleKeyRules()),
            $this->movedScheduleKeyMessages()
        );

        // Check if this edition-division combination already exists (excluding current record)
        if (isset($validated['edition_id']) || isset($validated['division_id'])) {
            $editionId = $validated['edition_id'] ?? $editionDivision->edition_id;
            $divisionId = $validated['division_id'] ?? $editionDivision->division_id;

            $existingDivision = EditionDivision::where('id_edition_division', '!=', $editionDivision->id_edition_division)
                ->where('edition_id', $editionId)
                ->where('division_id', $divisionId)
                ->first();

            if ($existingDivision) {
                return $this->errorResponse(
                    'This division is already assigned to this edition',
                    422
                );
            }
        }

        if (isset($validated['playing_start_time'])) {
            $validated['playing_start_time'] = PlayingWindow::normalizeStoredTime((string) $validated['playing_start_time']);
        }
        if (isset($validated['playing_end_time'])) {
            $validated['playing_end_time'] = PlayingWindow::normalizeStoredTime((string) $validated['playing_end_time']);
        }

        $this->applyAndInvalidatePosters($editionDivision, $validated);
        $editionDivision->load(['edition', 'division', 'courts']);

        return $this->resourceResponse(
            new EditionDivisionResource($editionDivision),
            'Edition division updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}",
     *     summary="Partially update edition division details",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="editionDivision",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="edition_id", type="integer", description="Edition ID"),
     *             @OA\Property(property="division_id", type="integer", description="Division ID"),
     *             @OA\Property(property="players_limit", type="integer", description="Players limit"),
     *             @OA\Property(property="division_title", type="string", maxLength=64, description="Division title"),
     *             @OA\Property(property="playing_start_time", type="string", format="time", description="Playing start time"),
     *             @OA\Property(property="playing_end_time", type="string", format="time", description="Playing end time"),
     *             @OA\Property(property="skill_order", type="integer", description="Ordering of divisions by skill within the edition")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Edition division partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionDivision")
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
     *         description="Edition division not found",
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
    public function patch(Request $request, EditionDivision $editionDivision)
    {
        $editionDivision->loadMissing('edition');
        if ($editionDivision->edition?->finalized_at !== null) {
            return $this->errorResponse('Edition divisions cannot be updated after finalize.', 409);
        }

        $validated = $request->validate(
            array_merge([
                'edition_id' => 'sometimes|integer|exists:editions,id_edition',
                'division_id' => 'sometimes|integer|exists:divisions,id_division',
                'division_title' => 'sometimes|string|max:64',
                'players_limit' => 'sometimes|integer|min:1',
                'playing_start_time' => ['sometimes', PlayingWindow::clockRegexRule()],
                'playing_end_time' => ['sometimes', PlayingWindow::clockRegexRule()],
                'skill_order' => 'sometimes|integer|min:0',
            ], $this->movedScheduleKeyRules()),
            $this->movedScheduleKeyMessages()
        );

        // Check if this edition-division combination already exists (excluding current record)
        if (isset($validated['edition_id']) || isset($validated['division_id'])) {
            $editionId = $validated['edition_id'] ?? $editionDivision->edition_id;
            $divisionId = $validated['division_id'] ?? $editionDivision->division_id;

            $existingDivision = EditionDivision::where('id_edition_division', '!=', $editionDivision->id_edition_division)
                ->where('edition_id', $editionId)
                ->where('division_id', $divisionId)
                ->first();

            if ($existingDivision) {
                return $this->errorResponse(
                    'This division is already assigned to this edition',
                    422
                );
            }
        }

        if (isset($validated['playing_start_time'])) {
            $validated['playing_start_time'] = PlayingWindow::normalizeStoredTime((string) $validated['playing_start_time']);
        }
        if (isset($validated['playing_end_time'])) {
            $validated['playing_end_time'] = PlayingWindow::normalizeStoredTime((string) $validated['playing_end_time']);
        }

        $this->applyAndInvalidatePosters($editionDivision, $validated);
        $editionDivision->load(['edition', 'division', 'courts']);

        return $this->resourceResponse(
            new EditionDivisionResource($editionDivision),
            'Edition division partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}",
     *     summary="Delete an edition division",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="editionDivision",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Edition division deleted successfully",
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
     *         description="Edition division not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Edition division cannot be deleted due to linked data",
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
    public function destroy(EditionDivision $editionDivision)
    {
        $editionDivision->loadMissing('edition');
        if ($editionDivision->edition?->finalized_at !== null) {
            return $this->errorResponse('Edition divisions cannot be deleted after finalize.', 409);
        }

        // Check if edition division has related participations
        $participationsCount = EditionParticipation::where(
            'edition_division_id',
            $editionDivision->id_edition_division
        )->count();

        if ($participationsCount > 0) {
            return $this->errorResponse(
                'Edition division cannot be deleted because it has linked data. Please remove all associated participations first.',
                409,
                ['linked_data' => ['participations_count' => $participationsCount]]
            );
        }

        $editionDivision->delete();

        return $this->successResponse(null, 'Edition division deleted successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-divisions/bulk",
     *     summary="Bulk delete edition divisions",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"edition_division_ids"},
     *             @OA\Property(
     *                 property="edition_division_ids",
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
            'edition_division_ids' => 'required|array|min:1',
            'edition_division_ids.*' => 'integer',
        ]);

        $results = [
            'successful' => [],
            'failed' => [],
            'total_requested' => count($request->edition_division_ids),
            'total_successful' => 0,
            'total_failed' => 0,
        ];

        foreach ($request->edition_division_ids as $editionDivisionId) {
            try {
                $editionDivision = EditionDivision::find($editionDivisionId);

                if (! $editionDivision) {
                    $results['failed'][] = [
                        'id' => $editionDivisionId,
                        'reason' => 'Resource not found',
                    ];
                    $results['total_failed']++;

                    continue;
                }

                $editionDivision->loadMissing('edition');
                if ($editionDivision->edition?->finalized_at !== null) {
                    $results['failed'][] = [
                        'id' => $editionDivisionId,
                        'reason' => 'Edition divisions cannot be deleted after finalize.',
                    ];
                    $results['total_failed']++;

                    continue;
                }

                // Check if edition division has related participations
                $participationsCount = EditionParticipation::where(
                    'edition_division_id',
                    $editionDivision->id_edition_division
                )->count();

                if ($participationsCount > 0) {
                    $results['failed'][] = [
                        'id' => $editionDivisionId,
                        'reason' => 'Edition division cannot be deleted because it has linked data. Please remove all associated participations first.',
                        'linked_data' => ['participations_count' => $participationsCount],
                    ];
                    $results['total_failed']++;

                    continue;
                }

                $editionDivision->delete();

                $results['successful'][] = [
                    'id' => $editionDivisionId,
                    'name' => $editionDivision->division->name ?? $editionDivisionId,
                ];
                $results['total_successful']++;

            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $editionDivisionId,
                    'reason' => 'Unexpected error: ' . $e->getMessage(),
                ];
                $results['total_failed']++;
            }
        }

        // Determine response based on results
        if ($results['total_failed'] === 0) {
            return $this->successResponse(
                $results,
                'Edition divisions deleted successfully'
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
     *     path="/api/v1/manager/edition-divisions/reorder",
     *     summary="Rewrite skill_order for every division of one edition",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"edition_id", "divisions"},
     *             @OA\Property(property="edition_id", type="integer", description="Edition owning every listed division"),
     *             @OA\Property(
     *                 property="divisions",
     *                 type="array",
     *                 description="Must list every division of the edition exactly once",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"id_edition_division", "skill_order"},
     *                     @OA\Property(property="id_edition_division", type="integer"),
     *                     @OA\Property(property="skill_order", type="integer", minimum=0)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Edition divisions reordered successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EditionDivision"))
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
     *         description="Edition is finalized",
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
            'edition_id' => 'required|integer|exists:editions,id_edition',
            'divisions' => 'required|array|min:1',
            'divisions.*.id_edition_division' => 'required|integer|distinct',
            'divisions.*.skill_order' => 'required|integer|min:0',
        ]);

        $editionId = (int) $validated['edition_id'];

        $edition = Edition::query()->find($editionId);
        if ($edition?->finalized_at !== null) {
            return $this->errorResponse('Edition divisions cannot be reordered after finalize.', 409);
        }

        $existing = EditionDivision::query()
            ->where('edition_id', $editionId)
            ->get()
            ->keyBy('id_edition_division');

        $requestedIds = array_map(
            static fn (array $row): int => (int) $row['id_edition_division'],
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
                ['missing_edition_division_ids' => $missing]
            );
        }

        $unknown = array_values(array_diff($requestedIds, $existing->keys()->map(static fn ($id): int => (int) $id)->all()));
        if ($unknown !== []) {
            return $this->errorResponse(
                'One or more edition divisions do not belong to this edition.',
                422,
                ['unknown_edition_division_ids' => $unknown]
            );
        }

        $ordered = $validated['divisions'];
        usort($ordered, static function (array $left, array $right): int {
            return [(int) $left['skill_order'], (int) $left['id_edition_division']]
                <=> [(int) $right['skill_order'], (int) $right['id_edition_division']];
        });

        $reordered = DB::transaction(function () use ($ordered, $existing, $editionId) {
            foreach ($ordered as $index => $row) {
                $editionDivision = $existing->get((int) $row['id_edition_division']);
                $editionDivision->skill_order = $index;
                $editionDivision->save();
            }

            return EditionDivision::query()
                ->with(['edition', 'division', 'courts'])
                ->where('edition_id', $editionId)
                ->orderBy('skill_order')
                ->orderBy('id_edition_division')
                ->get();
        });

        return $this->successResponse(
            EditionDivisionResource::collection($reordered),
            'Edition divisions reordered successfully'
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
                'The %s field moved to the edition division schedule. Send it to /edition-divisions/{editionDivision}/schedule/form instead.',
                $key
            );
        }

        return $messages;
    }

    private function nextSkillOrder(int $editionId): int
    {
        $max = EditionDivision::query()
            ->where('edition_id', $editionId)
            ->max('skill_order');

        return $max === null ? 0 : ((int) $max + 1);
    }

    /**
     * Playing window edits change every generated poster, so they are cleared.
     *
     * @param  array<string, mixed>  $validated
     */
    private function applyAndInvalidatePosters(EditionDivision $editionDivision, array $validated): void
    {
        $playingWindowChanged = false;
        foreach (['playing_start_time', 'playing_end_time'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }
            $incoming = PlayingWindow::toClockString($validated[$field]);
            $current = PlayingWindow::toClockString($editionDivision->getAttribute($field));
            if ($incoming !== $current) {
                $playingWindowChanged = true;
            }
        }

        $editionDivision->update($validated);

        if ($playingWindowChanged) {
            $this->posterInvalidator->clearMatchesAndRanking($editionDivision);
        }
    }
}
