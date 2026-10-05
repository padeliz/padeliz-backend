<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Requests\App\EventDateRangeRequest;
use App\Http\Resources\EventResource;
use App\Models\PadelEvent;
use App\Models\Player;
use App\Services\EventAppQueryService;
use App\Services\EventBadgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="🐥 App: Events",
 *     description="(v1) Player events (public + optional auth filters)"
 * )
 */
class EventController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function __construct(
        private readonly EventAppQueryService $queryService,
        private readonly EventBadgeService $badgeService,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/events",
     *     summary="List events in a date range (public; optional auth filters)",
     *     tags={"🐥 App: Events"},
     *     security={},
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
     *     @OA\Parameter(name="participated", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="registered", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="unpaid", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="lottery_winner", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(
     *         name="club_ids[]",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
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
     *     @OA\Response(response=401, description="Auth required for player filters"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function index(EventDateRangeRequest $request)
    {
        $validated = $request->validated();
        $player = $this->optionalPlayer();

        if ($this->queryService->requiresPlayerAuth($validated) && $player === null) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $query = $this->queryService->baseQuery($validated);
        if ($player !== null) {
            $query = $this->queryService->applyPlayerFilters($query, $validated, $player);
        }

        $events = $query->paginate($this->paginationLimit);
        $this->attachBadges($events->getCollection(), $player);

        return EventResource::collection($events);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/events/{event}",
     *     summary="Show a single event (public; badges when authenticated)",
     *     tags={"🐥 App: Events"},
     *     security={},
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
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, PadelEvent $event)
    {
        $event->load(['location.club', 'organizer', 'divisions.division']);
        $event->loadCount('participations');

        $player = $this->optionalPlayer();
        $this->attachBadges(collect([$event]), $player);

        return $this->resourceResponse(
            new EventResource($event),
            'Event retrieved successfully'
        );
    }

    private function optionalPlayer(): ?Player
    {
        $user = Auth::guard('sanctum')->user();

        return $user instanceof Player ? $user : null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PadelEvent>  $events
     */
    private function attachBadges($events, ?Player $player): void
    {
        $badgesById = $this->badgeService->forEvents($events, $player);
        foreach ($events as $event) {
            $event->setAttribute(
                'badges',
                $badgesById[(int) $event->id_event] ?? []
            );
        }
    }
}
