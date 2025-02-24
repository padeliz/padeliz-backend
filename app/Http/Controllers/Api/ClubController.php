<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Club;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Clubs",
 *     description="API Endpoints for clubs"
 * )
 */
class ClubController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/clubs",
     *     summary="Get list of clubs",
     *     tags={"Clubs"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Club")
     *         )
     *     )
     * )
     */
    public function index()
    {
        $clubs = Club::with('locations')->get();
        return response()->json($clubs);
    }

    /**
     * @OA\Get(
     *     path="/api/clubs/{club}",
     *     summary="Get club details",
     *     tags={"Clubs"},
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
     * @OA\Get(
     *     path="/api/clubs/{club}/locations",
     *     summary="Get club locations",
     *     tags={"Clubs"},
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
     *     path="/api/clubs/{club}/prizes",
     *     summary="Get club prizes",
     *     tags={"Clubs"},
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
     *             @OA\Items(ref="#/components/schemas/PrizeFromClub")
     *         )
     *     )
     * )
     */
    public function prizes(Club $club)
    {
        return response()->json($club->prizes()->with('prize')->get());
    }
}
