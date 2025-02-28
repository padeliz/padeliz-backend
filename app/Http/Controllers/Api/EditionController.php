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
     *     summary="Get paginated list of editions",
     *     tags={"Editions"},
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
     *                         @OA\Items(ref="#/components/schemas/Edition")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index()
    {
        return response()->json(
            Edition::with(['location', 'divisions'])
            ->orderBy('date', 'desc')
            ->paginate($this->paginationLimit)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/editions/upcoming",
     *     summary="Get paginated list of upcoming editions",
     *     tags={"Editions"},
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
     *                         @OA\Items(ref="#/components/schemas/Edition")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function upcoming()
    {
        return response()->json(
            Edition::where('date', '>', Carbon::now())
                ->orderBy('date', 'asc')
            ->paginate($this->paginationLimit)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/editions/ongoing",
     *     summary="Get paginated list of ongoing editions",
     *     tags={"Editions"},
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
     *                         @OA\Items(ref="#/components/schemas/Edition")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function ongoing()
    {
        return response()->json(
            Edition::whereDate('date', Carbon::today())
                ->orderBy('date', 'asc')
            ->paginate($this->paginationLimit)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/editions/past",
     *     summary="Get paginated list of past editions",
     *     tags={"Editions"},
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
     *                         @OA\Items(ref="#/components/schemas/Edition")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function past()
    {
        return response()->json(
            Edition::where('date', '<', Carbon::today())
                ->orderBy('date', 'desc')
            ->paginate($this->paginationLimit)
        );
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
