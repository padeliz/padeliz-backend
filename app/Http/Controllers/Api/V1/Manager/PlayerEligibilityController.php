<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerEligibilityResource;
use App\Models\PlayerEligibility;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Player Eligibilities",
 *     description="(v1) Player eligibility management and operations"
 * )
 */
class PlayerEligibilityController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-eligibilities",
     *     summary="Get all eligibility records",
     *     tags={"🐧 Manager: Player Eligibilities"},
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
     *         description="Search term for name, first_name, last_name, phone, playtomic_id, rankedin_id, instagram_username, facebook_username, review_message fields",
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
     *             @OA\Property(property="skill_division", type="string", description="Filter by skill division"),
     *             @OA\Property(property="year_of_birth", type="string", description="Filter by year of birth"),
     *             @OA\Property(property="being_student", type="string", description="Filter by student status (use 0 or 1)"),
     *             @OA\Property(property="review_status", type="string", description="Filter by review status (pending, approved, rejected)")
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
     *             @OA\Property(property="id_eligibility", type="string", enum={"asc", "desc"}, description="Sort by eligibility ID"),
     *             @OA\Property(property="edition_id", type="string", enum={"asc", "desc"}, description="Sort by edition ID"),
     *             @OA\Property(property="player_id", type="string", enum={"asc", "desc"}, description="Sort by player ID"),
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}, description="Sort by name"),
     *             @OA\Property(property="first_name", type="string", enum={"asc", "desc"}, description="Sort by first name"),
     *             @OA\Property(property="last_name", type="string", enum={"asc", "desc"}, description="Sort by last name"),
     *             @OA\Property(property="skill_division", type="string", enum={"asc", "desc"}, description="Sort by skill division"),
     *             @OA\Property(property="year_of_birth", type="string", enum={"asc", "desc"}, description="Sort by year of birth"),
     *             @OA\Property(property="being_student", type="string", enum={"asc", "desc"}, description="Sort by student status"),
     *             @OA\Property(property="review_status", type="string", enum={"asc", "desc"}, description="Sort by review status"),
     *             @OA\Property(property="reviewed_at", type="string", enum={"asc", "desc"}, description="Sort by reviewed at"),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}, description="Sort by creation date"),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"}, description="Sort by update date"),
     *             @OA\Property(property="resubmitted_at", type="string", enum={"asc", "desc"}, description="Sort by resubmitted at")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of all eligibility records",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/PlayerEligibility")
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
        $query = PlayerEligibility::with(['player']);

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            PlayerEligibility::$searchableFields,
            PlayerEligibility::$filterableFields,
            PlayerEligibility::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_eligibility', 'desc');
        }

        $eligibilities = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return PlayerEligibilityResource::collection($eligibilities);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-eligibilities/{playerEligibility}",
     *     summary="Get eligibility details",
     *     tags={"🐧 Manager: Player Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="playerEligibility",
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
     *                     @OA\Property(property="data", ref="#/components/schemas/PlayerEligibility")
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
     *         description="Eligibility not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(PlayerEligibility $playerEligibility)
    {
        $playerEligibility->load('player');

        return $this->resourceResponse(
            new PlayerEligibilityResource($playerEligibility),
            'Eligibility details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/player-eligibilities",
     *     summary="Create a new eligibility record",
     *     tags={"🐧 Manager: Player Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"player_id", "edition_id", "review_status"},
     *             @OA\Property(property="player_id", type="integer", description="Player ID"),
     *             @OA\Property(property="edition_id", type="integer", description="Edition ID"),
     *             @OA\Property(property="review_status", type="string", enum={"approved", "rejected", "pending"}, description="Eligibility review status"),
     *             @OA\Property(property="review_message", type="string", description="Review message")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Eligibility created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/PlayerEligibility")
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
            'player_id' => 'required|integer|exists:players,id_player',
            'review_status' => 'required|in:approved,rejected,pending,discarded',
            'review_message' => 'nullable|string',
        ]);

        $now = time();

        if ($validated['review_status'] === 'pending') {
            $hasPending = PlayerEligibility::where('player_id', $validated['player_id'])
                ->where('review_status', 'pending')
                ->exists();
            if ($hasPending) {
                return $this->errorResponse('Player already has a pending eligibility', 422);
            }
        }

        $playerEligibility = PlayerEligibility::create([
            'player_id' => $validated['player_id'],
            'review_status' => $validated['review_status'],
            'review_message' => $validated['review_message'] ?? null,
            'submitted_at' => $now,
            'inserted_at' => $now,
            'updated_at' => null,
            'reviewed_at' => in_array($validated['review_status'], ['approved', 'rejected'], true) ? $now : null,
            'ready_for_review' => 0,
        ]);
        $playerEligibility->load('player');

        return $this->resourceResponse(
            new PlayerEligibilityResource($playerEligibility),
            'Eligibility created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/player-eligibilities/{playerEligibility}",
     *     summary="Update eligibility status (complete replacement)",
     *     tags={"🐧 Manager: Player Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="playerEligibility",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"review_status"},
     *             @OA\Property(property="review_status", type="string", enum={"approved", "rejected", "pending"}),
     *             @OA\Property(property="review_message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Eligibility updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/PlayerEligibility")
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
     *         description="Eligibility not found",
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
    public function update(Request $request, PlayerEligibility $playerEligibility)
    {
        $request->validate([
            'review_status' => 'required|in:approved,rejected,pending,discarded',
            'review_message' => 'nullable|string',
        ]);

        $now = time();

        if ($request->review_status === 'pending') {
            $hasOtherPending = PlayerEligibility::where('player_id', $playerEligibility->player_id)
                ->where('review_status', 'pending')
                ->where('id_eligibility', '!=', $playerEligibility->id_eligibility)
                ->exists();
            if ($hasOtherPending) {
                return $this->errorResponse('Player already has a pending eligibility', 422);
            }
        }

        $playerEligibility->update([
            'review_status' => $request->review_status,
            'review_message' => $request->review_message,
            'reviewed_at' => in_array($request->review_status, ['approved', 'rejected'], true) ? $now : $playerEligibility->reviewed_at,
            'updated_at' => $now,
        ]);

        return $this->resourceResponse(
            new PlayerEligibilityResource($playerEligibility),
            'Eligibility updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/player-eligibilities/{playerEligibility}",
     *     summary="Partially update eligibility status",
     *     tags={"🐧 Manager: Player Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="playerEligibility",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="review_status", type="string", enum={"approved", "rejected", "pending"}),
     *             @OA\Property(property="review_message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Eligibility partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/PlayerEligibility")
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
     *         description="Eligibility not found",
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
    public function patch(Request $request, PlayerEligibility $playerEligibility)
    {
        $request->validate([
            'review_status' => 'sometimes|in:approved,rejected,pending,discarded',
            'review_message' => 'nullable|string',
        ]);

        $now = time();

        if ($request->has('review_status') && $request->review_status === 'pending') {
            $hasOtherPending = PlayerEligibility::where('player_id', $playerEligibility->player_id)
                ->where('review_status', 'pending')
                ->where('id_eligibility', '!=', $playerEligibility->id_eligibility)
                ->exists();
            if ($hasOtherPending) {
                return $this->errorResponse('Player already has a pending eligibility', 422);
            }
        }
        $updates = [];
        if ($request->has('review_status')) {
            $updates['review_status'] = $request->review_status;
            if (in_array($request->review_status, ['approved', 'rejected'], true)) {
                $updates['reviewed_at'] = $now;
            }
        }
        if ($request->has('review_message')) {
            $updates['review_message'] = $request->review_message;
        }
        $updates['updated_at'] = $now;

        $playerEligibility->update($updates);

        return $this->resourceResponse(
            new PlayerEligibilityResource($playerEligibility),
            'Eligibility partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/player-eligibilities/{playerEligibility}",
     *     summary="Delete an eligibility record",
     *     tags={"🐧 Manager: Player Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="playerEligibility",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Eligibility deleted successfully",
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
     *         description="Eligibility not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Cannot delete eligibility due to linked data",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function destroy(PlayerEligibility $playerEligibility)
    {
        // PlayerEligibility has no relationships that would prevent deletion
        $linkedDataRelations = [];

        return $this->deleteResourceWithResponse(
            $playerEligibility,
            $linkedDataRelations,
            'eligibility',
            'Eligibility deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/eligibility/bulk",
     *     summary="Bulk delete eligibility records",
     *     tags={"🐧 Manager: Player Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"eligibility_ids"},
     *             @OA\Property(
     *                 property="eligibility_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 description="Array of eligibility IDs to delete"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All eligibilities deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=207,
     *         description="Some eligibilities failed to delete",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - no eligibilities provided",
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="All eligibilities failed to delete due to linked data",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'eligibility_ids' => 'required|array|min:1',
            'eligibility_ids.*' => 'integer',
        ]);

        // PlayerEligibility has no relationships that would prevent deletion
        $linkedDataRelations = [];

        return $this->deleteBulkResourcesWithResponse(
            $request->eligibility_ids,
            PlayerEligibility::class,
            $linkedDataRelations,
            'eligibility',
            'Eligibilities deleted successfully'
        );
    }
}
