<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\LevelResource;
use App\Models\Level;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="🐥 App: Levels",
 *     description="(v1) Seeded player level bands (read-only)"
 * )
 */
class LevelController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/app/levels",
     *     summary="List all level bands",
     *     tags={"🐥 App: Levels"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All level bands, strongest first",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Level")))
     *             }
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $levels = Level::query()
            ->orderBy('level_max', 'asc')
            ->orderBy('id_level', 'asc')
            ->get();

        return $this->resourceResponse(LevelResource::collection($levels), 'Levels retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/levels/{level}",
     *     summary="Get a level band",
     *     tags={"🐥 App: Levels"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="level", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Level not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function show(Level $level): JsonResponse
    {
        return $this->resourceResponse(new LevelResource($level), 'Level retrieved successfully');
    }
}
