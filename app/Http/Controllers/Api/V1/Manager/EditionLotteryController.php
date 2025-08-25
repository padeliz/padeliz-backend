<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Models\EditionLottery;
use App\Models\Edition;

/**
 * @OA\Tag(
 *     name="Manager: Editions Lottery",
 *     description="(v1) Edition lottery management and operations"
 * )
 */
class EditionLotteryController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function index(Edition $edition)
    {
        $lotteries = $edition->lotteries()
            ->with(['prize', 'division', 'luckyOnes.drawnParticipation.player'])
            ->get();

        return response()->json($lotteries);
    }

    public function show(EditionLottery $lottery)
    {
        return response()->json($lottery->load([
            'prize',
            'division',
            'luckyOnes.drawnParticipation.player'
        ]));
    }
}
