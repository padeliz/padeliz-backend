<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionResource;
use App\Models\Edition;
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
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term for name field",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         description="Filter parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="location_id", type="integer"),
     *             @OA\Property(property="date", type="string", format="date"),
     *             @OA\Property(property="entry_fee", type="number"),
     *             @OA\Property(property="students_entry_fee", type="number")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="date", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="location_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="entry_fee", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="students_entry_fee", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="arriving_time", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="closing_time", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"})
     *         )
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
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Edition::with(['location', 'divisions']);

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            Edition::$searchableFields,
            Edition::$filterableFields,
            Edition::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_edition', 'desc');
        }

        $editions = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return EditionResource::collection($editions);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/upcoming",
     *     summary="Get paginated list of upcoming editions",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term for name field",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         description="Filter parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="location_id", type="integer"),
     *             @OA\Property(property="date", type="string", format="date"),
     *             @OA\Property(property="entry_fee", type="number"),
     *             @OA\Property(property="students_entry_fee", type="number")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="date", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="location_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="entry_fee", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="students_entry_fee", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="arriving_time", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="closing_time", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"})
     *         )
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
     *     )
     * )
     */
    public function upcoming(Request $request)
    {
        $query = Edition::where('date', '>', Carbon::now());

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            Edition::$searchableFields,
            Edition::$filterableFields,
            Edition::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_edition', 'desc');
        }

        $editions = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return EditionResource::collection($editions);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/ongoing",
     *     summary="Get paginated list of ongoing editions",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term for name field",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         description="Filter parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="location_id", type="integer"),
     *             @OA\Property(property="date", type="string", format="date"),
     *             @OA\Property(property="entry_fee", type="number"),
     *             @OA\Property(property="students_entry_fee", type="number")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="date", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="location_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="entry_fee", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="students_entry_fee", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="arriving_time", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="closing_time", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"})
     *         )
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
     *     )
     * )
     */
    public function ongoing(Request $request)
    {
        $query = Edition::where('date', '=', Carbon::today());

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            Edition::$searchableFields,
            Edition::$filterableFields,
            Edition::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_edition', 'desc');
        }

        $editions = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return EditionResource::collection($editions);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/past",
     *     summary="Get paginated list of past editions",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term for name field",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         description="Filter parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="location_id", type="integer"),
     *             @OA\Property(property="date", type="string", format="date"),
     *             @OA\Property(property="entry_fee", type="number"),
     *             @OA\Property(property="students_entry_fee", type="number")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="date", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="location_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="entry_fee", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="students_entry_fee", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="arriving_time", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="closing_time", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"})
     *         )
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
     *     )
     * )
     */
    public function past(Request $request)
    {
        $query = Edition::where('date', '<', Carbon::today());

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            Edition::$searchableFields,
            Edition::$filterableFields,
            Edition::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_edition', 'desc');
        }

        $editions = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return EditionResource::collection($editions);
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
            'max_participants' => 'nullable|integer|min:1',
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
     *     summary="Update an edition (complete replacement)",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="location_id", type="integer"),
     *             @OA\Property(property="max_participants", type="integer", minimum=1)
     *         )
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
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'location_id' => 'nullable|exists:locations,id',
            'entry_fee' => 'nullable|numeric|min:0',
            'students_entry_fee' => 'nullable|numeric|min:0',
            'arriving_time' => 'nullable|date_format:H:i:s',
            'closing_time' => 'nullable|date_format:H:i:s',
        ]);

        $edition->update($request->all());

        return $this->resourceResponse(
            new EditionResource($edition),
            'Edition updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/editions/{edition}",
     *     summary="Partially update an edition",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="location_id", type="integer"),
     *             @OA\Property(property="max_participants", type="integer", minimum=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Edition partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/ApiResponse")
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
    public function patch(Request $request, Edition $edition)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'date' => 'sometimes|date',
            'location_id' => 'nullable|exists:locations,id',
            'entry_fee' => 'nullable|numeric|min:0',
            'students_entry_fee' => 'nullable|numeric|min:0',
            'arriving_time' => 'nullable|date_format:H:i:s',
            'closing_time' => 'nullable|date_format:H:i:s',
        ]);

        $edition->update($request->all());

        return $this->resourceResponse(
            new EditionResource($edition),
            'Edition partially updated successfully'
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
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Edition cannot be deleted due to linked data",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="linked_data",
     *                         type="object",
     *                         description="Information about linked data preventing deletion",
     *                         @OA\Property(property="divisions_count", type="integer", example=3),
     *                         @OA\Property(property="participations_count", type="integer", example=15),
     *                         @OA\Property(property="lotteries_count", type="integer", example=2)
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function destroy(Edition $edition)
    {
        $linkedDataRelations = [
            'divisions' => 'divisions_count',
            'participations' => 'participations_count',
            'lotteries' => 'lotteries_count',
        ];

        return $this->deleteResourceWithResponse(
            $edition,
            $linkedDataRelations,
            'edition',
            'Edition deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/editions/bulk",
     *     summary="Bulk delete editions",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"edition_ids"},
     *             @OA\Property(
     *                 property="edition_ids",
     *                 type="array",
     *                 description="Array of edition IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All editions deleted successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="successful", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="failed", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="total_requested", type="integer"),
     *                         @OA\Property(property="total_successful", type="integer"),
     *                         @OA\Property(property="total_failed", type="integer")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=207,
     *         description="Some editions failed to delete (Multi-Status)",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         @OA\Property(property="successful", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="failed", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="total_requested", type="integer"),
     *                         @OA\Property(property="total_successful", type="integer"),
     *                         @OA\Property(property="total_failed", type="integer")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No editions provided for deletion",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="All editions failed to delete due to linked data",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         @OA\Property(property="successful", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="failed", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="total_requested", type="integer"),
     *                         @OA\Property(property="total_successful", type="integer"),
     *                         @OA\Property(property="total_failed", type="integer")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'edition_ids' => 'required|array|min:1',
            'edition_ids.*' => 'integer',
        ]);

        $linkedDataRelations = [
            'divisions' => 'divisions_count',
            'participations' => 'participations_count',
            'lotteries' => 'lotteries_count',
        ];

        return $this->deleteBulkResourcesWithResponse(
            $request->edition_ids,
            Edition::class,
            $linkedDataRelations,
            'edition',
            'Editions deleted successfully'
        );
    }
}
