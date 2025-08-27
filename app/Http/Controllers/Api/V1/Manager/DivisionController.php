<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Http\Resources\DivisionResource;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Manager: Divisions",
 *     description="(v1) Division management and operations"
 * )
 */
class DivisionController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/divisions",
     *     summary="Get paginated list of divisions",
     *     tags={"Manager: Divisions"},
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
     *                         @OA\Items(ref="#/components/schemas/Division")
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
            Division::query()
                ->orderByRaw('name ASC')
                ->paginate($this->paginationLimit)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/divisions/{division}",
     *     summary="Get division details",
     *     tags={"Manager: Divisions"},
     *     @OA\Parameter(
     *         name="division",
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
     *                     @OA\Property(property="data", ref="#/components/schemas/Division")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function show(Division $division)
    {
        return $this->resourceResponse(
            new DivisionResource($division),
            'Division details retrieved successfully'
        );
    }
}
