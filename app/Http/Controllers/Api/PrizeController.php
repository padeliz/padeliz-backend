<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prize;
use Illuminate\Http\Request;

class PrizeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/prizes",
     *     summary="Get paginated list of prizes",
     *     tags={"Prizes"},
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
     *                         @OA\Items(ref="#/components/schemas/Prize")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index()
    {
        $prizes = Prize::query()
            ->orderByRaw('LOWER(name) ASC, name ASC')  // Case-insensitive + consistent tie-breaking
            ->paginate($this->paginationLimit);

        return response()->json($prizes);
    }

    public function show(Prize $prize)
    {
        return response()->json(
            $prize->load(['clubPrizes.club'])
        );
    }
}
