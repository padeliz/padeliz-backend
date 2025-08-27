<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\Prize;
use App\Http\Resources\PrizeResource;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Manager: Prizes",
 *     description="(v1) Prizes management and operations"
 * )
 */
class PrizeController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/prizes",
     *     summary="Get paginated list of prizes",
     *     tags={"Manager: Prizes"},
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
        $prizes = Prize::query()
            ->orderByRaw('LOWER(name) ASC, name ASC')  // Case-insensitive + consistent tie-breaking
            ->paginate($this->paginationLimit);

        return PrizeResource::collection($prizes);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/prizes/{prize}",
     *     summary="Get prize details",
     *     tags={"Manager: Prizes"},
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
        $prize->load(['clubPrizes.club']);
        return $this->resourceResponse(
            new PrizeResource($prize),
            'Prize details retrieved successfully'
        );
    }
}
