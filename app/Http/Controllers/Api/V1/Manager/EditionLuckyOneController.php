<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\EditionLuckyOne;
use App\Models\EditionLottery;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Manager: Editions Lucky One",
 *     description="(v1) Edition lucky one management and operations"
 * )
 */
class EditionLuckyOneController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function index(EditionLottery $lottery)
    {
        $luckyOnes = $lottery->luckyOnes()
            ->with(['drawnParticipation.player'])
            ->get();

        return response()->json($luckyOnes);
    }

    public function show(EditionLuckyOne $luckyOne)
    {
        return response()->json($luckyOne->load(['drawnParticipation.player']));
    }
}
