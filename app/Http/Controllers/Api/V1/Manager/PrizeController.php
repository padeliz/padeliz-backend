<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\PrizeResource;
use App\Models\Prize;
use App\Traits\CachesManagerResourceReads;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Prizes",
 *     description="(v1) Prize management and operations"
 * )
 */
class PrizeController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    use CachesManagerResourceReads;
    /**
     * @OA\Get(
     *     path="/api/v1/manager/prizes",
     *     summary="Get paginated list of prizes",
     *     tags={"🐧 Manager: Prizes"},
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
     *         description="Search term for name, box_1_text, box_2_text fields",
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
     *             @OA\Property(property="box_1_bg_color", type="string", description="Filter by box 1 background color"),
     *             @OA\Property(property="box_1_text_color", type="string", description="Filter by box 1 text color"),
     *             @OA\Property(property="box_2_bg_color", type="string", description="Filter by box 2 background color"),
     *             @OA\Property(property="box_2_text_color", type="string", description="Filter by box 2 text color")
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
     *             @OA\Property(property="id_prize", type="string", enum={"asc", "desc"}, description="Sort by prize ID"),
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}, description="Sort by name"),
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
     *                         @OA\Items(ref="#/components/schemas/Prize")
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
        $payload = $this->cachedManagerIndex('prizes', $request, [], function () use ($request) {
            $query = Prize::with(['sponsorPrizes', 'lotteryPrizes']);

            $this->applyAdvancedPagination(
                $query,
                $request,
                Prize::$searchableFields,
                Prize::$filterableFields,
                Prize::$sortableFields
            );

            $paginationParams = $this->getPaginationParams($request);

            if (! $request->has('sort_by')) {
                $query->orderBy('id_prize', 'desc');
            }

            $prizes = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

            return PrizeResource::collection($prizes)->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/prizes/{prize}",
     *     summary="Get prize details",
     *     tags={"🐧 Manager: Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="prize",
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
     *                     @OA\Property(property="data", ref="#/components/schemas/Prize")
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
     *         description="Prize not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(Prize $prize)
    {
        $data = $this->cachedManagerShow('prizes', (int) $prize->id_prize, function () use ($prize) {
            $prize->load(['sponsorPrizes', 'lotteryPrizes']);

            return (new PrizeResource($prize))->resolve();
        });

        return $this->successResponse($data, 'Prize details retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/prizes",
     *     summary="Create a new prize",
     *     tags={"🐧 Manager: Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=60, description="Prize name"),
     *             @OA\Property(property="media_path", type="string", maxLength=512, description="Prize media storage path"),
     *             @OA\Property(property="box_1_text", type="string", maxLength=200, description="Box 1 text"),
     *             @OA\Property(property="box_1_bg_color", type="string", maxLength=7, description="Box 1 background color"),
     *             @OA\Property(property="box_1_text_color", type="string", maxLength=7, description="Box 1 text color"),
     *             @OA\Property(property="box_2_text", type="string", maxLength=200, description="Box 2 text"),
     *             @OA\Property(property="box_2_bg_color", type="string", maxLength=7, description="Box 2 background color"),
     *             @OA\Property(property="box_2_text_color", type="string", maxLength=7, description="Box 2 text color")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Prize created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Prize")
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
            'name' => 'required|string|max:60|unique:prizes,name',
            'media_path' => 'nullable|string|max:512',
            'box_1_text' => 'nullable|string|max:200',
            'box_1_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_1_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text' => 'nullable|string|max:200',
            'box_2_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $prize = Prize::create($validated);

        return $this->resourceResponse(
            new PrizeResource($prize),
            'Prize created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/prizes/{prize}",
     *     summary="Update prize details (complete replacement)",
     *     tags={"🐧 Manager: Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="prize",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=60, description="Prize name"),
     *             @OA\Property(property="media_path", type="string", maxLength=512, description="Prize media storage path"),
     *             @OA\Property(property="box_1_text", type="string", maxLength=200, description="Box 1 text"),
     *             @OA\Property(property="box_1_bg_color", type="string", maxLength=7, description="Box 1 background color"),
     *             @OA\Property(property="box_1_text_color", type="string", maxLength=7, description="Box 1 text color"),
     *             @OA\Property(property="box_2_text", type="string", maxLength=200, description="Box 2 text"),
     *             @OA\Property(property="box_2_bg_color", type="string", maxLength=7, description="Box 2 background color"),
     *             @OA\Property(property="box_2_text_color", type="string", maxLength=7, description="Box 2 text color")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Prize updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Prize")
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
     *         description="Prize not found",
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
    public function update(Request $request, Prize $prize)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:60|unique:prizes,name,' . $prize->id_prize . ',id_prize',
            'media_path' => 'nullable|string|max:512',
            'box_1_text' => 'nullable|string|max:200',
            'box_1_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_1_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text' => 'nullable|string|max:200',
            'box_2_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $prize->update($validated);

        return $this->resourceResponse(
            new PrizeResource($prize),
            'Prize updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/prizes/{prize}",
     *     summary="Partially update prize details",
     *     tags={"🐧 Manager: Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="prize",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=60, description="Prize name"),
     *             @OA\Property(property="media_path", type="string", maxLength=512, description="Prize media storage path"),
     *             @OA\Property(property="box_1_text", type="string", maxLength=200, description="Box 1 text"),
     *             @OA\Property(property="box_1_bg_color", type="string", maxLength=7, description="Box 1 background color"),
     *             @OA\Property(property="box_1_text_color", type="string", maxLength=7, description="Box 1 text color"),
     *             @OA\Property(property="box_2_text", type="string", maxLength=200, description="Box 2 text"),
     *             @OA\Property(property="box_2_bg_color", type="string", maxLength=7, description="Box 2 background color"),
     *             @OA\Property(property="box_2_text_color", type="string", maxLength=7, description="Box 2 text color")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Prize partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Prize")
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
     *         description="Prize not found",
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
    public function patch(Request $request, Prize $prize)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:60|unique:prizes,name,' . $prize->id_prize . ',id_prize',
            'media_path' => 'nullable|string|max:512',
            'box_1_text' => 'nullable|string|max:200',
            'box_1_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_1_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text' => 'nullable|string|max:200',
            'box_2_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $prize->update($validated);

        return $this->resourceResponse(
            new PrizeResource($prize),
            'Prize partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/prizes/{prize}",
     *     summary="Delete a prize",
     *     tags={"🐧 Manager: Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="prize",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Prize deleted successfully",
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
     *         description="Prize not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Prize cannot be deleted due to linked data",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="linked_data",
     *                         type="object",
     *                         description="Information about linked data preventing deletion",
     *                         @OA\Property(property="lottery_prizes_count", type="integer", example=2),
     *                         @OA\Property(property="sponsor_prizes_count", type="integer", example=3)
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
    public function destroy(Prize $prize)
    {
        $linkedDataRelations = [
            'lotteryPrizes' => 'lottery_prizes_count',
            'sponsorPrizes' => 'sponsor_prizes_count',
        ];

        return $this->deleteResourceWithResponse(
            $prize,
            $linkedDataRelations,
            'prize',
            'Prize deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/prizes/bulk",
     *     summary="Bulk delete prizes",
     *     tags={"🐧 Manager: Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"prize_ids"},
     *             @OA\Property(
     *                 property="prize_ids",
     *                 type="array",
     *                 description="Array of prize IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All prizes deleted successfully",
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
     *         description="Some prizes failed to delete (Multi-Status)",
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
     *         description="No prizes provided for deletion",
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
     *         description="All prizes failed to delete due to linked data",
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
            'prize_ids' => 'required|array|min:1',
            'prize_ids.*' => 'integer',
        ]);

        $linkedDataRelations = [
            'lotteryPrizes' => 'lottery_prizes_count',
            'sponsorPrizes' => 'sponsor_prizes_count',
        ];

        return $this->deleteBulkResourcesWithResponse(
            $request->prize_ids,
            Prize::class,
            $linkedDataRelations,
            'prize',
            'Prizes deleted successfully'
        );
    }
}
