<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EditionLuckyOne;
use App\Models\EditionLottery;
use Illuminate\Http\Request;

class EditionLuckyOneController extends Controller
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
