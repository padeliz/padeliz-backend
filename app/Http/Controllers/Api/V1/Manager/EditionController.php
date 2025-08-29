<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Http\Resources\EditionResource;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Editions",
 *     description="(v1) Edition management and operations"
 * )
 */
class EditionController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions",
     *     summary="Get paginated list of editions",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
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
    public function index()
    {
        $editions = Edition::with(['location', 'divisions'])
            ->orderBy('date', 'desc')
            ->paginate($this->paginationLimit);

        return EditionResource::collection($editions);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/upcoming",
     *     summary="Get paginated list of upcoming editions",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
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
        return response()->json(
            Edition::where('date', '>', Carbon::now())
                ->orderBy('date', 'asc')
            ->paginate($this->paginationLimit)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/ongoing",
     *     summary="Get paginated list of ongoing editions",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
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
        return response()->json(
            Edition::where('date', '=', Carbon::today())
                ->orderBy('date', 'asc')
            ->paginate($this->paginationLimit)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/past",
     *     summary="Get paginated list of past editions",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
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
        return response()->json(
            Edition::where('date', '<', Carbon::today())
                ->orderBy('date', 'desc')
            ->paginate($this->paginationLimit)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/{edition}",
     *     summary="Get edition details",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="edition",
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
     *                     @OA\Property(property="data", ref="#/components/schemas/Edition")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function show(Edition $edition)
    {
        $edition->load(['location', 'divisions']);
        return $this->resourceResponse(
            new EditionResource($edition),
            'Edition details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/editions",
     *     summary="Create a new edition",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "description", "start_date", "end_date"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="location_id", type="integer"),
     *             @OA\Property(property="max_participants", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Edition created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Edition")
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
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'location_id' => 'nullable|exists:locations,id',
            'max_participants' => 'nullable|integer|min:1'
        ]);

        $edition = Edition::create($request->all());

        return $this->resourceResponse(
            new EditionResource($edition),
            'Edition created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/editions/{edition}",
     *     summary="Update an edition",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Edition updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Edition")
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
    public function update(Request $request, Edition $edition)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'location_id' => 'nullable|exists:locations,id',
            'max_participants' => 'nullable|integer|min:1'
        ]);

        $edition->update($request->all());

        return $this->resourceResponse(
            new EditionResource($edition),
            'Edition updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/editions/{edition}",
     *     summary="Delete an edition",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Edition deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     */
    public function destroy(Edition $edition)
    {
        $edition->delete();
        return $this->successResponse(
            null,
            'Edition deleted successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/{edition}/divisions",
     *     summary="Get edition divisions",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="edition",
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
     *                     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EditionDivision"))
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function divisions(Edition $edition)
    {
        return $this->successResponse(
            $edition->divisions()->with('division')->get(),
            'Edition divisions retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/{edition}/participants",
     *     summary="Get edition participants",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/EditionParticipation"))
     *     )
     * )
     */
    public function participants(Edition $edition)
    {
        return response()->json($edition->participations()->with(['player', 'division'])->get());
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/{edition}/lotteries",
     *     summary="Get edition lotteries",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/EditionLottery"))
     *     )
     * )
     */
    public function lotteries(Edition $edition)
    {
        return response()->json($edition->lotteries()->with(['prize', 'division', 'luckyOnes'])->get());
    }
}
