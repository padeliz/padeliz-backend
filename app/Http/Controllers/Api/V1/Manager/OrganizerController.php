<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\OrganizerResource;
use App\Models\Organizer;
use App\Traits\CachesManagerResourceReads;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Organizers",
 *     description="(v1) Organizer management and poster branding owners"
 * )
 */
class OrganizerController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    use CachesManagerResourceReads;
    /**
     * @OA\Get(
     *     path="/api/v1/manager/organizers",
     *     summary="Get paginated list of organizers",
     *     tags={"🐧 Manager: Organizers"},
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
     *             @OA\Property(property="show_edition_label", type="string", description="Filter by show edition label")
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
     *             @OA\Property(property="id_organizer", type="string", enum={"asc", "desc"}, description="Sort by organizer ID"),
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}, description="Sort by name"),
     *             @OA\Property(property="show_edition_label", type="string", enum={"asc", "desc"}, description="Sort by show edition label"),
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
     *                         @OA\Items(ref="#/components/schemas/Organizer")
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
        $payload = $this->cachedManagerIndex('organizers', $request, [], function () use ($request) {
            $query = Organizer::query();

            $this->applyAdvancedPagination(
                $query,
                $request,
                Organizer::$searchableFields,
                Organizer::$filterableFields,
                Organizer::$sortableFields
            );

            $paginationParams = $this->getPaginationParams($request);

            if (! $request->has('sort_by')) {
                $query->orderBy('id_organizer', 'desc');
            }

            $organizers = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

            return OrganizerResource::collection($organizers)->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/organizers/{organizer}",
     *     summary="Get organizer details",
     *     tags={"🐧 Manager: Organizers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="organizer",
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
     *                     @OA\Property(property="data", ref="#/components/schemas/Organizer")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organizer not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(Organizer $organizer)
    {
        $data = $this->cachedManagerShow('organizers', (int) $organizer->id_organizer, function () use ($organizer) {
            return (new OrganizerResource($organizer))->resolve();
        });

        return $this->successResponse($data, 'Organizer details retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/organizers",
     *     summary="Create a new organizer",
     *     tags={"🐧 Manager: Organizers"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=60, description="Organizer name"),
     *             @OA\Property(property="show_edition_label", type="boolean", description="Whether posters show the edition label next to the organizer logo", default=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Organizer created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Organizer")
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
            'name' => 'required|string|max:60|unique:organizers,name',
            'show_edition_label' => 'sometimes|boolean',
        ]);

        $validated['show_edition_label'] = (bool) ($validated['show_edition_label'] ?? false);

        $organizer = Organizer::create($validated);

        return $this->resourceResponse(
            new OrganizerResource($organizer),
            'Organizer created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/organizers/{organizer}",
     *     summary="Update an organizer (complete replacement)",
     *     tags={"🐧 Manager: Organizers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="organizer",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=60),
     *             @OA\Property(property="show_edition_label", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organizer updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Organizer")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organizer not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function update(Request $request, Organizer $organizer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:60|unique:organizers,name,' . $organizer->id_organizer . ',id_organizer',
            'show_edition_label' => 'sometimes|boolean',
        ]);

        $organizer->update($validated);

        return $this->resourceResponse(
            new OrganizerResource($organizer),
            'Organizer updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/organizers/{organizer}",
     *     summary="Partially update an organizer",
     *     tags={"🐧 Manager: Organizers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="organizer",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=60),
     *             @OA\Property(property="show_edition_label", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organizer partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Organizer")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organizer not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function patch(Request $request, Organizer $organizer)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:60|unique:organizers,name,' . $organizer->id_organizer . ',id_organizer',
            'show_edition_label' => 'sometimes|boolean',
        ]);

        $organizer->update($validated);

        return $this->resourceResponse(
            new OrganizerResource($organizer),
            'Organizer partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/organizers/{organizer}",
     *     summary="Delete an organizer",
     *     tags={"🐧 Manager: Organizers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="organizer",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organizer deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organizer not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Organizer cannot be deleted because editions still reference it",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="linked_data",
     *                         type="object",
     *                         @OA\Property(property="editions_count", type="integer", example=2)
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function destroy(Organizer $organizer)
    {
        $editionsCount = $organizer->editions()->count();

        if ($editionsCount > 0) {
            return $this->errorResponse(
                'Organizer cannot be deleted because it has linked data. Please remove all associated editions first.',
                409,
                ['linked_data' => ['editions_count' => $editionsCount]]
            );
        }

        return $this->deleteResourceWithResponse(
            $organizer,
            ['editions' => 'editions_count'],
            'organizer',
            'Organizer deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/organizers/bulk",
     *     summary="Bulk delete organizers",
     *     tags={"🐧 Manager: Organizers"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"organizer_ids"},
     *             @OA\Property(
     *                 property="organizer_ids",
     *                 type="array",
     *                 description="Array of organizer IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All organizers deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=207,
     *         description="Some organizers failed to delete (Multi-Status)",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="All organizers failed to delete due to linked data",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'organizer_ids' => 'required|array|min:1',
            'organizer_ids.*' => 'integer',
        ]);

        return $this->deleteBulkResourcesWithResponse(
            $request->organizer_ids,
            Organizer::class,
            ['editions' => 'editions_count'],
            'organizer',
            'Organizers deleted successfully'
        );
    }
}
