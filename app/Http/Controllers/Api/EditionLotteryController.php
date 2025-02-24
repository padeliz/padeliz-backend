<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EditionLottery;
use App\Models\Edition;
use Illuminate\Http\Request;

class EditionLotteryController extends Controller
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
