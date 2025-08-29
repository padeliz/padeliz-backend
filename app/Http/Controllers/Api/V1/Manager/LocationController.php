<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Http\Resources\LocationResource;
use Illuminate\Http\Request;
use App\Http\Resources\EditionResource;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Locations",
 *     description="(v1) Location management and operations"
 * )
 */
class LocationController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/locations",
     *     summary="Get paginated list of locations",
     *     tags={"🐧 Manager: Locations"},
     *     @OA\Parameter(ref="#/components/parameters/page"),
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
     *     )
     * )
     */
    public function index()
    {
        $locations = Location::with('club')
            ->orderBy('name')
            ->paginate($this->paginationLimit);

        return LocationResource::collection($locations);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/locations/{location}",
     *     summary="Get location details",
     *     tags={"🐧 Manager: Locations"},
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
     *     )
     * )
     */
    public function show(Location $location)
    {
        $location->load('club');
        return $this->resourceResponse(
            new LocationResource($location),
            'Location details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/locations",
     *     summary="Create a new location",
     *     tags={"🐧 Manager: Locations"},
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
     *         response=422,
     *         description="Validation error",
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
     *     summary="Update location details",
     *     tags={"🐧 Manager: Locations"},
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function update(Request $request, Location $location)
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
            'Location updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/locations/{location}",
     *     summary="Delete a location",
     *     tags={"🐧 Manager: Locations"},
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
     *         response=409,
     *         description="Location cannot be deleted due to related records",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function destroy(Location $location)
    {
        // Check if location has related editions
        if ($location->editions()->exists()) {
            return $this->errorResponse(
                'Cannot delete location because it has related editions',
                409
            );
        }

        $location->delete();

        return $this->successResponse(null, 'Location deleted successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/locations/{location}/editions",
     *     summary="Get paginated list of location editions",
     *     tags={"🐧 Manager: Locations"},
     *     @OA\Parameter(
     *         name="location",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/page"),
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
     *                         @OA\Items(ref="#/components/schemas/Edition")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function editions(Location $location)
    {
        $editions = $location->editions()
            ->orderBy('date', 'desc')
            ->paginate($this->paginationLimit);

        return EditionResource::collection($editions);
    }
}
