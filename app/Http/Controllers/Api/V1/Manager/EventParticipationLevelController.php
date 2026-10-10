<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerLevelResource;
use App\Models\EventParticipation;
use App\Services\PlayerLevel\PlayerLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Participations",
 *     description="(v1) API Endpoints for edition participations"
 * )
 */
class EventParticipationLevelController extends V1BaseController
{
    public function __construct(
        private readonly PlayerLevelService $playerLevels,
    ) {
        parent::__construct();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-participations/{eventParticipation}/level",
     *     summary="Get the player level entered for a participation",
     *     tags={"🐧 Manager: PadelEvent Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventParticipation", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Player level"),
     *     @OA\Response(response=404, description="No level entered for this participation", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function show(EventParticipation $eventParticipation): JsonResponse
    {
        $level = $eventParticipation->playerLevel;

        if ($level === null) {
            return $this->notFoundResponse('No player level has been entered for this participation');
        }

        return $this->resourceResponse(
            new PlayerLevelResource($level),
            'Participation player level retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-participations/{eventParticipation}/level",
     *     summary="Set the player level for a participation (create or update in place)",
     *     description="Upserts the participation-sourced `player_levels` row. Re-setting updates the same row and keeps its `inserted_at`, so an older participation never overtakes a newer level. The value needs exactly two decimals and must sit inside a seeded band.",
     *     tags={"🐧 Manager: PadelEvent Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventParticipation", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(required={"level"}, @OA\Property(property="level", type="string", example="3.50"))
     *     ),
     *     @OA\Response(response=200, description="Player level saved"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function upsert(Request $request, EventParticipation $eventParticipation): JsonResponse
    {
        $request->validate([
            'level' => 'required|numeric',
        ]);

        $input = $request->input('level');

        $level = $this->playerLevels->upsertForParticipation(
            (int) $eventParticipation->player_id,
            (int) $eventParticipation->id_participation,
            is_string($input) ? $input : (float) $input
        );

        return $this->resourceResponse(
            new PlayerLevelResource($level),
            'Participation player level saved successfully'
        );
    }
}
