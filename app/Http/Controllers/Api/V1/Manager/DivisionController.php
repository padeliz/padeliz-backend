<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Http\Resources\DivisionResource;
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
        $divisions = Division::query()
            ->orderByRaw('name ASC')
            ->paginate($this->paginationLimit);

        return DivisionResource::collection($divisions);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/divisions/{division}",
     *     summary="Get division details",
     *     tags={"🐧 Manager: Divisions"},
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

    /**
     * @OA\Post(
     *     path="/api/v1/manager/divisions",
     *     summary="Create a new division",
     *     tags={"🐧 Manager: Divisions"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "color"},
     *             @OA\Property(property="name", type="string", maxLength=25, description="Division name"),
     *             @OA\Property(property="color", type="string", maxLength=7, description="Division color (hex)"),
     *             @OA\Property(property="is_students_only", type="boolean", description="Whether division is for students only"),
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:25',
            'color' => 'required|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'is_students_only' => 'boolean',
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
     *     summary="Update division details",
     *     tags={"🐧 Manager: Divisions"},
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
     *             @OA\Property(property="is_students_only", type="boolean", description="Whether division is for students only"),
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function update(Request $request, Division $division)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:25',
            'color' => 'sometimes|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'is_students_only' => 'boolean',
            'its_first_edition_id' => 'nullable|integer|exists:editions,id_edition',
        ]);

        $division->update($validated);

        return $this->resourceResponse(
            new DivisionResource($division),
            'Division updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/divisions/{division}",
     *     summary="Delete a division",
     *     tags={"🐧 Manager: Divisions"},
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
     *         response=409,
     *         description="Division cannot be deleted due to related records",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function destroy(Division $division)
    {
        // Check if division has related edition divisions or participations
        if ($division->editionDivisions()->exists() || $division->participations()->exists()) {
            return $this->errorResponse(
                'Cannot delete division because it has related edition divisions or participations',
                409
            );
        }

        $division->delete();

        return $this->successResponse(null, 'Division deleted successfully');
    }
}
