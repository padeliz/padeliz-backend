<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Locations",
 *     description="API Endpoints for locations"
 * )
 */
class LocationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/locations",
     *     summary="Get paginated list of locations",
     *     tags={"Locations"},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/PaginatedResponse"),
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
        return response()->json(
            Location::with('club')
            ->orderBy('name')
            ->paginate($this->paginationLimit)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/locations/{location}",
     *     summary="Get location details",
     *     tags={"Locations"},
     *     @OA\Parameter(
     *         name="location",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Location")
     *     )
     * )
     */
    public function show(Location $location)
    {
        return response()->json($location->load('club'));
    }

    /**
     * @OA\Get(
     *     path="/api/locations/{location}/editions",
     *     summary="Get paginated list of location editions",
     *     tags={"Locations"},
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
     *                 @OA\Schema(ref="#/components/schemas/PaginatedResponse"),
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
        return response()->json(
            $location->editions()
                ->with(['divisions', 'location'])
                ->orderBy('date', 'desc')
                ->paginate($this->paginationLimit)
        );
    }
}
