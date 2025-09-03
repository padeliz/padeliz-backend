<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\PrizeFromClubResource;
use App\Models\PrizeFromClub;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Prizes From Clubs",
 *     description="(v1) API Endpoints for prizes from clubs"
 * )
 */
class PrizeFromClubController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/prizes-from-clubs",
     *     summary="Get paginated list of prizes from clubs",
     *     tags={"🐧 Manager: Prizes From Clubs"},
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
     *         description="Search term for box_1_text, box_2_text fields",
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
     *             @OA\Property(property="club_id", type="integer"),
     *             @OA\Property(property="prize_id", type="integer"),
     *             @OA\Property(property="box_1_bg_color", type="string"),
     *             @OA\Property(property="box_1_text_color", type="string"),
     *             @OA\Property(property="box_2_bg_color", type="string"),
     *             @OA\Property(property="box_2_text_color", type="string")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="club_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="prize_id", type="string", enum={"asc", "desc"}),
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
     *                         @OA\Items(ref="#/components/schemas/PrizeFromClub")
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
        $query = PrizeFromClub::with(['club', 'prize']);

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            PrizeFromClub::$searchableFields,
            PrizeFromClub::$filterableFields,
            PrizeFromClub::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_club_prize', 'desc');
        }

        $prizesFromClubs = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return PrizeFromClubResource::collection($prizesFromClubs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/prizes-from-clubs/{prizeFromClub}",
     *     summary="Get prize from club details",
     *     tags={"🐧 Manager: Prizes From Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="prizeFromClub",
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
     *                     @OA\Property(property="data", ref="#/components/schemas/PrizeFromClub")
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
     *         description="Prize from club not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(PrizeFromClub $prizeFromClub)
    {
        $prizeFromClub->load(['club', 'prize']);

        return $this->resourceResponse(
            new PrizeFromClubResource($prizeFromClub),
            'Prize from club details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/prizes-from-clubs",
     *     summary="Create a new prize from club",
     *     tags={"🐧 Manager: Prizes From Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"club_id", "prize_id"},
     *             @OA\Property(property="club_id", type="integer", description="Club ID"),
     *             @OA\Property(property="prize_id", type="integer", description="Prize ID"),
     *             @OA\Property(property="image", type="string", maxLength=100, description="Custom image URL"),
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
     *         description="Prize from club created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/PrizeFromClub")
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
            'club_id' => 'required|integer|exists:clubs,id_club',
            'prize_id' => 'required|integer|exists:prizes,id_prize',
            'image' => 'nullable|string|max:100',
            'box_1_text' => 'nullable|string|max:200',
            'box_1_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_1_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text' => 'nullable|string|max:200',
            'box_2_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
        ]);

        // Check if this club-prize combination already exists
        $existingPrize = PrizeFromClub::where('club_id', $validated['club_id'])
            ->where('prize_id', $validated['prize_id'])
            ->first();

        if ($existingPrize) {
            return $this->errorResponse(
                'This club already has this prize',
                422
            );
        }

        $prizeFromClub = PrizeFromClub::create($validated);
        $prizeFromClub->load(['club', 'prize']);

        return $this->resourceResponse(
            new PrizeFromClubResource($prizeFromClub),
            'Prize from club created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/prizes-from-clubs/{prizeFromClub}",
     *     summary="Update prize from club details (complete replacement)",
     *     tags={"🐧 Manager: Prizes From Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="prizeFromClub",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="club_id", type="integer", description="Club ID"),
     *             @OA\Property(property="prize_id", type="integer", description="Prize ID"),
     *             @OA\Property(property="image", type="string", maxLength=100, description="Custom image URL"),
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
     *         description="Prize from club updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/PrizeFromClub")
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
     *         description="Prize from club not found",
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
    public function update(Request $request, PrizeFromClub $prizeFromClub)
    {
        $validated = $request->validate([
            'club_id' => 'required|integer|exists:clubs,id_club',
            'prize_id' => 'required|integer|exists:prizes,id_prize',
            'image' => 'nullable|string|max:100',
            'box_1_text' => 'nullable|string|max:200',
            'box_1_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_1_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text' => 'nullable|string|max:200',
            'box_2_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
        ]);

        // Check if this club-prize combination already exists (excluding current record)
        if (isset($validated['club_id']) || isset($validated['prize_id'])) {
            $clubId = $validated['club_id'] ?? $prizeFromClub->club_id;
            $prizeId = $validated['prize_id'] ?? $prizeFromClub->prize_id;

            $existingPrize = PrizeFromClub::where('id_club_prize', '!=', $prizeFromClub->id_club_prize)
                ->where('club_id', $clubId)
                ->where('prize_id', $prizeId)
                ->first();

            if ($existingPrize) {
                return $this->errorResponse(
                    'This club already has this prize',
                    422
                );
            }
        }

        $prizeFromClub->update($validated);
        $prizeFromClub->load(['club', 'prize']);

        return $this->resourceResponse(
            new PrizeFromClubResource($prizeFromClub),
            'Prize from club updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/prizes-from-clubs/{prizeFromClub}",
     *     summary="Partially update prize from club details",
     *     tags={"🐧 Manager: Prizes From Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="prizeFromClub",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="club_id", type="integer", description="Club ID"),
     *             @OA\Property(property="prize_id", type="integer", description="Prize ID"),
     *             @OA\Property(property="image", type="string", maxLength=100, description="Custom image URL"),
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
     *         description="Prize from club partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/PrizeFromClub")
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
     *         description="Prize from club not found",
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
    public function patch(Request $request, PrizeFromClub $prizeFromClub)
    {
        $validated = $request->validate([
            'club_id' => 'sometimes|integer|exists:clubs,id_club',
            'prize_id' => 'sometimes|integer|exists:prizes,id_prize',
            'image' => 'nullable|string|max:100',
            'box_1_text' => 'nullable|string|max:200',
            'box_1_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_1_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text' => 'nullable|string|max:200',
            'box_2_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
        ]);

        // Check if this club-prize combination already exists (excluding current record)
        if (isset($validated['club_id']) || isset($validated['prize_id'])) {
            $clubId = $validated['club_id'] ?? $prizeFromClub->club_id;
            $prizeId = $validated['prize_id'] ?? $prizeFromClub->prize_id;

            $existingPrize = PrizeFromClub::where('id_club_prize', '!=', $prizeFromClub->id_club_prize)
                ->where('club_id', $clubId)
                ->where('prize_id', $prizeId)
                ->first();

            if ($existingPrize) {
                return $this->errorResponse(
                    'This club already has this prize',
                    422
                );
            }
        }

        $prizeFromClub->update($validated);
        $prizeFromClub->load(['club', 'prize']);

        return $this->resourceResponse(
            new PrizeFromClubResource($prizeFromClub),
            'Prize from club partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/prizes-from-clubs/{prizeFromClub}",
     *     summary="Delete a prize from club",
     *     tags={"🐧 Manager: Prizes From Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="prizeFromClub",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Prize from club deleted successfully",
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
     *         description="Prize from club not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function destroy(PrizeFromClub $prizeFromClub)
    {
        return $this->deleteResourceWithResponse(
            $prizeFromClub,
            [],
            'prize from club',
            'Prize from club deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/prizes-from-clubs/bulk",
     *     summary="Bulk delete prizes from clubs",
     *     tags={"🐧 Manager: Prizes From Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"prize_from_club_ids"},
     *             @OA\Property(
     *                 property="prize_from_club_ids",
     *                 type="array",
     *                 description="Array of prize from club IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All prizes from clubs deleted successfully",
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
     *         description="Some prizes from clubs failed to delete (Multi-Status)",
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
     *         description="No prizes from clubs provided for deletion",
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
            'prize_from_club_ids' => 'required|array|min:1',
            'prize_from_club_ids.*' => 'integer',
        ]);

        return $this->deleteBulkResourcesWithResponse(
            $request->prize_from_club_ids,
            PrizeFromClub::class,
            [],
            'prize from club',
            'Prizes from clubs deleted successfully'
        );
    }
}
