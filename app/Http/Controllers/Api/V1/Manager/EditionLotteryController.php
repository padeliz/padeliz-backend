<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Models\EditionLottery;
use App\Models\Edition;
use App\Http\Resources\EditionLotteryResource;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Editions Lottery",
 *     description="(v1) Edition lottery management and operations"
 * )
 */
class EditionLotteryController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/lotteries",
     *     summary="Get paginated list of lotteries",
     *     tags={"🐧 Manager: Editions Lottery"},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/EditionLottery")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index()
    {
        $lotteries = EditionLottery::with(['prize', 'division', 'edition'])
            ->orderBy('orderliness')
            ->paginate($this->paginationLimit);

        return EditionLotteryResource::collection($lotteries);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/lotteries/{lottery}",
     *     summary="Get lottery details",
     *     tags={"🐧 Manager: Editions Lottery"},
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

    /**
     * @OA\Post(
     *     path="/api/v1/manager/lotteries",
     *     summary="Create a new lottery",
     *     tags={"🐧 Manager: Editions Lottery"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"edition_id", "total_draws_nr"},
     *             @OA\Property(property="edition_id", type="integer", description="Edition ID"),
     *             @OA\Property(property="orderliness", type="integer", description="Lottery order", default=0),
     *             @OA\Property(property="division_id", type="integer", description="Division ID (optional)"),
     *             @OA\Property(property="prize_id", type="integer", description="Prize ID (optional)"),
     *             @OA\Property(property="total_draws_nr", type="integer", description="Total number of draws")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Lottery created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLottery")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'edition_id' => 'required|integer|exists:editions,id_edition',
            'orderliness' => 'integer|min:0',
            'division_id' => 'nullable|integer|exists:divisions,id_division',
            'prize_id' => 'nullable|integer|exists:prizes,id_prize',
            'total_draws_nr' => 'required|integer|min:1',
        ]);

        $lottery = EditionLottery::create($validated);
        $lottery->load(['prize', 'division', 'edition']);

        return $this->resourceResponse(
            new EditionLotteryResource($lottery),
            'Lottery created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/lotteries/{lottery}",
     *     summary="Update lottery details",
     *     tags={"🐧 Manager: Editions Lottery"},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="edition_id", type="integer", description="Edition ID"),
     *             @OA\Property(property="orderliness", type="integer", description="Lottery order"),
     *             @OA\Property(property="division_id", type="integer", description="Division ID (optional)"),
     *             @OA\Property(property="prize_id", type="integer", description="Prize ID (optional)"),
     *             @OA\Property(property="total_draws_nr", type="integer", description="Total number of draws")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lottery updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLottery")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function update(Request $request, EditionLottery $lottery)
    {
        $validated = $request->validate([
            'edition_id' => 'sometimes|integer|exists:editions,id_edition',
            'orderliness' => 'integer|min:0',
            'division_id' => 'nullable|integer|exists:divisions,id_division',
            'prize_id' => 'nullable|integer|exists:prizes,id_prize',
            'total_draws_nr' => 'sometimes|integer|min:1',
        ]);

        $lottery->update($validated);
        $lottery->load(['prize', 'division', 'edition']);

        return $this->resourceResponse(
            new EditionLotteryResource($lottery),
            'Lottery updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/lotteries/{lottery}",
     *     summary="Delete a lottery",
     *     tags={"🐧 Manager: Editions Lottery"},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lottery deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Lottery cannot be deleted due to related records",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function destroy(EditionLottery $lottery)
    {
        // Check if lottery has related lucky ones
        if ($lottery->luckyOnes()->exists()) {
            return $this->errorResponse(
                'Cannot delete lottery because it has related lucky ones',
                409
            );
        }

        $lottery->delete();

        return $this->successResponse(null, 'Lottery deleted successfully');
    }
}
