<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Editions",
 *     description="API Endpoints for editions"
 * )
 */
class EditionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/editions",
     *     summary="Get list of editions",
     *     tags={"Editions"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Edition"))
     *     )
     * )
     */
    public function index()
    {
        $editions = Edition::with(['location', 'divisions'])->get();
        return response()->json($editions);
    }

    /**
     * @OA\Get(
     *     path="/api/editions/upcoming",
     *     summary="Get upcoming editions",
     *     tags={"Editions"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Edition"))
     *     )
     * )
     */
    public function upcoming()
    {
        $editions = Edition::with(['location', 'divisions'])
            ->where('date', '>', Carbon::now())
            ->orderBy('date', 'desc')
            ->get();
        return response()->json($editions);
    }

    /**
     * @OA\Get(
     *     path="/api/editions/ongoing",
     *     summary="Get ongoing editions",
     *     tags={"Editions"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Edition"))
     *     )
     * )
     */
    public function ongoing()
    {
        $now = Carbon::now();
        $today = $now->format('Y-m-d');
        $currentTime = $now->format('H:i:s');

        $editions = Edition::with(['location', 'divisions'])
        ->whereDate('date', $today)
            ->where('arriving_time', '<=', $currentTime)
            ->where('closing_time', '>=', $currentTime)
            ->orderBy('date', 'desc')
            ->get();

        return response()->json($editions);
    }

    /**
     * @OA\Get(
     *     path="/api/editions/past",
     *     summary="Get past editions",
     *     tags={"Editions"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Edition"))
     *     )
     * )
     */
    public function past()
    {
        $editions = Edition::with(['location', 'divisions'])
            ->where('date', '<', Carbon::now())
            ->orderBy('date', 'desc')
            ->get();
        return response()->json($editions);
    }

    /**
     * @OA\Get(
     *     path="/api/editions/{edition}",
     *     summary="Get edition details",
     *     tags={"Editions"},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Edition")
     *     )
     * )
     */
    public function show(Edition $edition)
    {
        return response()->json($edition->load(['location', 'divisions']));
    }

    /**
     * @OA\Get(
     *     path="/api/editions/{edition}/divisions",
     *     summary="Get edition divisions",
     *     tags={"Editions"},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/EditionDivision"))
     *     )
     * )
     */
    public function divisions(Edition $edition)
    {
        return response()->json($edition->divisions()->with('division')->get());
    }

    /**
     * @OA\Get(
     *     path="/api/editions/{edition}/participants",
     *     summary="Get edition participants",
     *     tags={"Editions"},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/EditionParticipation"))
     *     )
     * )
     */
    public function participants(Edition $edition)
    {
        return response()->json($edition->participations()->with(['player', 'division'])->get());
    }

    /**
     * @OA\Get(
     *     path="/api/editions/{edition}/lotteries",
     *     summary="Get edition lotteries",
     *     tags={"Editions"},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/EditionLottery"))
     *     )
     * )
     */
    public function lotteries(Edition $edition)
    {
        return response()->json($edition->lotteries()->with(['prize', 'division', 'luckyOnes'])->get());
    }
}
