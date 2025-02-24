<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prize;
use Illuminate\Http\Request;

class PrizeController extends Controller
{
    public function index()
    {
        $prizes = Prize::with('clubPrizes.club')->get();
        return response()->json($prizes);
    }

    public function show(Prize $prize)
    {
        return response()->json($prize->load('clubPrizes.club'));
    }
}
