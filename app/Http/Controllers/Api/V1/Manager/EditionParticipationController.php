<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\EditionParticipationResource;
use App\Models\{Edition, EditionParticipation, Player};
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Edition Participations",
 *     description="(v1) Edition participation management and operations"
 * )
 */
class EditionParticipationController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-participations",
     *     summary="Get all edition participations",
     *     tags={"🐧 Manager: Edition Participations"},
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
     *             @OA\Property(property="edition_id", type="string", description="Filter by edition ID"),
     *             @OA\Property(property="player_id", type="string", description="Filter by player ID"),
     *             @OA\Property(property="edition_division_id", type="string", description="Filter by edition division ID"),
     *             @OA\Property(property="is_minor", type="string", description="Filter by minor status (use 0 or 1)"),
     *             @OA\Property(property="is_student", type="string", description="Filter by student status (use 0 or 1)"),
     *             @OA\Property(property="discount", type="string", description="Filter by discount amount"),
     *             @OA\Property(property="payment", type="string", description="Filter by payment amount")
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
     *             @OA\Property(property="edition_id", type="string", enum={"asc", "desc"}, description="Sort by edition ID"),
     *             @OA\Property(property="player_id", type="string", enum={"asc", "desc"}, description="Sort by player ID"),
     *             @OA\Property(property="edition_division_id", type="string", enum={"asc", "desc"}, description="Sort by edition division ID"),
     *             @OA\Property(property="is_minor", type="string", enum={"asc", "desc"}, description="Sort by minor status"),
     *             @OA\Property(property="is_student", type="string", enum={"asc", "desc"}, description="Sort by student status"),
     *             @OA\Property(property="discount", type="string", enum={"asc", "desc"}, description="Sort by discount amount"),
     *             @OA\Property(property="payment", type="string", enum={"asc", "desc"}, description="Sort by payment amount"),
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
     *                         @OA\Items(ref="#/components/schemas/EditionParticipation")
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
        $query = EditionParticipation::with(['player', 'editionDivision', 'edition']);

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            EditionParticipation::$searchableFields,
            EditionParticipation::$filterableFields,
            EditionParticipation::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_participation', 'desc');
        }

        $editionParticipations = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return EditionParticipationResource::collection($editionParticipations);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-participations/{editionParticipation}",
     *     summary="Get edition participation details",
     *     tags={"🐧 Manager: Edition Participations"},
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
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionParticipation")
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
    public function show(EditionParticipation $editionParticipation)
    {
        $editionParticipation->load(['player', 'editionDivision', 'edition']);

        return $this->resourceResponse(
            new EditionParticipationResource($editionParticipation),
            'Participation details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-participations",
     *     summary="Create edition participation",
     *     tags={"🐧 Manager: Edition Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"edition_id", "player_id", "edition_division_id"},
     *             @OA\Property(property="edition_id", type="integer"),
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="edition_division_id", type="integer"),
     *             @OA\Property(property="is_minor", type="boolean", description="Is minor player"),
     *             @OA\Property(property="is_student", type="boolean", description="Is student"),
     *             @OA\Property(property="discount", type="integer", description="Discount amount"),
     *             @OA\Property(property="payment", type="integer", description="Payment amount"),
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
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionParticipation")
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
            'edition_id' => 'required|exists:editions,id_edition',
            'player_id' => 'required|exists:players,id_player',
            'edition_division_id' => 'required|exists:edition_divisions,id_edition_division',
            'is_minor' => 'boolean',
            'is_student' => 'boolean',
            'discount' => 'nullable|integer|min:0',
            'payment' => 'nullable|integer|min:0',
            'absence_reason' => 'nullable|in:SICK,FORGOT,EXAMS,RECONSIDERED',
            'ban_reason' => 'nullable|in:LEFT SUDDENLY,AGGRESSIVE PLAYER,LIBIDINOUS,',
        ]);

        // Check if player is already participating
        $existingParticipation = EditionParticipation::where([
            'edition_id' => $validated['edition_id'],
            'player_id' => $validated['player_id'],
        ])->first();

        if ($existingParticipation) {
            return $this->errorResponse('This player is already participating in this edition', 422);
        }

        // Set default values for optional fields
        $validated['is_minor'] = $validated['is_minor'] ?? false;
        $validated['is_student'] = $validated['is_student'] ?? false;
        $validated['discount'] = $validated['discount'] ?? 0;
        $validated['payment'] = $validated['payment'] ?? 0;

        $editionParticipation = EditionParticipation::create($validated);

        $editionParticipation->load(['player', 'editionDivision', 'edition']);

        return $this->resourceResponse(
            new EditionParticipationResource($editionParticipation),
            'Participation created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/edition-participations/{editionParticipation}",
     *     summary="Update edition participation details (complete replacement)",
     *     tags={"🐧 Manager: Edition Participations"},
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
     *             @OA\Property(property="player_id", type="integer", description="Player ID"),
     *             @OA\Property(property="edition_division_id", type="integer", description="Edition division ID"),
     *             @OA\Property(property="is_minor", type="boolean", description="Is minor player"),
     *             @OA\Property(property="is_student", type="boolean", description="Is student"),
     *             @OA\Property(property="discount", type="integer", description="Discount amount"),
     *             @OA\Property(property="payment", type="integer", description="Payment amount"),
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
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionParticipation")
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
     *         description="Edition or participation not found",
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
    public function update(Request $request, EditionParticipation $editionParticipation)
    {

        $validated = $request->validate([
            'edition_id' => 'required|exists:editions,id_edition',
            'player_id' => 'required|exists:players,id_player',
            'edition_division_id' => 'required|exists:edition_divisions,id_edition_division',
            'is_minor' => 'required|boolean',
            'is_student' => 'required|boolean',
            'discount' => 'nullable|integer|min:0',
            'payment' => 'nullable|integer|min:0',
            'absence_reason' => 'nullable|in:SICK,FORGOT,EXAMS,RECONSIDERED',
            'ban_reason' => 'nullable|in:LEFT SUDDENLY,AGGRESSIVE PLAYER,LIBIDINOUS,',
        ]);

        // Check if player is already participating (excluding current record)
        if (isset($validated['player_id']) && $validated['player_id'] !== $editionParticipation->player_id) {
            $existingParticipation = EditionParticipation::where([
                'edition_id' => $validated['edition_id'],
                'player_id' => $validated['player_id'],
            ])->first();

            if ($existingParticipation) {
                return $this->errorResponse('This player is already participating in this edition', 422);
            }
        }

        $editionParticipation->update($validated);
        $editionParticipation->load(['player', 'editionDivision', 'edition']);

        return $this->resourceResponse(
            new EditionParticipationResource($editionParticipation),
            'Participation updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/edition-participations/{editionParticipation}",
     *     summary="Partially update edition participation details",
     *     tags={"🐧 Manager: Edition Participations"},
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
     *             @OA\Property(property="player_id", type="integer", description="Player ID"),
     *             @OA\Property(property="edition_division_id", type="integer", description="Edition division ID"),
     *             @OA\Property(property="is_minor", type="boolean", description="Is minor player"),
     *             @OA\Property(property="is_student", type="boolean", description="Is student"),
     *             @OA\Property(property="discount", type="integer", description="Discount amount"),
     *             @OA\Property(property="payment", type="integer", description="Payment amount"),
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
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionParticipation")
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
     *         description="Edition or participation not found",
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
    public function patch(Request $request, EditionParticipation $editionParticipation)
    {
        $validated = $request->validate([
            'edition_id' => 'sometimes|exists:editions,id_edition',
            'player_id' => 'sometimes|exists:players,id_player',
            'edition_division_id' => 'sometimes|exists:edition_divisions,id_edition_division',
            'is_minor' => 'boolean',
            'is_student' => 'boolean',
            'discount' => 'nullable|integer|min:0',
            'payment' => 'nullable|integer|min:0',
            'absence_reason' => 'nullable|in:SICK,FORGOT,EXAMS,RECONSIDERED',
            'ban_reason' => 'nullable|in:LEFT SUDDENLY,AGGRESSIVE PLAYER,LIBIDINOUS,',
        ]);

        // Check if player is already participating (excluding current record)
        if (isset($validated['player_id']) && $validated['player_id'] !== $editionParticipation->player_id) {
            $existingParticipation = EditionParticipation::where([
                'edition_id' => $validated['edition_id'] ?? $editionParticipation->edition_id,
                'player_id' => $validated['player_id'],
            ])->first();

            if ($existingParticipation) {
                return $this->errorResponse('This player is already participating in this edition', 422);
            }
        }

        $editionParticipation->update($validated);
        $editionParticipation->load(['player', 'editionDivision', 'edition']);

        return $this->resourceResponse(
            new EditionParticipationResource($editionParticipation),
            'Participation partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-participations/{editionParticipation}",
     *     summary="Delete edition participation",
     *     tags={"🐧 Manager: Edition Participations"},
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
    public function destroy(EditionParticipation $editionParticipation)
    {

        return $this->deleteResourceWithResponse(
            $editionParticipation,
            [],
            'participation',
            'Participation deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-participations/bulk",
     *     summary="Bulk delete edition participations",
     *     tags={"🐧 Manager: Edition Participations"},
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
     *         description="Edition not found",
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

        foreach ($request->participation_ids as $editionParticipationId) {
            try {
                $editionParticipation = EditionParticipation::find($editionParticipationId);

                if (! $editionParticipation) {
                    $results['failed'][] = [
                        'id' => $editionParticipationId,
                        'reason' => 'Resource not found',
                    ];
                    $results['total_failed']++;

                    continue;
                }

                $editionParticipation->delete();

                $results['successful'][] = [
                    'id' => $editionParticipationId,
                    'name' => $editionParticipation->player->name ?? $editionParticipationId,
                ];
                $results['total_successful']++;

            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $editionParticipationId,
                    'reason' => 'Unexpected error: ' . $e->getMessage(),
                ];
                $results['total_failed']++;
            }
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
}
