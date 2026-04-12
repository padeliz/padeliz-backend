<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Requests\App\EditionDateRangeRequest;
use App\Http\Resources\EditionResource;
use App\Models\Edition;

/**
 * @OA\Tag(
 *     name="🐥 App: My Editions",
 *     description="(v1) Player my editions"
 * )
 */
class MyEditionController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/app/my/editions",
     *     summary="List my editions in a date range",
     *     tags={"🐥 App: My Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         required=true,
     *         description="Start of inclusive date range (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2026-04-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         required=true,
     *         description="End of inclusive date range (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2026-04-30")
     *     ),
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
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: player",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error (missing or invalid date range)",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function index(EditionDateRangeRequest $request)
    {
        $validated = $request->validated();

        $editions = Edition::query()
            ->with(['location', 'divisions.division'])
            ->whereHas('participations', function ($query) use ($request) {
                $query->where('player_id', $request->user()->id_player);
            })
            ->whereDate('date', '>=', $validated['date_from'])
            ->whereDate('date', '<=', $validated['date_to'])
            ->orderBy('date', 'asc')
            ->paginate($this->paginationLimit);

        return EditionResource::collection($editions);
    }
}
