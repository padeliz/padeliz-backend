<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionLotteryResource;
use App\Models\EditionLottery;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Editions Lottery",
 *     description="(v1) Edition lottery management and operations"
 * )
 */
class EditionLotteryController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/lotteries",
     *     summary="Get paginated list of lotteries",
     *     tags={"🐧 Manager: Editions Lottery"},
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
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="edition_id", type="integer"),
     *             @OA\Property(property="division_id", type="integer"),
     *             @OA\Property(property="prize_id", type="integer"),
     *             @OA\Property(property="orderliness", type="integer"),
     *             @OA\Property(property="total_draws_nr", type="integer")
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
     *             @OA\Property(property="prize_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="orderliness", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="total_draws_nr", type="string", enum={"asc", "desc"}),
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
     *                         @OA\Items(ref="#/components/schemas/EditionLottery")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = EditionLottery::with(['prize', 'division', 'edition']);

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
     *     path="/api/v1/manager/lotteries/{lottery}",
     *     summary="Get lottery details",
     *     tags={"🐧 Manager: Editions Lottery"},
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
     *     )
     * )
     */
    public function show(EditionLottery $lottery)
    {
        $lottery->load([
            'prize',
            'division',
            'luckyOnes.drawnParticipation.player',
        ]);

        return $this->resourceResponse(
            new EditionLotteryResource($lottery),
            'Lottery details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/lotteries",
     *     summary="Create a new lottery",
     *     tags={"🐧 Manager: Editions Lottery"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"edition_id", "total_draws_nr"},
     *             @OA\Property(property="edition_id", type="integer", description="Edition ID"),
     *             @OA\Property(property="orderliness", type="integer", description="Lottery order", default=0),
     *             @OA\Property(property="division_id", type="integer", description="Division ID (optional)"),
     *             @OA\Property(property="prize_id", type="integer", description="Prize ID (optional)"),
     *             @OA\Property(property="total_draws_nr", type="integer", description="Total number of draws")
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
            'orderliness' => 'integer|min:0',
            'division_id' => 'nullable|integer|exists:divisions,id_division',
            'prize_id' => 'nullable|integer|exists:prizes,id_prize',
            'total_draws_nr' => 'required|integer|min:1',
        ]);

        $lottery = EditionLottery::create($validated);
        $lottery->load(['prize', 'division', 'edition']);

        return $this->resourceResponse(
            new EditionLotteryResource($lottery),
            'Lottery created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/lotteries/{lottery}",
     *     summary="Update lottery details (complete replacement)",
     *     tags={"🐧 Manager: Editions Lottery"},
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
     *             @OA\Property(property="orderliness", type="integer", description="Lottery order"),
     *             @OA\Property(property="division_id", type="integer", description="Division ID (optional)"),
     *             @OA\Property(property="prize_id", type="integer", description="Prize ID (optional)"),
     *             @OA\Property(property="total_draws_nr", type="integer", description="Total number of draws")
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function update(Request $request, EditionLottery $lottery)
    {
        $validated = $request->validate([
            'edition_id' => 'required|integer|exists:editions,id_edition',
            'orderliness' => 'required|integer|min:0',
            'division_id' => 'nullable|integer|exists:divisions,id_division',
            'prize_id' => 'nullable|integer|exists:prizes,id_prize',
            'total_draws_nr' => 'required|integer|min:1',
        ]);

        $lottery->update($validated);
        $lottery->load(['prize', 'division', 'edition']);

        return $this->resourceResponse(
            new EditionLotteryResource($lottery),
            'Lottery updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/lotteries/{lottery}",
     *     summary="Partially update lottery details",
     *     tags={"🐧 Manager: Editions Lottery"},
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
     *             @OA\Property(property="orderliness", type="integer", description="Lottery order"),
     *             @OA\Property(property="division_id", type="integer", description="Division ID (optional)"),
     *             @OA\Property(property="prize_id", type="integer", description="Prize ID (optional)"),
     *             @OA\Property(property="total_draws_nr", type="integer", description="Total number of draws")
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function patch(Request $request, EditionLottery $lottery)
    {
        $validated = $request->validate([
            'edition_id' => 'sometimes|integer|exists:editions,id_edition',
            'orderliness' => 'integer|min:0',
            'division_id' => 'nullable|integer|exists:divisions,id_division',
            'prize_id' => 'nullable|integer|exists:prizes,id_prize',
            'total_draws_nr' => 'sometimes|integer|min:1',
        ]);

        $lottery->update($validated);
        $lottery->load(['prize', 'division', 'edition']);

        return $this->resourceResponse(
            new EditionLotteryResource($lottery),
            'Lottery partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/lotteries/{lottery}",
     *     summary="Delete a lottery",
     *     tags={"🐧 Manager: Editions Lottery"},
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
     *                         @OA\Property(property="lucky_ones_count", type="integer", example=5)
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function destroy(EditionLottery $lottery)
    {
        $linkedDataRelations = [
            'luckyOnes' => 'lucky_ones_count',
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
     *     path="/api/v1/manager/lotteries/bulk",
     *     summary="Bulk delete lotteries",
     *     tags={"🐧 Manager: Editions Lottery"},
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
            'lottery_ids' => 'required|array|min:1',
            'lottery_ids.*' => 'integer',
        ]);

        $linkedDataRelations = [
            'luckyOnes' => 'lucky_ones_count',
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
