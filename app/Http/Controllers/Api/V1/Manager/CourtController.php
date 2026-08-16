<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\CourtResource;
use App\Models\Court;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Courts",
 *     description="(v1) Court management and operations"
 * )
 */
class CourtController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/courts",
     *     summary="Get paginated list of courts",
     *     tags={"🐧 Manager: Courts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Parameter(name="q", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $query = Court::with('location');

        $this->applyAdvancedPagination(
            $query,
            $request,
            Court::$searchableFields,
            Court::$filterableFields,
            Court::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('display_order')->orderBy('id_court', 'desc');
        }

        $courts = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return CourtResource::collection($courts);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/courts/{court}",
     *     summary="Get court details",
     *     tags={"🐧 Manager: Courts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="court", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show(Court $court)
    {
        $court->load('location');

        return $this->resourceResponse(new CourtResource($court), 'Court details retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/courts",
     *     summary="Create a court",
     *     tags={"🐧 Manager: Courts"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"location_id", "name", "is_indoor"},
     *             @OA\Property(property="location_id", type="integer"),
     *             @OA\Property(property="name", type="string", maxLength=30),
     *             @OA\Property(property="is_indoor", type="boolean"),
     *             @OA\Property(property="display_order", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'location_id' => 'required|integer|exists:locations,id_location',
            'name' => 'required|string|max:30',
            'is_indoor' => 'required|boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $validated['display_order'] = $validated['display_order'] ?? 0;

        $court = Court::create($validated);
        $court->load('location');

        return $this->resourceResponse(new CourtResource($court), 'Court created successfully', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/courts/{court}",
     *     summary="Update a court",
     *     tags={"🐧 Manager: Courts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="court", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function update(Request $request, Court $court)
    {
        $validated = $request->validate([
            'location_id' => 'required|integer|exists:locations,id_location',
            'name' => 'required|string|max:30',
            'is_indoor' => 'required|boolean',
            'display_order' => 'required|integer|min:0',
        ]);

        $court->update($validated);
        $court->load('location');

        return $this->resourceResponse(new CourtResource($court), 'Court updated successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/courts/{court}",
     *     summary="Partially update a court",
     *     tags={"🐧 Manager: Courts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="court", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function patch(Request $request, Court $court)
    {
        $validated = $request->validate([
            'location_id' => 'sometimes|integer|exists:locations,id_location',
            'name' => 'sometimes|string|max:30',
            'is_indoor' => 'boolean',
            'display_order' => 'integer|min:0',
        ]);

        $court->update($validated);
        $court->load('location');

        return $this->resourceResponse(new CourtResource($court), 'Court partially updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/courts/{court}",
     *     summary="Delete a court",
     *     tags={"🐧 Manager: Courts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="court", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=409, description="Linked data")
     * )
     */
    public function destroy(Court $court)
    {
        return $this->deleteResourceWithResponse(
            $court,
            ['editionDivisionCourts' => 'edition_division_courts_count'],
            'court',
            'Court deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/courts/bulk",
     *     summary="Bulk delete courts",
     *     tags={"🐧 Manager: Courts"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'court_ids' => 'required|array|min:1',
            'court_ids.*' => 'integer',
        ]);

        return $this->deleteBulkResourcesWithResponse(
            $request->court_ids,
            Court::class,
            ['editionDivisionCourts' => 'edition_division_courts_count'],
            'court',
            'Courts deleted successfully'
        );
    }
}
