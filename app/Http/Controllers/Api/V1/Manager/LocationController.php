<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\LocationResource;
use App\Models\Location;
use App\Traits\CachesManagerResourceReads;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Locations",
 *     description="(v1) Location management and operations"
 * )
 */
class LocationController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    use CachesManagerResourceReads;

    /**
     * @OA\Get(
     *     path="/api/v1/manager/locations",
     *     summary="Get paginated list of locations",
     *     tags={"🐧 Manager: Locations"},
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
     *         description="Search term for name and address fields",
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
     *             @OA\Property(property="club_id", type="string", description="Filter by club ID"),
     *             @OA\Property(property="total_indoors_courts", type="string", description="Filter by indoor courts count"),
     *             @OA\Property(property="total_outdoor_courts", type="string", description="Filter by outdoor courts count")
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
     *             @OA\Property(property="id_location", type="string", enum={"asc", "desc"}, description="Sort by location ID"),
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}, description="Sort by name"),
     *             @OA\Property(property="address", type="string", enum={"asc", "desc"}, description="Sort by address"),
     *             @OA\Property(property="club_id", type="string", enum={"asc", "desc"}, description="Sort by club ID"),
     *             @OA\Property(property="total_indoors_courts", type="string", enum={"asc", "desc"}, description="Sort by indoor courts count"),
     *             @OA\Property(property="total_outdoor_courts", type="string", enum={"asc", "desc"}, description="Sort by outdoor courts count"),
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
     *                         @OA\Items(ref="#/components/schemas/Location")
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
        $payload = $this->cachedManagerIndex('locations', $request, [], function () use ($request) {
            $query = Location::with('club');

            $this->applyAdvancedPagination(
                $query,
                $request,
                Location::$searchableFields,
                Location::$filterableFields,
                Location::$sortableFields
            );

            $paginationParams = $this->getPaginationParams($request);

            if (! $request->has('sort_by')) {

                $query->orderBy('id_location', 'desc');

            }

            $locations = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

            return LocationResource::collection($locations)->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/locations/{location}",
     *     summary="Get location details",
     *     tags={"🐧 Manager: Locations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="location",
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
     *                     @OA\Property(property="data", ref="#/components/schemas/Location")
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
     *         description="Location not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(Location $location)
    {
        $data = $this->cachedManagerShow('locations', (int) $location->id_location, function () use ($location) {
            $location->load('club');

            return (new LocationResource($location))->resolve();
        });

        return $this->successResponse($data, 'Location details retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/locations",
     *     summary="Create a new location",
     *     tags={"🐧 Manager: Locations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"club_id", "name"},
     *             @OA\Property(property="club_id", type="integer", description="Club ID"),
     *             @OA\Property(property="name", type="string", maxLength=30, description="Location name"),
     *             @OA\Property(property="address", type="string", maxLength=200, description="Location address"),
     *             @OA\Property(property="total_indoors_courts", type="integer", description="Number of indoor courts"),
     *             @OA\Property(property="total_outdoor_courts", type="integer", description="Number of outdoor courts")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Location created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Location")
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
            'name' => 'required|string|max:30',
            'address' => 'nullable|string|max:200',
            'total_indoors_courts' => 'nullable|integer|min:0',
            'total_outdoor_courts' => 'nullable|integer|min:0',
        ]);

        $location = Location::create($validated);
        $location->load('club');

        return $this->resourceResponse(
            new LocationResource($location),
            'Location created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/locations/{location}",
     *     summary="Update location details (complete replacement)",
     *     tags={"🐧 Manager: Locations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="location",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="club_id", type="integer", description="Club ID"),
     *             @OA\Property(property="name", type="string", maxLength=30, description="Location name"),
     *             @OA\Property(property="address", type="string", maxLength=200, description="Location address"),
     *             @OA\Property(property="total_indoors_courts", type="integer", description="Number of indoor courts"),
     *             @OA\Property(property="total_outdoor_courts", type="integer", description="Number of outdoor courts")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Location")
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
     *         description="Location not found",
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
    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'club_id' => 'required|integer|exists:clubs,id_club',
            'name' => 'required|string|max:30',
            'address' => 'nullable|string|max:200',
            'total_indoors_courts' => 'nullable|integer|min:0',
            'total_outdoor_courts' => 'nullable|integer|min:0',
        ]);

        $location->update($validated);
        $location->load('club');

        return $this->resourceResponse(
            new LocationResource($location),
            'Location updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/locations/{location}",
     *     summary="Partially update location details",
     *     tags={"🐧 Manager: Locations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="location",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="club_id", type="integer", description="Club ID"),
     *             @OA\Property(property="name", type="string", maxLength=30, description="Location name"),
     *             @OA\Property(property="address", type="string", maxLength=200, description="Location address"),
     *             @OA\Property(property="total_indoors_courts", type="integer", description="Number of indoor courts"),
     *             @OA\Property(property="total_outdoor_courts", type="integer", description="Number of outdoor courts")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Location")
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
     *         description="Location not found",
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
    public function patch(Request $request, Location $location)
    {
        $validated = $request->validate([
            'club_id' => 'sometimes|integer|exists:clubs,id_club',
            'name' => 'sometimes|string|max:30',
            'address' => 'nullable|string|max:200',
            'total_indoors_courts' => 'nullable|integer|min:0',
            'total_outdoor_courts' => 'nullable|integer|min:0',
        ]);

        $location->update($validated);
        $location->load('club');

        return $this->resourceResponse(
            new LocationResource($location),
            'Location partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/locations/{location}",
     *     summary="Delete a location",
     *     tags={"🐧 Manager: Locations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="location",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location deleted successfully",
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
     *         description="Location not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Location cannot be deleted due to linked data",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="linked_data",
     *                         type="object",
     *                         description="Information about linked data preventing deletion",
     *                         @OA\Property(property="events_count", type="integer", example=3)
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
    public function destroy(Location $location)
    {
        $linkedDataRelations = [
            'events' => 'events_count',
        ];

        return $this->deleteResourceWithResponse(
            $location,
            $linkedDataRelations,
            'location',
            'Location deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/locations/bulk",
     *     summary="Bulk delete locations",
     *     tags={"🐧 Manager: Locations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"location_ids"},
     *             @OA\Property(
     *                 property="location_ids",
     *                 type="array",
     *                 description="Array of location IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All locations deleted successfully",
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
     *         description="Some locations failed to delete (Multi-Status)",
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
     *         description="No locations provided for deletion",
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
     *         description="All locations failed to delete due to linked data",
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
            'location_ids' => 'required|array|min:1',
            'location_ids.*' => 'integer',
        ]);

        $linkedDataRelations = [
            'events' => 'events_count',
        ];

        return $this->deleteBulkResourcesWithResponse(
            $request->location_ids,
            Location::class,
            $linkedDataRelations,
            'location',
            'Locations deleted successfully'
        );
    }
}
