<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Http\Resources\LocationResource;
use Illuminate\Http\Request;
use App\Http\Resources\EditionResource;

/**
 * @OA\Tag(
 *     name="Manager: Locations",
 *     description="(v1) Location management and operations"
 * )
 */
class LocationController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/locations",
     *     summary="Get paginated list of locations",
     *     tags={"Manager: Locations"},
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
     *     tags={"Manager: Locations"},
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
     * @OA\Get(
     *     path="/api/v1/manager/locations/{location}/editions",
     *     summary="Get paginated list of location editions",
     *     tags={"Manager: Locations"},
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
