<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\EditionLuckyOne;
use App\Models\EditionLottery;
use App\Http\Resources\EditionLuckyOneResource;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Manager: Editions Lucky One",
 *     description="(v1) Edition lucky one management and operations"
 * )
 */
class EditionLuckyOneController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/lotteries/{lottery}/lucky-ones",
     *     summary="Get all lucky ones for a lottery",
     *     tags={"Manager: Editions Lucky One"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of lucky ones for the lottery",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/EditionLuckyOne")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index(EditionLottery $lottery)
    {
        $luckyOnes = $lottery->luckyOnes()
            ->with(['drawnParticipation.player'])
            ->get();

        return $this->resourceResponse(
            EditionLuckyOneResource::collection($luckyOnes),
            'Lucky ones retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/lucky-ones/{luckyOne}",
     *     summary="Get lucky one details",
     *     tags={"Manager: Editions Lucky One"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="luckyOne",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lucky one details",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLuckyOne")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function show(EditionLuckyOne $luckyOne)
    {
        $luckyOne->load(['drawnParticipation.player']);

        return $this->resourceResponse(
            new EditionLuckyOneResource($luckyOne),
            'Lucky one details retrieved successfully'
        );
    }
}
