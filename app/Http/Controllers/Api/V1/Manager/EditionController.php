<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionResource;
use App\Models\Edition;
use App\Services\Pdf\EditionDivisionPosterInvalidator;
use App\Services\Schedule\PlayingWindow;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Editions",
 *     description="(v1) Edition management and operations"
 * )
 */
class EditionController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function __construct(
        private readonly EditionDivisionPosterInvalidator $posterInvalidator,
    ) {
    }

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
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(
     *             type="object",
     *             additionalProperties=false,
     *             @OA\Property(property="location_id", type="string", description="Filter by location ID"),
     *             @OA\Property(property="entry_fee", type="string", description="Filter by entry fee"),
     *             @OA\Property(property="students_entry_fee", type="string", description="Filter by students entry fee")
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
     *             @OA\Property(property="id_edition", type="string", enum={"asc", "desc"}, description="Sort by edition ID"),
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}, description="Sort by name"),
     *             @OA\Property(property="date", type="string", enum={"asc", "desc"}, description="Sort by date"),
     *             @OA\Property(property="location_id", type="string", enum={"asc", "desc"}, description="Sort by location ID"),
     *             @OA\Property(property="entry_fee", type="string", enum={"asc", "desc"}, description="Sort by entry fee"),
     *             @OA\Property(property="students_entry_fee", type="string", enum={"asc", "desc"}, description="Sort by students entry fee"),
     *             @OA\Property(property="arriving_time", type="string", enum={"asc", "desc"}, description="Sort by arriving time"),
     *             @OA\Property(property="closing_time", type="string", enum={"asc", "desc"}, description="Sort by closing time"),
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
        $query = Edition::with(['location', 'divisions', 'organizer']);

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
        $edition->load(['location', 'divisions', 'organizer']);

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
     *             required={"name", "date", "location_id"},
     *             @OA\Property(property="name", type="string", maxLength=25, description="Edition name"),
     *             @OA\Property(property="date", type="string", format="date", description="Edition date"),
     *             @OA\Property(property="location_id", type="integer", description="Location ID"),
     *             @OA\Property(property="organizer_id", type="integer", nullable=true, description="Organizer branding owner"),
     *             @OA\Property(property="include_club_host_on_pdf", type="boolean", description="Whether posters show the hosting club logo", default=true),
     *             @OA\Property(property="entry_fee", type="integer", description="Entry fee in RON", default=55),
     *             @OA\Property(property="students_entry_fee", type="integer", description="Students entry fee in RON", default=45),
     *             @OA\Property(property="arriving_time", type="string", format="time", description="Arriving time"),
     *             @OA\Property(property="closing_time", type="string", format="time", description="Closing time")
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
        $request->validate([
            'name' => 'required|string|max:25',
            'date' => 'required|date',
            'location_id' => 'required|exists:locations,id_location',
            'organizer_id' => 'nullable|integer|exists:organizers,id_organizer',
            'include_club_host_on_pdf' => 'sometimes|boolean',
            'entry_fee' => 'nullable|integer|min:0',
            'students_entry_fee' => 'nullable|integer|min:0',
            'arriving_time' => ['required', PlayingWindow::clockRegexRule()],
            'closing_time' => ['required', PlayingWindow::clockRegexRule()],
        ]);

        $payload = $request->all();
        $payload['arriving_time'] = PlayingWindow::normalizeStoredTime((string) $payload['arriving_time']);
        $payload['closing_time'] = PlayingWindow::normalizeStoredTime((string) $payload['closing_time']);
        $payload['include_club_host_on_pdf'] = $request->has('include_club_host_on_pdf')
            ? $request->boolean('include_club_host_on_pdf')
            : true;

        $edition = Edition::create($payload);
        $edition->load('organizer');

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
     *             @OA\Property(property="name", type="string", maxLength=25, description="Edition name"),
     *             @OA\Property(property="date", type="string", format="date", description="Edition date"),
     *             @OA\Property(property="location_id", type="integer", description="Location ID"),
     *             @OA\Property(property="organizer_id", type="integer", nullable=true, description="Organizer branding owner"),
     *             @OA\Property(property="include_club_host_on_pdf", type="boolean", description="Whether posters show the hosting club logo"),
     *             @OA\Property(property="entry_fee", type="integer", description="Entry fee in RON", minimum=0),
     *             @OA\Property(property="students_entry_fee", type="integer", description="Students entry fee in RON", minimum=0),
     *             @OA\Property(property="arriving_time", type="string", format="time", description="Arriving time"),
     *             @OA\Property(property="closing_time", type="string", format="time", description="Closing time")
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
     *         description="Edition not found",
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
    public function update(Request $request, Edition $edition)
    {
        $request->validate([
            'name' => 'required|string|max:25',
            'date' => 'required|date',
            'location_id' => 'required|exists:locations,id_location',
            'organizer_id' => 'nullable|integer|exists:organizers,id_organizer',
            'include_club_host_on_pdf' => 'sometimes|boolean',
            'entry_fee' => 'nullable|integer|min:0',
            'students_entry_fee' => 'nullable|integer|min:0',
            'arriving_time' => ['required', PlayingWindow::clockRegexRule()],
            'closing_time' => ['required', PlayingWindow::clockRegexRule()],
        ]);

        $payload = $request->all();
        $payload['arriving_time'] = PlayingWindow::normalizeStoredTime((string) $payload['arriving_time']);
        $payload['closing_time'] = PlayingWindow::normalizeStoredTime((string) $payload['closing_time']);
        if ($request->has('include_club_host_on_pdf')) {
            $payload['include_club_host_on_pdf'] = $request->boolean('include_club_host_on_pdf');
        }

        $shouldClearAttendance = $this->attendanceHeaderChanged($edition, $payload);
        $edition->update($payload);
        $edition->load('organizer');
        if ($shouldClearAttendance) {
            $this->posterInvalidator->clearAttendanceForEdition((int) $edition->id_edition);
        }

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
     *             @OA\Property(property="name", type="string", maxLength=25, description="Edition name"),
     *             @OA\Property(property="date", type="string", format="date", description="Edition date"),
     *             @OA\Property(property="location_id", type="integer", description="Location ID"),
     *             @OA\Property(property="organizer_id", type="integer", nullable=true, description="Organizer branding owner"),
     *             @OA\Property(property="include_club_host_on_pdf", type="boolean", description="Whether posters show the hosting club logo"),
     *             @OA\Property(property="entry_fee", type="integer", description="Entry fee in RON", minimum=0),
     *             @OA\Property(property="students_entry_fee", type="integer", description="Students entry fee in RON", minimum=0),
     *             @OA\Property(property="arriving_time", type="string", format="time", description="Arriving time"),
     *             @OA\Property(property="closing_time", type="string", format="time", description="Closing time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Edition partially updated successfully",
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
     *         description="Edition not found",
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
    public function patch(Request $request, Edition $edition)
    {
        $request->validate([
            'name' => 'sometimes|string|max:25',
            'date' => 'sometimes|date',
            'location_id' => 'nullable|exists:locations,id',
            'organizer_id' => 'nullable|integer|exists:organizers,id_organizer',
            'include_club_host_on_pdf' => 'sometimes|boolean',
            'entry_fee' => 'nullable|numeric|min:0',
            'students_entry_fee' => 'nullable|numeric|min:0',
            'arriving_time' => ['nullable', PlayingWindow::clockRegexRule()],
            'closing_time' => ['nullable', PlayingWindow::clockRegexRule()],
        ]);

        $payload = $request->all();
        if (array_key_exists('arriving_time', $payload) && $payload['arriving_time'] !== null && $payload['arriving_time'] !== '') {
            $payload['arriving_time'] = PlayingWindow::normalizeStoredTime((string) $payload['arriving_time']);
        }
        if (array_key_exists('closing_time', $payload) && $payload['closing_time'] !== null && $payload['closing_time'] !== '') {
            $payload['closing_time'] = PlayingWindow::normalizeStoredTime((string) $payload['closing_time']);
        }
        if ($request->has('include_club_host_on_pdf')) {
            $payload['include_club_host_on_pdf'] = $request->boolean('include_club_host_on_pdf');
        }

        $shouldClearAttendance = $this->attendanceHeaderChanged($edition, $payload);
        $edition->update($payload);
        $edition->load('organizer');
        if ($shouldClearAttendance) {
            $this->posterInvalidator->clearAttendanceForEdition((int) $edition->id_edition);
        }

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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function attendanceHeaderChanged(Edition $edition, array $payload): bool
    {
        foreach (['name', 'date', 'arriving_time', 'closing_time', 'organizer_id'] as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $incoming = $payload[$field];
            $current = $edition->getAttribute($field);

            if ($field === 'arriving_time' || $field === 'closing_time') {
                $incoming = PlayingWindow::toClockString($incoming);
                $current = PlayingWindow::toClockString($current);
            }

            if ((string) $incoming !== (string) $current) {
                return true;
            }
        }

        return false;
    }
}
