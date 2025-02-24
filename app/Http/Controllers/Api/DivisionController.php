<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Divisions",
 *     description="API Endpoints for divisions"
 * )
 */
class DivisionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/divisions",
     *     summary="Get paginated list of divisions",
     *     tags={"Divisions"},
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
            Division::orderBy('name')
            ->paginate($this->paginationLimit)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/divisions/{division}",
     *     summary="Get division details",
     *     tags={"Divisions"},
     *     @OA\Parameter(
     *         name="division",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Division")
     *     )
     * )
     */
    public function show(Division $division)
    {
        return response()->json($division);
    }
}
