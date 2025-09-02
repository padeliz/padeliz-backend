<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionDivisionResource;
use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Editions Divisions",
 *     description="(v1) API Endpoints for edition divisions"
 * )
 */
class EditionDivisionController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-divisions",
     *     summary="Get paginated list of edition divisions",
     *     tags={"🐧 Manager: Editions Divisions"},
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
     *         description="Search term for court field",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         description="Filter parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="edition_id", type="integer"),
     *             @OA\Property(property="division_id", type="integer"),
     *             @OA\Property(property="players_limit", type="integer"),
     *             @OA\Property(property="player_opponents_nr", type="integer"),
     *             @OA\Property(property="player_matches_repeat", type="integer"),
     *             @OA\Property(property="include_scores", type="integer", enum={0, 1}, description="Include scores (use 0 or 1, not true/false)"),
     *             @OA\Property(property="has_demonstrative_match", type="integer", enum={0, 1}, description="Has demonstrative match (use 0 or 1, not true/false)"),
     *             @OA\Property(property="fixed_teams", type="integer", enum={0, 1}, description="Fixed teams (use 0 or 1, not true/false)")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="edition_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="division_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="players_limit", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="court", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="playing_start_time", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="playing_end_time", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="player_opponents_nr", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="player_matches_repeat", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="include_scores", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="has_demonstrative_match", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="fixed_teams", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"})
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
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = EditionDivision::with(['edition', 'division']);

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
            $query->orderBy('id_edition_division', 'desc');
        }

        $editionDivisions = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return EditionDivisionResource::collection($editionDivisions);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}",
     *     summary="Get edition division details",
     *     tags={"🐧 Manager: Editions Divisions"},
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
     *     )
     * )
     */
    public function show(EditionDivision $editionDivision)
    {
        $editionDivision->load(['edition', 'division']);

        return $this->resourceResponse(
            new EditionDivisionResource($editionDivision),
            'Edition division details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-divisions",
     *     summary="Create a new edition division",
     *     tags={"🐧 Manager: Editions Divisions"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"edition_id", "division_id", "players_limit", "court", "playing_start_time", "playing_end_time"},
     *             @OA\Property(property="edition_id", type="integer", description="Edition ID"),
     *             @OA\Property(property="division_id", type="integer", description="Division ID"),
     *             @OA\Property(property="players_limit", type="integer", description="Players limit"),
     *             @OA\Property(property="court", type="string", maxLength=30, description="Court name"),
     *             @OA\Property(property="playing_start_time", type="string", format="time", description="Playing start time"),
     *             @OA\Property(property="playing_end_time", type="string", format="time", description="Playing end time"),
     *             @OA\Property(property="player_opponents_nr", type="integer", description="Number of player opponents"),
     *             @OA\Property(property="player_matches_repeat", type="integer", description="Player matches repeat", default=1),
     *             @OA\Property(property="include_scores", type="integer", enum={0, 1}, description="Include scores (use 0 or 1, not true/false)", default=1),
     *             @OA\Property(property="has_demonstrative_match", type="integer", enum={0, 1}, description="Has demonstrative match (use 0 or 1, not true/false)", default=0),
     *             @OA\Property(property="fixed_teams", type="integer", enum={0, 1}, description="Fixed teams (use 0 or 1, not true/false)", default=0)
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'edition_id' => 'required|integer|exists:editions,id_edition',
            'division_id' => 'required|integer|exists:divisions,id_division',
            'players_limit' => 'required|integer|min:1',
            'court' => 'required|string|max:30',
            'playing_start_time' => 'required|date_format:H:i:s',
            'playing_end_time' => 'required|date_format:H:i:s|after:playing_start_time',
            'player_opponents_nr' => 'nullable|integer|min:1',
            'player_matches_repeat' => 'integer|min:1',
            'include_scores' => 'boolean',
            'has_demonstrative_match' => 'boolean',
            'fixed_teams' => 'boolean',
        ]);

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

        $editionDivision = EditionDivision::create($validated);
        $editionDivision->load(['edition', 'division']);

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
     *     tags={"🐧 Manager: Editions Divisions"},
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
     *             @OA\Property(property="court", type="string", maxLength=30, description="Court name"),
     *             @OA\Property(property="playing_start_time", type="string", format="time", description="Playing start time"),
     *             @OA\Property(property="playing_end_time", type="string", format="time", description="Playing end time"),
     *             @OA\Property(property="player_opponents_nr", type="integer", description="Number of player opponents"),
     *             @OA\Property(property="player_matches_repeat", type="integer", description="Player matches repeat"),
     *             @OA\Property(property="include_scores", type="integer", enum={0, 1}, description="Include scores (use 0 or 1, not true/false)"),
     *             @OA\Property(property="has_demonstrative_match", type="integer", enum={0, 1}, description="Has demonstrative match (use 0 or 1, not true/false)"),
     *             @OA\Property(property="fixed_teams", type="integer", enum={0, 1}, description="Fixed teams (use 0 or 1, not true/false)")
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function update(Request $request, EditionDivision $editionDivision)
    {
        $validated = $request->validate([
            'edition_id' => 'required|integer|exists:editions,id_edition',
            'division_id' => 'required|integer|exists:divisions,id_division',
            'players_limit' => 'required|integer|min:1',
            'court' => 'required|string|max:30',
            'playing_start_time' => 'required|date_format:H:i:s',
            'playing_end_time' => 'required|date_format:H:i:s',
            'player_opponents_nr' => 'nullable|integer|min:1',
            'player_matches_repeat' => 'required|integer|min:1',
            'include_scores' => 'required|boolean',
            'has_demonstrative_match' => 'required|boolean',
            'fixed_teams' => 'required|boolean',
        ]);

        // Validate playing_end_time is after playing_start_time if both are provided
        if (isset($validated['playing_start_time']) && isset($validated['playing_end_time'])) {
            if ($validated['playing_end_time'] <= $validated['playing_start_time']) {
                return $this->errorResponse(
                    'Playing end time must be after playing start time',
                    422
                );
            }
        }

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

        $editionDivision->update($validated);
        $editionDivision->load(['edition', 'division']);

        return $this->resourceResponse(
            new EditionDivisionResource($editionDivision),
            'Edition division updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}",
     *     summary="Partially update edition division details",
     *     tags={"🐧 Manager: Editions Divisions"},
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
     *             @OA\Property(property="court", type="string", maxLength=30, description="Court name"),
     *             @OA\Property(property="playing_start_time", type="string", format="time", description="Playing start time"),
     *             @OA\Property(property="playing_end_time", type="string", format="time", description="Playing end time"),
     *             @OA\Property(property="player_opponents_nr", type="integer", description="Number of player opponents"),
     *             @OA\Property(property="player_matches_repeat", type="integer", description="Player matches repeat"),
     *             @OA\Property(property="include_scores", type="integer", enum={0, 1}, description="Include scores (use 0 or 1, not true/false)"),
     *             @OA\Property(property="has_demonstrative_match", type="integer", enum={0, 1}, description="Has demonstrative match (use 0 or 1, not true/false)"),
     *             @OA\Property(property="fixed_teams", type="integer", enum={0, 1}, description="Fixed teams (use 0 or 1, not true/false)")
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function patch(Request $request, EditionDivision $editionDivision)
    {
        $validated = $request->validate([
            'edition_id' => 'sometimes|integer|exists:editions,id_edition',
            'division_id' => 'sometimes|integer|exists:divisions,id_division',
            'players_limit' => 'sometimes|integer|min:1',
            'court' => 'sometimes|string|max:30',
            'playing_start_time' => 'sometimes|date_format:H:i:s',
            'playing_end_time' => 'sometimes|date_format:H:i:s',
            'player_opponents_nr' => 'nullable|integer|min:1',
            'player_matches_repeat' => 'integer|min:1',
            'include_scores' => 'boolean',
            'has_demonstrative_match' => 'boolean',
            'fixed_teams' => 'boolean',
        ]);

        // Validate playing_end_time is after playing_start_time if both are provided
        if (isset($validated['playing_start_time']) && isset($validated['playing_end_time'])) {
            if ($validated['playing_end_time'] <= $validated['playing_start_time']) {
                return $this->errorResponse(
                    'Playing end time must be after playing start time',
                    422
                );
            }
        }

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

        $editionDivision->update($validated);
        $editionDivision->load(['edition', 'division']);

        return $this->resourceResponse(
            new EditionDivisionResource($editionDivision),
            'Edition division partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}",
     *     summary="Delete an edition division",
     *     tags={"🐧 Manager: Editions Divisions"},
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
     *     )
     * )
     */
    public function destroy(EditionDivision $editionDivision)
    {
        // Check if edition division has related participations
        $participationsCount = EditionParticipation::where([
            'edition_id' => $editionDivision->edition_id,
            'division_id' => $editionDivision->division_id,
        ])->count();

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
     *     tags={"🐧 Manager: Editions Divisions"},
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

                // Check if edition division has related participations
                $participationsCount = EditionParticipation::where([
                    'edition_id' => $editionDivision->edition_id,
                    'division_id' => $editionDivision->division_id,
                ])->count();

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
}
