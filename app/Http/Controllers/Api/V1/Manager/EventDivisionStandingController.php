<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EventDivisionStandingResource;
use App\Models\EventDivision;
use App\Models\EventDivisionStanding;
use App\Services\Standings\StandingsRecalculator;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Divisions",
 *     description="(v1) API Endpoints for edition divisions"
 * )
 */
class EventDivisionStandingController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function __construct(
        private readonly StandingsRecalculator $recalculator,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/standings",
     *     summary="List standings for an edition division",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Standings list")
     * )
     */
    public function index(EventDivision $eventDivision)
    {
        $standings = EventDivisionStanding::query()
            ->where('event_division_id', $eventDivision->id_event_division)
            ->orderBy('rank')
            ->orderBy('id_standing')
            ->get();

        return $this->successResponse(
            EventDivisionStandingResource::collection($standings),
            'Standings retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/standings/recalculate",
     *     summary="Recalculate standings from REGULAR match scores",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Standings recalculated"),
     *     @OA\Response(response=409, description="Finalized")
     * )
     */
    public function recalculate(EventDivision $eventDivision)
    {
        $eventDivision->loadMissing('event');
        if ($eventDivision->event?->finalized_at !== null) {
            return $this->errorResponse('Standings cannot be recalculated after finalize.', 409);
        }

        $standings = $this->recalculator->recalculate($eventDivision);

        return $this->successResponse(
            EventDivisionStandingResource::collection(collect($standings)),
            'Standings recalculated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/event-division-standings/{standing}",
     *     summary="Manually edit a standing row",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="standing", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="points_total", type="integer", nullable=true),
     *             @OA\Property(property="wins", type="number", nullable=true),
     *             @OA\Property(property="rank", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Standing updated"),
     *     @OA\Response(response=409, description="Finalized")
     * )
     */
    public function patch(Request $request, EventDivisionStanding $standing)
    {
        $standing->loadMissing('eventDivision.event');

        if ($standing->eventDivision?->event?->finalized_at !== null) {
            return $this->errorResponse('Standings cannot be edited after finalize.', 409);
        }

        $validated = $request->validate([
            'points_total' => 'nullable|integer|min:0',
            'wins' => 'nullable|numeric|min:0',
            'rank' => 'nullable|integer|min:1',
        ]);

        $standing->update($validated);

        return $this->resourceResponse(
            new EventDivisionStandingResource($standing),
            'Standing updated successfully'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/event-division-standings/{standing}",
     *     summary="Replace standing values",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="standing", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Standing updated")
     * )
     */
    public function update(Request $request, EventDivisionStanding $standing)
    {
        $standing->loadMissing('eventDivision.event');

        if ($standing->eventDivision?->event?->finalized_at !== null) {
            return $this->errorResponse('Standings cannot be edited after finalize.', 409);
        }

        $validated = $request->validate([
            'points_total' => 'required|integer|min:0',
            'wins' => 'required|numeric|min:0',
            'rank' => 'required|integer|min:1',
        ]);

        $standing->update($validated);

        return $this->resourceResponse(
            new EventDivisionStandingResource($standing),
            'Standing updated successfully'
        );
    }
}
