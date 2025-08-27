<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use App\Http\Resources\EditionDivisionResource;
use App\Http\Resources\EditionParticipationResource;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Manager: Editions Divisions",
 *     description="(v1) API Endpoints for edition divisions"
 * )
 */
class EditionDivisionController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-divisions",
     *     summary="Get paginated list of edition divisions",
     *     tags={"Manager: Editions Divisions"},
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
     *                         @OA\Items(ref="#/components/schemas/EditionDivision")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index()
    {
        $editionDivisions = EditionDivision::with(['edition', 'division'])
            ->orderBy('id_edition_division', 'desc')
            ->paginate($this->paginationLimit);

        return $this->resourceResponse(
            EditionDivisionResource::collection($editionDivisions),
            'Edition divisions retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}",
     *     summary="Get edition division details",
     *     tags={"Manager: Editions Divisions"},
     *     @OA\Parameter(
     *         name="editionDivision",
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
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionDivision")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function show(EditionDivision $editionDivision)
    {
        $editionDivision->load(['edition', 'division']);

        return $this->resourceResponse(
            new EditionDivisionResource($editionDivision),
            'Edition division details retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/participants",
     *     summary="Get participants in this division",
     *     tags={"Manager: Editions Divisions"},
     *     @OA\Parameter(
     *         name="editionDivision",
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
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/EditionParticipation")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function participants(EditionDivision $editionDivision)
    {
        $participants = EditionParticipation::where([
            'edition_id' => $editionDivision->edition_id,
            'division_id' => $editionDivision->division_id
        ])->with(['player'])->get();

        return $this->resourceResponse(
            EditionParticipationResource::collection($participants),
            'Participants retrieved successfully'
        );
    }
}
