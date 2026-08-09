<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\DivisionResource;
use App\Models\Division;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Divisions",
 *     description="(v1) Division management and operations"
 * )
 */
class DivisionController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/divisions",
     *     summary="Get paginated list of divisions",
     *     tags={"🐧 Manager: Divisions"},
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
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(
     *             type="object",
     *             additionalProperties=false,
     *             @OA\Property(property="its_first_edition_id", type="string", description="Filter by first edition ID")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(
     *             type="object",
     *             additionalProperties=false,
     *             @OA\Property(property="id_division", type="string", enum={"asc", "desc"}, description="Sort by division ID"),
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}, description="Sort by name"),
     *             @OA\Property(property="color", type="string", enum={"asc", "desc"}, description="Sort by color"),
     *             @OA\Property(property="its_first_edition_id", type="string", enum={"asc", "desc"}, description="Sort by first edition ID"),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}, description="Sort by creation date"),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"}, description="Sort by update date")
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
     *                         @OA\Items(ref="#/components/schemas/Division")
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
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Division::query();

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            Division::$searchableFields,
            Division::$filterableFields,
            Division::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_division', 'desc');
        }

        $divisions = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return DivisionResource::collection($divisions);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/divisions/{division}",
     *     summary="Get division details",
     *     tags={"🐧 Manager: Divisions"},
     *     security={{"sanctum":{}}},
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
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Division not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
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

    /**
     * @OA\Post(
     *     path="/api/v1/manager/divisions",
     *     summary="Create a new division",
     *     tags={"🐧 Manager: Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "color"},
     *             @OA\Property(property="name", type="string", maxLength=25, description="Division name"),
     *             @OA\Property(property="color", type="string", maxLength=7, description="Division color (hex)"),
     *             @OA\Property(property="its_first_edition_id", type="integer", description="First edition ID for this division")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Division created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Division")
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
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:25',
            'color' => 'required|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'its_first_edition_id' => 'nullable|integer|exists:editions,id_edition',
        ]);

        $division = Division::create($validated);

        return $this->resourceResponse(
            new DivisionResource($division),
            'Division created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/divisions/{division}",
     *     summary="Update division details (complete replacement)",
     *     tags={"🐧 Manager: Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="division",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=25, description="Division name"),
     *             @OA\Property(property="color", type="string", maxLength=7, description="Division color (hex)"),
     *             @OA\Property(property="its_first_edition_id", type="integer", description="First edition ID for this division")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Division updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Division")
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
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Division not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function update(Request $request, Division $division)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:25',
            'color' => 'required|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'its_first_edition_id' => 'nullable|integer|exists:editions,id_edition',
        ]);

        $division->update($validated);

        return $this->resourceResponse(
            new DivisionResource($division),
            'Division updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/divisions/{division}",
     *     summary="Partially update division details",
     *     tags={"🐧 Manager: Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="division",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=25, description="Division name"),
     *             @OA\Property(property="color", type="string", maxLength=7, description="Division color (hex)"),
     *             @OA\Property(property="its_first_edition_id", type="integer", description="First edition ID for this division")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Division partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Division")
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
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Division not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function patch(Request $request, Division $division)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:25',
            'color' => 'sometimes|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'its_first_edition_id' => 'nullable|integer|exists:editions,id_edition',
        ]);

        $division->update($validated);

        return $this->resourceResponse(
            new DivisionResource($division),
            'Division partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/divisions/{division}",
     *     summary="Delete a division",
     *     tags={"🐧 Manager: Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="division",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Division deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Division not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Division cannot be deleted due to linked data",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="linked_data",
     *                         type="object",
     *                         description="Information about linked data preventing deletion",
     *                         @OA\Property(property="edition_divisions_count", type="integer", example=2),
     *                         @OA\Property(property="participations_count", type="integer", example=5)
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function destroy(Division $division)
    {
        $linkedDataRelations = [
            'editionDivisions' => 'edition_divisions_count',
            'participations' => 'participations_count',
        ];

        return $this->deleteResourceWithResponse(
            $division,
            $linkedDataRelations,
            'division',
            'Division deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/divisions/bulk",
     *     summary="Bulk delete divisions",
     *     tags={"🐧 Manager: Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"division_ids"},
     *             @OA\Property(
     *                 property="division_ids",
     *                 type="array",
     *                 description="Array of division IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All divisions deleted successfully",
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
     *         description="Some divisions failed to delete (Multi-Status)",
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
     *         description="No divisions provided for deletion",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="All divisions failed to delete due to linked data",
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'division_ids' => 'required|array|min:1',
            'division_ids.*' => 'integer',
        ]);

        $linkedDataRelations = [
            'editionDivisions' => 'edition_divisions_count',
            'participations' => 'participations_count',
        ];

        return $this->deleteBulkResourcesWithResponse(
            $request->division_ids,
            Division::class,
            $linkedDataRelations,
            'division',
            'Divisions deleted successfully'
        );
    }
}
