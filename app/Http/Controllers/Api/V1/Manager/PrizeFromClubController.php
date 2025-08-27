<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\PrizeFromClub;
use App\Http\Resources\PrizeFromClubResource;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Manager: Prizes From Clubs",
 *     description="(v1) API Endpoints for prizes from clubs"
 * )
 */
class PrizeFromClubController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/prizes-from-clubs",
     *     summary="Get paginated list of prizes from clubs",
     *     tags={"Manager: Prizes From Clubs"},
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
    public function index()
    {
        $prizesFromClubs = PrizeFromClub::with(['club', 'prize'])
            ->orderBy('id_club_prize', 'desc')
            ->paginate($this->paginationLimit);

        return PrizeFromClubResource::collection($prizesFromClubs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/prizes-from-clubs/{prizeFromClub}",
     *     summary="Get prize from club details",
     *     tags={"Manager: Prizes From Clubs"},
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
}
