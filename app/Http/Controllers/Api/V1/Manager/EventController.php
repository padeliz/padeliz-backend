<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EventResource;
use App\Models\PadelEvent;
use App\Rules\EventNameSlugable;
use App\Services\EventDeadlineValidator;
use App\Services\EventDescriptionSanitizer;
use App\Services\EventSlug\EventSlugSync;
use App\Services\Pdf\EventDivisionPosterInvalidator;
use App\Services\Schedule\PlayingWindow;
use App\Traits\CachesManagerResourceReads;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Events",
 *     description="(v1) PadelEvent management and operations"
 * )
 */
class EventController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    use CachesManagerResourceReads;

    public function __construct(
        private readonly EventDivisionPosterInvalidator $posterInvalidator,
        private readonly EventDescriptionSanitizer $descriptionSanitizer,
        private readonly EventSlugSync $eventSlugSync,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/events",
     *     summary="Get paginated list of editions",
     *     tags={"🐧 Manager: Events"},
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
     *             @OA\Property(property="location_id", type="string", description="Filter by location ID")
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
     *             @OA\Property(property="id_event", type="string", enum={"asc", "desc"}, description="Sort by edition ID"),
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}, description="Sort by name"),
     *             @OA\Property(property="date", type="string", enum={"asc", "desc"}, description="Sort by date"),
     *             @OA\Property(property="location_id", type="string", enum={"asc", "desc"}, description="Sort by location ID"),
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
     *                         @OA\Items(ref="#/components/schemas/Event")
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
        $payload = $this->cachedManagerIndex('events', $request, [], function () use ($request) {
            $query = PadelEvent::with(['location', 'divisions', 'organizer']);

            $this->applyAdvancedPagination(
                $query,
                $request,
                PadelEvent::$searchableFields,
                PadelEvent::$filterableFields,
                PadelEvent::$sortableFields
            );

            $paginationParams = $this->getPaginationParams($request);

            if (! $request->has('sort_by')) {
                $query->orderBy('id_event', 'desc');
            }

            $editions = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

            return EventResource::collection($editions)->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/events/{event}",
     *     summary="Get edition details",
     *     tags={"🐧 Manager: Events"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="event",
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
     *                     @OA\Property(property="data", ref="#/components/schemas/Event")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function show(PadelEvent $event)
    {
        $data = $this->cachedManagerShow('events', (int) $event->id_event, function () use ($event) {
            $event->load(['location', 'divisions', 'organizer']);

            return (new EventResource($event))->resolve();
        });

        return $this->successResponse($data, 'PadelEvent details retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/events",
     *     summary="Create a new edition",
     *     tags={"🐧 Manager: Events"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "date", "location_id", "description", "registration_allowed_until", "cancellation_allowed_until"},
     *             @OA\Property(property="name", type="string", maxLength=25, description="PadelEvent name"),
     *             @OA\Property(property="description", type="string", description="Rich HTML description"),
     *             @OA\Property(property="date", type="string", format="date", description="PadelEvent date"),
     *             @OA\Property(property="location_id", type="integer", description="Location ID"),
     *             @OA\Property(property="organizer_id", type="integer", nullable=true, description="Organizer branding owner"),
     *             @OA\Property(property="include_club_host_on_pdf", type="boolean", description="Whether posters show the hosting club logo", default=true),
     *             @OA\Property(property="arriving_time", type="string", format="time", description="Arriving time"),
     *             @OA\Property(property="closing_time", type="string", format="time", description="Closing time"),
     *             @OA\Property(property="registration_allowed_until", type="integer", format="timestamp", description="Unix timestamp until which players may register"),
     *             @OA\Property(property="cancellation_allowed_until", type="integer", format="timestamp", description="Unix timestamp until which players may self-cancel")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="PadelEvent created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Event")
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
            'name' => ['required', 'string', 'max:25', new EventNameSlugable()],
            'description' => 'required|string',
            'date' => 'required|date',
            'location_id' => 'required|exists:locations,id_location',
            'organizer_id' => 'nullable|integer|exists:organizers,id_organizer',
            'include_club_host_on_pdf' => 'sometimes|boolean',
            'registration_allowed_until' => 'required|integer|min:0',
            'cancellation_allowed_until' => 'required|integer|min:0',
            'arriving_time' => ['required', PlayingWindow::clockRegexRule()],
            'closing_time' => ['required', PlayingWindow::clockRegexRule()],
        ]);

        $payload = $request->all();
        $payload['description'] = $this->sanitizeDescriptionOrFail((string) $payload['description']);
        $payload['arriving_time'] = PlayingWindow::normalizeStoredTime((string) $payload['arriving_time']);
        $payload['closing_time'] = PlayingWindow::normalizeStoredTime((string) $payload['closing_time']);
        $this->assertDistinctArrivingClosingWindow(
            (string) $payload['arriving_time'],
            (string) $payload['closing_time']
        );
        $payload['include_club_host_on_pdf'] = $request->has('include_club_host_on_pdf')
            ? $request->boolean('include_club_host_on_pdf')
            : true;
        unset($payload['cover_path'], $payload['cover_url'], $payload['slug']);

        EventDeadlineValidator::assertOrdering([
            'date' => $payload['date'],
            'registration_allowed_until' => $payload['registration_allowed_until'],
            'cancellation_allowed_until' => $payload['cancellation_allowed_until'],
        ]);

        $event = PadelEvent::create($payload);
        $this->eventSlugSync->syncForEvent($event->fresh(['organizer']) ?? $event);
        $event->refresh()->load('organizer');

        return $this->resourceResponse(
            new EventResource($event),
            'PadelEvent created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/events/{event}",
     *     summary="Update an edition (complete replacement)",
     *     tags={"🐧 Manager: Events"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "date", "location_id", "description", "registration_allowed_until", "cancellation_allowed_until"},
     *             @OA\Property(property="name", type="string", maxLength=25, description="PadelEvent name"),
     *             @OA\Property(property="description", type="string", description="Rich HTML description"),
     *             @OA\Property(property="date", type="string", format="date", description="PadelEvent date"),
     *             @OA\Property(property="location_id", type="integer", description="Location ID"),
     *             @OA\Property(property="organizer_id", type="integer", nullable=true, description="Organizer branding owner"),
     *             @OA\Property(property="include_club_host_on_pdf", type="boolean", description="Whether posters show the hosting club logo"),
     *             @OA\Property(property="arriving_time", type="string", format="time", description="Arriving time"),
     *             @OA\Property(property="closing_time", type="string", format="time", description="Closing time"),
     *             @OA\Property(property="registration_allowed_until", type="integer", format="timestamp", description="Unix timestamp until which players may register"),
     *             @OA\Property(property="cancellation_allowed_until", type="integer", format="timestamp", description="Unix timestamp until which players may self-cancel")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PadelEvent updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Event")
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
     *         description="PadelEvent not found",
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
    public function update(Request $request, PadelEvent $event)
    {
        $this->ensureCoverPresent($event);

        $request->validate([
            'name' => ['required', 'string', 'max:25', new EventNameSlugable()],
            'description' => 'required|string',
            'date' => 'required|date',
            'location_id' => 'required|exists:locations,id_location',
            'organizer_id' => 'nullable|integer|exists:organizers,id_organizer',
            'include_club_host_on_pdf' => 'sometimes|boolean',
            'registration_allowed_until' => 'required|integer|min:0',
            'cancellation_allowed_until' => 'required|integer|min:0',
            'arriving_time' => ['required', PlayingWindow::clockRegexRule()],
            'closing_time' => ['required', PlayingWindow::clockRegexRule()],
        ]);

        $payload = $request->all();
        $payload['description'] = $this->sanitizeDescriptionOrFail((string) $payload['description']);
        $payload['arriving_time'] = PlayingWindow::normalizeStoredTime((string) $payload['arriving_time']);
        $payload['closing_time'] = PlayingWindow::normalizeStoredTime((string) $payload['closing_time']);
        $this->assertDistinctArrivingClosingWindow(
            (string) $payload['arriving_time'],
            (string) $payload['closing_time']
        );
        if ($request->has('include_club_host_on_pdf')) {
            $payload['include_club_host_on_pdf'] = $request->boolean('include_club_host_on_pdf');
        }
        unset($payload['cover_path'], $payload['cover_url'], $payload['slug']);

        EventDeadlineValidator::assertOrdering([
            'date' => $payload['date'],
            'registration_allowed_until' => $payload['registration_allowed_until'],
            'cancellation_allowed_until' => $payload['cancellation_allowed_until'],
        ]);

        $shouldClearAttendance = $this->attendanceHeaderChanged($event, $payload);
        $shouldSyncSlug = array_key_exists('name', $payload) || array_key_exists('organizer_id', $payload);
        $event->update($payload);
        if ($shouldSyncSlug) {
            $this->eventSlugSync->syncForEvent($event->fresh(['organizer']) ?? $event);
        }
        $event->refresh()->load('organizer');
        if ($shouldClearAttendance) {
            $this->posterInvalidator->clearAttendanceForEvent((int) $event->id_event);
        }

        return $this->resourceResponse(
            new EventResource($event),
            'PadelEvent updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/events/{event}",
     *     summary="Partially update an edition",
     *     tags={"🐧 Manager: Events"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=25, description="PadelEvent name"),
     *             @OA\Property(property="description", type="string", description="Rich HTML description"),
     *             @OA\Property(property="date", type="string", format="date", description="PadelEvent date"),
     *             @OA\Property(property="location_id", type="integer", description="Location ID"),
     *             @OA\Property(property="organizer_id", type="integer", nullable=true, description="Organizer branding owner"),
     *             @OA\Property(property="include_club_host_on_pdf", type="boolean", description="Whether posters show the hosting club logo"),
     *             @OA\Property(property="arriving_time", type="string", format="time", description="Arriving time"),
     *             @OA\Property(property="closing_time", type="string", format="time", description="Closing time"),
     *             @OA\Property(property="registration_allowed_until", type="integer", format="timestamp", description="Unix timestamp until which players may register"),
     *             @OA\Property(property="cancellation_allowed_until", type="integer", format="timestamp", description="Unix timestamp until which players may self-cancel")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PadelEvent partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Event")
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
     *         description="PadelEvent not found",
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
    public function patch(Request $request, PadelEvent $event)
    {
        $this->ensureCoverPresent($event);

        $request->validate([
            'name' => ['sometimes', 'string', 'max:25', new EventNameSlugable()],
            'description' => 'sometimes|string',
            'date' => 'sometimes|date',
            'location_id' => 'nullable|exists:locations,id',
            'organizer_id' => 'nullable|integer|exists:organizers,id_organizer',
            'include_club_host_on_pdf' => 'sometimes|boolean',
            'registration_allowed_until' => 'sometimes|integer|min:0',
            'cancellation_allowed_until' => 'sometimes|integer|min:0',
            'arriving_time' => ['nullable', PlayingWindow::clockRegexRule()],
            'closing_time' => ['nullable', PlayingWindow::clockRegexRule()],
        ]);

        $payload = $request->all();
        if (array_key_exists('description', $payload)) {
            $payload['description'] = $this->sanitizeDescriptionOrFail((string) $payload['description']);
        }
        if (array_key_exists('arriving_time', $payload) && $payload['arriving_time'] !== null && $payload['arriving_time'] !== '') {
            $payload['arriving_time'] = PlayingWindow::normalizeStoredTime((string) $payload['arriving_time']);
        }
        if (array_key_exists('closing_time', $payload) && $payload['closing_time'] !== null && $payload['closing_time'] !== '') {
            $payload['closing_time'] = PlayingWindow::normalizeStoredTime((string) $payload['closing_time']);
        }
        if (
            (array_key_exists('arriving_time', $payload) && $payload['arriving_time'] !== null && $payload['arriving_time'] !== '')
            || (array_key_exists('closing_time', $payload) && $payload['closing_time'] !== null && $payload['closing_time'] !== '')
        ) {
            $this->assertDistinctArrivingClosingWindow(
                (string) ($payload['arriving_time'] ?? $event->getAttributes()['arriving_time']),
                (string) ($payload['closing_time'] ?? $event->getAttributes()['closing_time'])
            );
        }
        if ($request->has('include_club_host_on_pdf')) {
            $payload['include_club_host_on_pdf'] = $request->boolean('include_club_host_on_pdf');
        }
        unset($payload['cover_path'], $payload['cover_url'], $payload['slug']);

        if (array_key_exists('date', $payload)
            || array_key_exists('registration_allowed_until', $payload)
            || array_key_exists('cancellation_allowed_until', $payload)) {
            EventDeadlineValidator::assertOrdering([
                'date' => $payload['date'] ?? $event->date?->format('Y-m-d'),
                'registration_allowed_until' => $payload['registration_allowed_until'] ?? $event->registration_allowed_until,
                'cancellation_allowed_until' => $payload['cancellation_allowed_until'] ?? $event->cancellation_allowed_until,
            ]);
        }

        $shouldClearAttendance = $this->attendanceHeaderChanged($event, $payload);
        $shouldSyncSlug = array_key_exists('name', $payload) || array_key_exists('organizer_id', $payload);
        $event->update($payload);
        if ($shouldSyncSlug) {
            $this->eventSlugSync->syncForEvent($event->fresh(['organizer']) ?? $event);
        }
        $event->refresh()->load('organizer');
        if ($shouldClearAttendance) {
            $this->posterInvalidator->clearAttendanceForEvent((int) $event->id_event);
        }

        return $this->resourceResponse(
            new EventResource($event),
            'PadelEvent partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/events/{event}",
     *     summary="Delete an edition",
     *     tags={"🐧 Manager: Events"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PadelEvent deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="PadelEvent cannot be deleted due to linked data",
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
    public function destroy(PadelEvent $event)
    {
        $linkedDataRelations = [
            'divisions' => 'divisions_count',
            'participations' => 'participations_count',
            'lotteries' => 'lotteries_count',
        ];

        return $this->deleteResourceWithResponse(
            $event,
            $linkedDataRelations,
            'event',
            'PadelEvent deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/events/bulk",
     *     summary="Bulk delete editions",
     *     tags={"🐧 Manager: Events"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event_ids"},
     *             @OA\Property(
     *                 property="event_ids",
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
            'event_ids' => 'required|array|min:1',
            'event_ids.*' => 'integer',
        ]);

        $linkedDataRelations = [
            'divisions' => 'divisions_count',
            'participations' => 'participations_count',
            'lotteries' => 'lotteries_count',
        ];

        return $this->deleteBulkResourcesWithResponse(
            $request->event_ids,
            PadelEvent::class,
            $linkedDataRelations,
            'event',
            'Events deleted successfully'
        );
    }

    private function assertDistinctArrivingClosingWindow(string $start, string $end): void
    {
        if (PlayingWindow::windowsAreDistinct($start, $end)) {
            return;
        }

        throw ValidationException::withMessages([
            'closing_time' => ['Arriving time and closing time must differ.'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function attendanceHeaderChanged(PadelEvent $event, array $payload): bool
    {
        foreach (['name', 'date', 'arriving_time', 'closing_time', 'organizer_id'] as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $incoming = $payload[$field];
            $current = $event->getAttribute($field);

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

    private function ensureCoverPresent(PadelEvent $event): void
    {
        if ($event->cover_path !== null && $event->cover_path !== '') {
            return;
        }

        throw ValidationException::withMessages([
            'cover_path' => ['A cover image is required before updating this edition.'],
        ]);
    }

    private function sanitizeDescriptionOrFail(string $description): string
    {
        $sanitized = $this->descriptionSanitizer->sanitize($description);

        if ($this->descriptionSanitizer->isEmpty($sanitized)) {
            throw ValidationException::withMessages([
                'description' => ['The description field is required.'],
            ]);
        }

        return $sanitized;
    }
}
