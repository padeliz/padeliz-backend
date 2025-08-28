<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\EditionLuckyOne;
use App\Models\EditionLottery;
use App\Http\Resources\EditionLuckyOneResource;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Manager: Editions Lucky One",
 *     description="(v1) Edition lucky one management and operations"
 * )
 */
class EditionLuckyOneController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/lotteries/{lottery}/lucky-ones",
     *     summary="Get all lucky ones for a lottery",
     *     tags={"Manager: Editions Lucky One"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of lucky ones for the lottery",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/EditionLuckyOne")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index(EditionLottery $lottery)
    {
        $luckyOnes = $lottery->luckyOnes()
            ->with(['participation.player'])
            ->get();

        return $this->resourceResponse(
            EditionLuckyOneResource::collection($luckyOnes),
            'Lucky ones retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/lucky-ones/{luckyOne}",
     *     summary="Get lucky one details",
     *     tags={"Manager: Editions Lucky One"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="luckyOne",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lucky one details",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLuckyOne")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function show(EditionLuckyOne $luckyOne)
    {
        $luckyOne->load(['participation.player']);

        return $this->resourceResponse(
            new EditionLuckyOneResource($luckyOne),
            'Lucky one details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/lucky-ones",
     *     summary="Create a new lucky one",
     *     tags={"Manager: Editions Lucky One"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"lottery_id", "drawn_participation_id"},
     *             @OA\Property(property="lottery_id", type="integer", description="Lottery ID"),
     *             @OA\Property(property="drawn_participation_id", type="integer", description="Drawn participation ID"),
     *             @OA\Property(property="drawn_at", type="integer", description="Drawn timestamp"),
     *             @OA\Property(property="available_at", type="integer", description="Available timestamp"),
     *             @OA\Property(property="announced_at", type="integer", description="Announced timestamp"),
     *             @OA\Property(property="accepted_at", type="integer", description="Accepted timestamp"),
     *             @OA\Property(property="rejected_at", type="integer", description="Rejected timestamp")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Lucky one created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLuckyOne")
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
            'lottery_id' => 'required|integer|exists:edition_lotteries,id_lottery',
            'drawn_participation_id' => 'required|integer|exists:edition_participations,id_participation',
            'drawn_at' => 'integer',
            'available_at' => 'integer',
            'announced_at' => 'nullable|integer',
            'accepted_at' => 'nullable|integer',
            'rejected_at' => 'nullable|integer',
        ]);

        // Set default timestamps if not provided
        if (!isset($validated['drawn_at'])) {
            $validated['drawn_at'] = time();
        }
        if (!isset($validated['available_at'])) {
            $validated['available_at'] = time();
        }

        // Check if this participation is already a lucky one for this lottery
        $existingLuckyOne = EditionLuckyOne::where('lottery_id', $validated['lottery_id'])
            ->where('drawn_participation_id', $validated['drawn_participation_id'])
            ->first();

        if ($existingLuckyOne) {
            return $this->errorResponse(
                'This participation is already a lucky one for this lottery',
                422
            );
        }

        $luckyOne = EditionLuckyOne::create($validated);
        $luckyOne->load(['participation.player']);

        return $this->resourceResponse(
            new EditionLuckyOneResource($luckyOne),
            'Lucky one created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/lucky-ones/{luckyOne}",
     *     summary="Update lucky one details",
     *     tags={"Manager: Editions Lucky One"},
     *     @OA\Parameter(
     *         name="luckyOne",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="lottery_id", type="integer", description="Lottery ID"),
     *             @OA\Property(property="drawn_participation_id", type="integer", description="Drawn participation ID"),
     *             @OA\Property(property="drawn_at", type="integer", description="Drawn timestamp"),
     *             @OA\Property(property="available_at", type="integer", description="Available timestamp"),
     *             @OA\Property(property="announced_at", type="integer", description="Announced timestamp"),
     *             @OA\Property(property="accepted_at", type="integer", description="Accepted timestamp"),
     *             @OA\Property(property="rejected_at", type="integer", description="Rejected timestamp")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lucky one updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionLuckyOne")
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
    public function update(Request $request, EditionLuckyOne $luckyOne)
    {
        $validated = $request->validate([
            'lottery_id' => 'sometimes|integer|exists:edition_lotteries,id_lottery',
            'drawn_participation_id' => 'sometimes|integer|exists:edition_participations,id_participation',
            'drawn_at' => 'integer',
            'available_at' => 'integer',
            'announced_at' => 'nullable|integer',
            'accepted_at' => 'nullable|integer',
            'rejected_at' => 'nullable|integer',
        ]);

        // Check if this participation is already a lucky one for this lottery (excluding current record)
        if (isset($validated['lottery_id']) || isset($validated['drawn_participation_id'])) {
            $lotteryId = $validated['lottery_id'] ?? $luckyOne->lottery_id;
            $participationId = $validated['drawn_participation_id'] ?? $luckyOne->drawn_participation_id;

            $existingLuckyOne = EditionLuckyOne::where('id_lucky_one', '!=', $luckyOne->id_lucky_one)
                ->where('lottery_id', $lotteryId)
                ->where('drawn_participation_id', $participationId)
                ->first();

            if ($existingLuckyOne) {
                return $this->errorResponse(
                    'This participation is already a lucky one for this lottery',
                    422
                );
            }
        }

        $luckyOne->update($validated);
        $luckyOne->load(['participation.player']);

        return $this->resourceResponse(
            new EditionLuckyOneResource($luckyOne),
            'Lucky one updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/lucky-ones/{luckyOne}",
     *     summary="Delete a lucky one",
     *     tags={"Manager: Editions Lucky One"},
     *     @OA\Parameter(
     *         name="luckyOne",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lucky one deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     */
    public function destroy(EditionLuckyOne $luckyOne)
    {
        $luckyOne->delete();

        return $this->successResponse(null, 'Lucky one deleted successfully');
    }
}
