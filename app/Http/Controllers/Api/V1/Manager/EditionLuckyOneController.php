<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionLuckyOneResource;
use App\Models\EditionLuckyOne;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Edition Lucky One",
 *     description="(v1) Edition lucky one management and operations"
 * )
 */
class EditionLuckyOneController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-lucky-ones",
     *     summary="Get all lucky ones for a lottery",
     *     tags={"🐧 Manager: Edition Lucky One"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         description="No filter keys are supported for this resource (filterable_fields is empty on the model).",
     *         required=false,
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(
     *             type="object",
     *             additionalProperties=false
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
     *             @OA\Property(property="id_lucky_one", type="string", enum={"asc", "desc"}, description="Sort by lucky one ID"),
     *             @OA\Property(property="lottery_id", type="string", enum={"asc", "desc"}, description="Sort by lottery ID"),
     *             @OA\Property(property="drawn_participation_id", type="string", enum={"asc", "desc"}, description="Sort by drawn participation ID"),
     *             @OA\Property(property="drawn_at", type="string", enum={"asc", "desc"}, description="Sort by drawn at"),
     *             @OA\Property(property="available_at", type="string", enum={"asc", "desc"}, description="Sort by available at"),
     *             @OA\Property(property="announced_at", type="string", enum={"asc", "desc"}, description="Sort by announced at"),
     *             @OA\Property(property="accepted_at", type="string", enum={"asc", "desc"}, description="Sort by accepted at"),
     *             @OA\Property(property="rejected_at", type="string", enum={"asc", "desc"}, description="Sort by rejected at"),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}, description="Sort by creation date"),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"}, description="Sort by update date")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of lucky ones for the lottery",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/EditionLuckyOne")
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
        $query = EditionLuckyOne::with(['participation.player', 'lottery']);

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            EditionLuckyOne::$searchableFields,
            EditionLuckyOne::$filterableFields,
            EditionLuckyOne::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_lucky_one', 'desc');
        }

        $luckyOnes = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return EditionLuckyOneResource::collection($luckyOnes);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-lucky-ones/{luckyOne}",
     *     summary="Get lucky one details",
     *     tags={"🐧 Manager: Edition Lucky One"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="luckyOne",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lucky one details",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLuckyOne")
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
     *         description="Lucky one not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(EditionLuckyOne $luckyOne)
    {
        $luckyOne->load(['participation.player']);

        return $this->resourceResponse(
            new EditionLuckyOneResource($luckyOne),
            'Lucky one details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-lucky-ones",
     *     summary="Create a new lucky one",
     *     tags={"🐧 Manager: Edition Lucky One"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"lottery_id", "drawn_participation_id"},
     *             @OA\Property(property="lottery_id", type="integer", description="Lottery ID"),
     *             @OA\Property(property="drawn_participation_id", type="integer", description="Drawn participation ID"),
     *             @OA\Property(property="drawn_at", type="integer", description="Drawn timestamp"),
     *             @OA\Property(property="available_at", type="integer", description="Available timestamp"),
     *             @OA\Property(property="announced_at", type="integer", description="Announced timestamp"),
     *             @OA\Property(property="accepted_at", type="integer", description="Accepted timestamp"),
     *             @OA\Property(property="rejected_at", type="integer", description="Rejected timestamp")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Lucky one created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLuckyOne")
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
            'lottery_id' => 'required|integer|exists:edition_lotteries,id_lottery',
            'drawn_participation_id' => 'required|integer|exists:edition_participations,id_participation',
            'drawn_at' => 'integer',
            'available_at' => 'integer',
            'announced_at' => 'nullable|integer',
            'accepted_at' => 'nullable|integer',
            'rejected_at' => 'nullable|integer',
        ]);

        // Set default timestamps if not provided
        if (! isset($validated['drawn_at'])) {
            $validated['drawn_at'] = time();
        }
        if (! isset($validated['available_at'])) {
            $validated['available_at'] = time();
        }

        // Check if this participation is already a lucky one for this lottery
        $existingLuckyOne = EditionLuckyOne::where('lottery_id', $validated['lottery_id'])
            ->where('drawn_participation_id', $validated['drawn_participation_id'])
            ->first();

        if ($existingLuckyOne) {
            return $this->errorResponse(
                'This participation is already a lucky one for this lottery',
                422
            );
        }

        $luckyOne = EditionLuckyOne::create($validated);
        $luckyOne->load(['participation.player']);

        return $this->resourceResponse(
            new EditionLuckyOneResource($luckyOne),
            'Lucky one created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/edition-lucky-ones/{luckyOne}",
     *     summary="Update lucky one details (complete replacement)",
     *     tags={"🐧 Manager: Edition Lucky One"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="luckyOne",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="lottery_id", type="integer", description="Lottery ID"),
     *             @OA\Property(property="drawn_participation_id", type="integer", description="Drawn participation ID"),
     *             @OA\Property(property="drawn_at", type="integer", description="Drawn timestamp"),
     *             @OA\Property(property="available_at", type="integer", description="Available timestamp"),
     *             @OA\Property(property="announced_at", type="integer", description="Announced timestamp"),
     *             @OA\Property(property="accepted_at", type="integer", description="Accepted timestamp"),
     *             @OA\Property(property="rejected_at", type="integer", description="Rejected timestamp")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lucky one updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLuckyOne")
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
     *         description="Lucky one not found",
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
    public function update(Request $request, EditionLuckyOne $luckyOne)
    {
        $validated = $request->validate([
            'lottery_id' => 'required|integer|exists:edition_lotteries,id_lottery',
            'drawn_participation_id' => 'required|integer|exists:edition_participations,id_participation',
            'drawn_at' => 'required|integer',
            'available_at' => 'required|integer',
            'announced_at' => 'nullable|integer',
            'accepted_at' => 'nullable|integer',
            'rejected_at' => 'nullable|integer',
        ]);

        // Check if this participation is already a lucky one for this lottery (excluding current record)
        if (isset($validated['lottery_id']) || isset($validated['drawn_participation_id'])) {
            $lotteryId = $validated['lottery_id'] ?? $luckyOne->lottery_id;
            $participationId = $validated['drawn_participation_id'] ?? $luckyOne->drawn_participation_id;

            $existingLuckyOne = EditionLuckyOne::where('id_lucky_one', '!=', $luckyOne->id_lucky_one)
                ->where('lottery_id', $lotteryId)
                ->where('drawn_participation_id', $participationId)
                ->first();

            if ($existingLuckyOne) {
                return $this->errorResponse(
                    'This participation is already a lucky one for this lottery',
                    422
                );
            }
        }

        $luckyOne->update($validated);
        $luckyOne->load(['participation.player']);

        return $this->resourceResponse(
            new EditionLuckyOneResource($luckyOne),
            'Lucky one updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/edition-lucky-ones/{luckyOne}",
     *     summary="Partially update lucky one details",
     *     tags={"🐧 Manager: Edition Lucky One"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="luckyOne",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="lottery_id", type="integer", description="Lottery ID"),
     *             @OA\Property(property="drawn_participation_id", type="integer", description="Drawn participation ID"),
     *             @OA\Property(property="drawn_at", type="integer", description="Drawn timestamp"),
     *             @OA\Property(property="available_at", type="integer", description="Available timestamp"),
     *             @OA\Property(property="announced_at", type="integer", description="Announced timestamp"),
     *             @OA\Property(property="accepted_at", type="integer", description="Accepted timestamp"),
     *             @OA\Property(property="rejected_at", type="integer", description="Rejected timestamp")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lucky one partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLuckyOne")
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
     *         description="Lucky one not found",
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
    public function patch(Request $request, EditionLuckyOne $luckyOne)
    {
        $validated = $request->validate([
            'lottery_id' => 'sometimes|integer|exists:edition_lotteries,id_lottery',
            'drawn_participation_id' => 'sometimes|integer|exists:edition_participations,id_participation',
            'drawn_at' => 'integer',
            'available_at' => 'integer',
            'announced_at' => 'nullable|integer',
            'accepted_at' => 'nullable|integer',
            'rejected_at' => 'nullable|integer',
        ]);

        // Check if this participation is already a lucky one for this lottery (excluding current record)
        if (isset($validated['lottery_id']) || isset($validated['drawn_participation_id'])) {
            $lotteryId = $validated['lottery_id'] ?? $luckyOne->lottery_id;
            $participationId = $validated['drawn_participation_id'] ?? $luckyOne->drawn_participation_id;

            $existingLuckyOne = EditionLuckyOne::where('id_lucky_one', '!=', $luckyOne->id_lucky_one)
                ->where('lottery_id', $lotteryId)
                ->where('drawn_participation_id', $participationId)
                ->first();

            if ($existingLuckyOne) {
                return $this->errorResponse(
                    'This participation is already a lucky one for this lottery',
                    422
                );
            }
        }

        $luckyOne->update($validated);
        $luckyOne->load(['participation.player']);

        return $this->resourceResponse(
            new EditionLuckyOneResource($luckyOne),
            'Lucky one partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-lucky-ones/{luckyOne}",
     *     summary="Delete a lucky one",
     *     tags={"🐧 Manager: Edition Lucky One"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="luckyOne",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lucky one deleted successfully",
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
     *         description="Lucky one not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function destroy(EditionLuckyOne $luckyOne)
    {
        return $this->deleteResourceWithResponse(
            $luckyOne,
            [],
            'lucky one',
            'Lucky one deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-lucky-ones/bulk",
     *     summary="Bulk delete lucky ones",
     *     tags={"🐧 Manager: Edition Lucky One"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"lucky_one_ids"},
     *             @OA\Property(
     *                 property="lucky_one_ids",
     *                 type="array",
     *                 description="Array of lucky one IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All lucky ones deleted successfully",
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
     *         description="Some lucky ones failed to delete (Multi-Status)",
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
     *         description="No lucky ones provided for deletion",
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
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'lucky_one_ids' => 'required|array|min:1',
            'lucky_one_ids.*' => 'integer',
        ]);

        return $this->deleteBulkResourcesWithResponse(
            $request->lucky_one_ids,
            EditionLuckyOne::class,
            [],
            'lucky one',
            'Lucky ones deleted successfully'
        );
    }
}
