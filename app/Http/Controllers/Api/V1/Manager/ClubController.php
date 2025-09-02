<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\ClubResource;
use App\Models\Club;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Clubs",
 *     description="(v1) Club management and operations"
 * )
 */
class ClubController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/clubs",
     *     summary="Get paginated list of clubs",
     *     tags={"🐧 Manager: Clubs"},
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
     *         description="Search term for name and city fields",
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
     *             @OA\Property(property="city", type="string")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="city", type="string", enum={"asc", "desc"}),
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
     *                         @OA\Items(ref="#/components/schemas/Club")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Club::with('locations');

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            Club::$searchableFields,
            Club::$filterableFields,
            Club::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_club', 'desc');
        }

        $clubs = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return ClubResource::collection($clubs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/clubs/{club}",
     *     summary="Get club details",
     *     tags={"🐧 Manager: Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="club",
     *         in="path",
     *         required=true,
     *         description="Club ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Club")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Club not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(Club $club)
    {
        $club->load('locations');

        return $this->resourceResponse(
            new ClubResource($club),
            'Club details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/clubs",
     *     summary="Create a new club",
     *     tags={"🐧 Manager: Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "city"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="city", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Club created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Club")
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
        $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
        ]);

        $club = Club::create($request->all());

        return $this->resourceResponse(
            new ClubResource($club),
            'Club created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/clubs/{club}",
     *     summary="Update a club",
     *     tags={"🐧 Manager: Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="club",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Club updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Club")
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
    public function update(Request $request, Club $club)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:255',
        ]);

        $club->update($request->all());

        return $this->resourceResponse(
            new ClubResource($club),
            'Club updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/clubs/{club}",
     *     summary="Delete a club",
     *     tags={"🐧 Manager: Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="club",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Club deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Club cannot be deleted due to linked data",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="linked_data",
     *                         type="object",
     *                         description="Information about linked data preventing deletion",
     *                         @OA\Property(property="locations_count", type="integer", example=3),
     *                         @OA\Property(property="prizes_count", type="integer", example=2)
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function destroy(Club $club)
    {
        $linkedDataRelations = [
            'locations' => 'locations_count',
            'prizes' => 'prizes_count',
        ];

        return $this->deleteResourceWithResponse(
            $club,
            $linkedDataRelations,
            'club',
            'Club deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/clubs/bulk",
     *     summary="Bulk delete clubs",
     *     tags={"🐧 Manager: Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"club_ids"},
     *             @OA\Property(
     *                 property="club_ids",
     *                 type="array",
     *                 description="Array of club IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All clubs deleted successfully",
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
     *         description="Some clubs failed to delete (Multi-Status)",
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
     *         description="No clubs provided for deletion",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="All clubs failed to delete due to linked data",
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
            'club_ids' => 'required|array|min:1',
            'club_ids.*' => 'integer',
        ]);

        $linkedDataRelations = [
            'locations' => 'locations_count',
            'prizes' => 'prizes_count',
        ];

        return $this->deleteBulkResourcesWithResponse(
            $request->club_ids,
            Club::class,
            $linkedDataRelations,
            'club',
            'Clubs deleted successfully'
        );
    }
}
