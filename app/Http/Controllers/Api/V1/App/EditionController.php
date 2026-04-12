<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Resources\EditionResource;
use App\Models\Edition;
use Illuminate\Support\Carbon;

/**
 * @OA\Tag(
 *     name="🐥 App: Editions",
 *     description="(v1) Player editions"
 * )
 */
class EditionController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/app/editions/upcoming",
     *     summary="List upcoming editions",
     *     tags={"🐥 App: Editions"},
     *     security={},
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
        $editions = Edition::query()
            ->with(['location', 'divisions.division'])
            ->whereDate('date', '>', Carbon::today())
            ->orderBy('date', 'asc')
            ->paginate($this->paginationLimit);

        return EditionResource::collection($editions);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/editions/ongoing",
     *     summary="List ongoing editions (date is today)",
     *     tags={"🐥 App: Editions"},
     *     security={},
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
        $editions = Edition::query()
            ->with(['location', 'divisions.division'])
            ->whereDate('date', Carbon::today())
            ->orderBy('arriving_time', 'asc')
            ->paginate($this->paginationLimit);

        return EditionResource::collection($editions);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/editions/past",
     *     summary="List past editions",
     *     tags={"🐥 App: Editions"},
     *     security={},
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
        $editions = Edition::query()
            ->with(['location', 'divisions.division'])
            ->whereDate('date', '<', Carbon::today())
            ->orderBy('date', 'desc')
            ->paginate($this->paginationLimit);

        return EditionResource::collection($editions);
    }
}
