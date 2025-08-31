<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\PrizeResource;
use App\Models\Prize;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Prizes",
 *     description="(v1) Prize management and operations"
 * )
 */
class PrizeController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/prizes",
     *     summary="Get paginated list of prizes",
     *     tags={"🐧 Manager: Prizes"},
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
        $prizes = Prize::with('clubPrizes')
            ->orderBy('name')
            ->paginate($this->paginationLimit);

        return PrizeResource::collection($prizes);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/prizes/{prize}",
     *     summary="Get prize details",
     *     tags={"🐧 Manager: Prizes"},
     *     @OA\Parameter(
     *         name="prize",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Prize")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function show(Prize $prize)
    {
        $prize->load('clubPrizes');

        return $this->resourceResponse(
            new PrizeResource($prize),
            'Prize details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/prizes",
     *     summary="Create a new prize",
     *     tags={"🐧 Manager: Prizes"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=60, description="Prize name"),
     *             @OA\Property(property="image", type="string", maxLength=100, description="Prize image URL"),
     *             @OA\Property(property="box_1_text", type="string", maxLength=200, description="Box 1 text"),
     *             @OA\Property(property="box_1_bg_color", type="string", maxLength=7, description="Box 1 background color"),
     *             @OA\Property(property="box_1_text_color", type="string", maxLength=7, description="Box 1 text color"),
     *             @OA\Property(property="box_2_text", type="string", maxLength=200, description="Box 2 text"),
     *             @OA\Property(property="box_2_bg_color", type="string", maxLength=7, description="Box 2 background color"),
     *             @OA\Property(property="box_2_text_color", type="string", maxLength=7, description="Box 2 text color")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Prize created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Prize")
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
            'name' => 'required|string|max:60|unique:prizes,name',
            'image' => 'nullable|string|max:100',
            'box_1_text' => 'nullable|string|max:200',
            'box_1_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_1_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text' => 'nullable|string|max:200',
            'box_2_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $prize = Prize::create($validated);

        return $this->resourceResponse(
            new PrizeResource($prize),
            'Prize created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/prizes/{prize}",
     *     summary="Update prize details",
     *     tags={"🐧 Manager: Prizes"},
     *     @OA\Parameter(
     *         name="prize",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=60, description="Prize name"),
     *             @OA\Property(property="image", type="string", maxLength=100, description="Prize image URL"),
     *             @OA\Property(property="box_1_text", type="string", maxLength=200, description="Box 1 text"),
     *             @OA\Property(property="box_1_bg_color", type="string", maxLength=7, description="Box 1 background color"),
     *             @OA\Property(property="box_1_text_color", type="string", maxLength=7, description="Box 1 text color"),
     *             @OA\Property(property="box_2_text", type="string", maxLength=200, description="Box 2 text"),
     *             @OA\Property(property="box_2_bg_color", type="string", maxLength=7, description="Box 2 background color"),
     *             @OA\Property(property="box_2_text_color", type="string", maxLength=7, description="Box 2 text color")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Prize updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Prize")
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
    public function update(Request $request, Prize $prize)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:60|unique:prizes,name,' . $prize->id_prize . ',id_prize',
            'image' => 'nullable|string|max:100',
            'box_1_text' => 'nullable|string|max:200',
            'box_1_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_1_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text' => 'nullable|string|max:200',
            'box_2_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'box_2_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $prize->update($validated);

        return $this->resourceResponse(
            new PrizeResource($prize),
            'Prize updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/prizes/{prize}",
     *     summary="Delete a prize",
     *     tags={"🐧 Manager: Prizes"},
     *     @OA\Parameter(
     *         name="prize",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Prize deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Prize cannot be deleted due to related records",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function destroy(Prize $prize)
    {
        // Check if prize has related club prizes or lotteries
        if ($prize->clubPrizes()->exists() || $prize->lotteries()->exists()) {
            return $this->errorResponse(
                'Cannot delete prize because it has related club prizes or lotteries',
                409
            );
        }

        $prize->delete();

        return $this->successResponse(null, 'Prize deleted successfully');
    }
}
