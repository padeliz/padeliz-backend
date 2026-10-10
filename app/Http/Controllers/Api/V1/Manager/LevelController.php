<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\LevelResource;
use App\Models\Level;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only: levels are seeded bands and are never created or edited over HTTP.
 *
 * @OA\Tag(
 *     name="🐧 Manager: Levels",
 *     description="(v1) Seeded player level bands (read-only)"
 * )
 */
class LevelController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/levels",
     *     summary="Get paginated list of level bands",
     *     tags={"🐧 Manager: Levels"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(name="q", in="query", description="Search term for name", required=false, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="List of level bands",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Level")))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Access denied. Required user type: manager", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function index(Request $request)
    {
        $query = Level::query();

        $this->applyAdvancedPagination(
            $query,
            $request,
            Level::$searchableFields,
            Level::$filterableFields,
            Level::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            // Strongest band first within the weakest-to-strongest numbering used by the bands.
            $query->orderBy('level_max', 'asc')->orderBy('id_level', 'asc');
        }

        $levels = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return LevelResource::collection($levels);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/levels/{level}",
     *     summary="Get level band details",
     *     tags={"🐧 Manager: Levels"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="level", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/Level"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=404, description="Level not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function show(Level $level): JsonResponse
    {
        return $this->resourceResponse(new LevelResource($level), 'Level details retrieved successfully');
    }
}
