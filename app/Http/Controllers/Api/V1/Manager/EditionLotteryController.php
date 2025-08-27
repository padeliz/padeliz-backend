<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Models\EditionLottery;
use App\Models\Edition;
use App\Http\Resources\EditionLotteryResource;

/**
 * @OA\Tag(
 *     name="Manager: Editions Lottery",
 *     description="(v1) Edition lottery management and operations"
 * )
 */
class EditionLotteryController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/lotteries/{lottery}",
     *     summary="Get lottery details",
     *     tags={"Manager: Editions Lottery"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lottery details",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLottery")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function show(EditionLottery $lottery)
    {
        $lottery->load([
            'prize',
            'division',
            'luckyOnes.drawnParticipation.player'
        ]);

        return $this->resourceResponse(
            new EditionLotteryResource($lottery),
            'Lottery details retrieved successfully'
        );
    }
}
