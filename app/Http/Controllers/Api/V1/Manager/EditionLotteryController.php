<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionLotteryResource;
use App\Models\EditionLottery;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Edition Lotteries",
 *     description="(v1) Edition lottery management and operations"
 * )
 */
class EditionLotteryController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-lotteries",
     *     summary="Get paginated list of lotteries",
     *     tags={"🐧 Manager: Edition Lotteries"},
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
     *             @OA\Property(property="prize_id", type="string", description="Filter by prize ID"),
     *             @OA\Property(property="prize_quantity", type="string", description="Filter by prize quantity")
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
     *             @OA\Property(property="id_lottery", type="string", enum={"asc", "desc"}, description="Sort by lottery ID"),
     *             @OA\Property(property="edition_id", type="string", enum={"asc", "desc"}, description="Sort by edition ID"),
     *             @OA\Property(property="prize_id", type="string", enum={"asc", "desc"}, description="Sort by prize ID"),
     *             @OA\Property(property="prize_quantity", type="string", enum={"asc", "desc"}, description="Sort by prize quantity"),
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
     *                         @OA\Items(ref="#/components/schemas/EditionLottery")
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
        $query = EditionLottery::with(['prize', 'edition', 'rules']);

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            EditionLottery::$searchableFields,
            EditionLottery::$filterableFields,
            EditionLottery::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_lottery', 'desc');
        }

        $lotteries = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return EditionLotteryResource::collection($lotteries);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-lotteries/{lottery}",
     *     summary="Get lottery details",
     *     tags={"🐧 Manager: Edition Lotteries"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lottery details",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLottery")
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
     *         description="Lottery not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(EditionLottery $lottery)
    {
        $lottery->load([
            'prize',
            'edition',
            'rules',
        ]);

        return $this->resourceResponse(
            new EditionLotteryResource($lottery),
            'Lottery details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-lotteries",
     *     summary="Create a new lottery",
     *     tags={"🐧 Manager: Edition Lotteries"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"edition_id", "prize_quantity"},
     *             @OA\Property(property="edition_id", type="integer", description="Edition ID"),
     *             @OA\Property(property="prize_id", type="integer", description="Prize ID (optional)"),
     *             @OA\Property(property="prize_quantity", type="integer", description="Number of prizes available"),
     *             @OA\Property(
     *                 property="rules",
     *                 type="array",
     *                 description="Optional lottery rules to create with the lottery",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"rule_prize_quantity"},
     *                     @OA\Property(property="rule_prize_quantity", type="integer"),
     *                     @OA\Property(property="orderliness", type="integer"),
     *                     @OA\Property(property="edition_division_id", type="integer", nullable=true),
     *                     @OA\Property(property="gender", type="string", enum={"MALE", "FEMALE"}, nullable=true),
     *                     @OA\Property(property="has_social_status", type="string", enum={"STUDENT", "ADULT"}, nullable=true),
     *                     @OA\Property(property="legal_age", type="string", enum={"MINOR", "MAJOR"}, nullable=true),
     *                     @OA\Property(property="is_younger_than", type="integer", nullable=true),
     *                     @OA\Property(property="is_older_than", type="integer", nullable=true),
     *                     @OA\Property(property="has_tshirt_size", type="string", enum={"XS", "S", "M", "L", "XL", "XXL"}, nullable=true),
     *                     @OA\Property(property="is_guest", type="boolean", nullable=true),
     *                     @OA\Property(property="is_padel_first_timer", type="boolean", nullable=true),
     *                     @OA\Property(property="is_event_first_timer", type="boolean", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Lottery created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLottery")
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
            'edition_id' => 'required|integer|exists:editions,id_edition',
            'prize_id' => 'nullable|integer|exists:prizes,id_prize',
            'prize_quantity' => 'required|integer|min:1',
            'rules' => 'nullable|array',
            'rules.*.rule_prize_quantity' => 'required_with:rules|integer|min:1',
            'rules.*.orderliness' => 'integer|min:0',
            'rules.*.edition_division_id' => 'nullable|integer|exists:edition_divisions,id_edition_division',
            'rules.*.gender' => 'nullable|in:MALE,FEMALE',
            'rules.*.has_social_status' => 'nullable|in:STUDENT,ADULT',
            'rules.*.legal_age' => 'nullable|in:MINOR,MAJOR',
            'rules.*.is_younger_than' => 'nullable|integer|min:0',
            'rules.*.is_older_than' => 'nullable|integer|min:0',
            'rules.*.has_tshirt_size' => 'nullable|in:XS,S,M,L,XL,XXL',
            'rules.*.is_guest' => 'nullable|boolean',
            'rules.*.is_padel_first_timer' => 'nullable|boolean',
            'rules.*.is_event_first_timer' => 'nullable|boolean',
        ]);

        $rules = $validated['rules'] ?? [];
        unset($validated['rules']);

        $lottery = EditionLottery::create($validated);

        foreach ($rules as $rule) {
            $lottery->rules()->create($rule);
        }

        $lottery->load(['prize', 'edition', 'rules']);

        return $this->resourceResponse(
            new EditionLotteryResource($lottery),
            'Lottery created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/edition-lotteries/{lottery}",
     *     summary="Update lottery details (complete replacement)",
     *     tags={"🐧 Manager: Edition Lotteries"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="edition_id", type="integer", description="Edition ID"),
     *             @OA\Property(property="prize_id", type="integer", description="Prize ID (optional)"),
     *             @OA\Property(property="prize_quantity", type="integer", description="Number of prizes available")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lottery updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLottery")
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
     *         description="Lottery not found",
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
    public function update(Request $request, EditionLottery $lottery)
    {
        $validated = $request->validate([
            'edition_id' => 'required|integer|exists:editions,id_edition',
            'prize_id' => 'nullable|integer|exists:prizes,id_prize',
            'prize_quantity' => 'required|integer|min:1',
        ]);

        $lottery->update($validated);
        $lottery->load(['prize', 'edition', 'rules']);

        return $this->resourceResponse(
            new EditionLotteryResource($lottery),
            'Lottery updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/edition-lotteries/{lottery}",
     *     summary="Partially update lottery details",
     *     tags={"🐧 Manager: Edition Lotteries"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="edition_id", type="integer", description="Edition ID"),
     *             @OA\Property(property="prize_id", type="integer", description="Prize ID (optional)"),
     *             @OA\Property(property="prize_quantity", type="integer", description="Number of prizes available")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lottery partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLottery")
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
     *         description="Lottery not found",
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
    public function patch(Request $request, EditionLottery $lottery)
    {
        $validated = $request->validate([
            'edition_id' => 'sometimes|integer|exists:editions,id_edition',
            'prize_id' => 'nullable|integer|exists:prizes,id_prize',
            'prize_quantity' => 'sometimes|integer|min:1',
        ]);

        $lottery->update($validated);
        $lottery->load(['prize', 'edition', 'rules']);

        return $this->resourceResponse(
            new EditionLotteryResource($lottery),
            'Lottery partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-lotteries/{lottery}",
     *     summary="Delete a lottery",
     *     tags={"🐧 Manager: Edition Lotteries"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lottery deleted successfully",
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
     *         description="Lottery not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Lottery cannot be deleted due to linked data",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="linked_data",
     *                         type="object",
     *                         description="Information about linked data preventing deletion",
     *                         @OA\Property(property="luckies_count", type="integer", example=5)
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
    public function destroy(EditionLottery $lottery)
    {
        $linkedDataRelations = [
            'luckies' => 'luckies_count',
            'rules' => 'rules_count',
        ];

        return $this->deleteResourceWithResponse(
            $lottery,
            $linkedDataRelations,
            'lottery',
            'Lottery deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-lotteries/bulk",
     *     summary="Bulk delete lotteries",
     *     tags={"🐧 Manager: Edition Lotteries"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"lottery_ids"},
     *             @OA\Property(
     *                 property="lottery_ids",
     *                 type="array",
     *                 description="Array of lottery IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All lotteries deleted successfully",
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
     *         description="Some lotteries failed to delete (Multi-Status)",
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
     *         description="No lotteries provided for deletion",
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
     *         description="All lotteries failed to delete due to linked data",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         @OA\Property(property="successful", type="array", @OA\Items(type="object")),
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
            'lottery_ids' => 'required|array|min:1',
            'lottery_ids.*' => 'integer',
        ]);

        $linkedDataRelations = [
            'luckies' => 'luckies_count',
            'rules' => 'rules_count',
        ];

        return $this->deleteBulkResourcesWithResponse(
            $request->lottery_ids,
            EditionLottery::class,
            $linkedDataRelations,
            'lottery',
            'Lotteries deleted successfully'
        );
    }
}
