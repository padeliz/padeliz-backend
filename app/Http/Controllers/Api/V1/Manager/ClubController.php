<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\Club;
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
     *                 @OA\Schema(ref="#/components/schemas/PaginatedResponse"),
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
        return response()->json(
            Club::with('locations')
            ->orderBy('name')
            ->paginate($this->paginationLimit)
        );
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
     *         @OA\JsonContent(ref="#/components/schemas/Club")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Club not found"
     *     )
     * )
     */
    public function show(Club $club)
    {
        return response()->json($club->load('locations'));
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
     *         description="Club created successfully"
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

        return response()->json($club, 201);
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
     *         description="Club updated successfully"
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

        return response()->json($club);
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
     *         description="Club deleted successfully"
     *     )
     * )
     */
    public function destroy(Club $club)
    {
        $club->delete();
        return response()->json(['message' => 'Club deleted successfully']);
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
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Location")
     *         )
     *     )
     * )
     */
    public function locations(Club $club)
    {
        return response()->json($club->locations);
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
     *                 @OA\Schema(ref="#/components/schemas/PaginatedResponse"),
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
        return response()->json(
            $club->prizes()
                ->with('prize')
                ->paginate($this->paginationLimit)
        );
    }
}
