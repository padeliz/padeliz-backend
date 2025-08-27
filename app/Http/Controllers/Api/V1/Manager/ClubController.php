<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Http\Resources\ClubResource;
use App\Http\Resources\LocationResource;
use App\Http\Resources\PrizeFromClubResource;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Manager: Clubs",
 *     description="(v1) Club management and operations"
 * )
 */
class ClubController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/clubs",
     *     summary="Get paginated list of clubs",
     *     tags={"Manager: Clubs"},
     *     security={{"sanctum":{}}},
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
     *                         @OA\Items(ref="#/components/schemas/Club")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index()
    {
        $clubs = Club::with('locations')
            ->orderBy('name')
            ->paginate($this->paginationLimit);

        return ClubResource::collection($clubs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/clubs/{club}",
     *     summary="Get club details",
     *     tags={"Manager: Clubs"},
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
     *     tags={"Manager: Clubs"},
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
            'city' => 'required|string|max:255'
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
     *     tags={"Manager: Clubs"},
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
            'city' => 'sometimes|required|string|max:255'
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
     *     tags={"Manager: Clubs"},
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
     *     )
     * )
     */
    public function destroy(Club $club)
    {
        $club->delete();
        return $this->successResponse(
            null,
            'Club deleted successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/clubs/{club}/locations",
     *     summary="Get club locations",
     *     tags={"Manager: Clubs"},
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
     *                     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Location"))
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function locations(Club $club)
    {
        return $this->successResponse(
            LocationResource::collection($club->locations),
            'Club locations retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/clubs/{club}/prizes",
     *     summary="Get paginated list of club prizes",
     *     tags={"Manager: Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="club",
     *         in="path",
     *         required=true,
     *         description="Club ID",
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
     *                         @OA\Items(ref="#/components/schemas/PrizeFromClub")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function prizes(Club $club)
    {
        $prizes = $club->prizes()
            ->with('prize')
            ->paginate($this->paginationLimit);

        return PrizeFromClubResource::collection($prizes);
    }
}
