<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EventDivisionReplacementResource;
use App\Models\EventDivision;
use App\Models\EventDivisionReplacement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Divisions",
 *     description="(v1) API Endpoints for edition divisions"
 * )
 */
class EventDivisionReplacementController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /** @var list<string> */
    private const LIST_WITH = [
        'eventDivision',
        'originalParticipation.player',
        'substituteParticipation.player',
        'substitutePlayer',
    ];

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/replacements",
     *     summary="List replacements for an edition division",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Replacements list")
     * )
     */
    public function index(EventDivision $eventDivision)
    {
        $replacements = $eventDivision->replacements()
            ->with(self::LIST_WITH)
            ->orderByDesc('id_replacement')
            ->get();

        return $this->successResponse(
            EventDivisionReplacementResource::collection($replacements),
            'Replacements retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/events/{event}/replacements",
     *     summary="List replacements for all edition divisions of an edition",
     *     tags={"🐧 Manager: Events"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Replacements list")
     * )
     */
    public function indexForEvent(\App\Models\PadelEvent $event)
    {
        $divisionIds = $event->divisions()->pluck('id_event_division');

        $replacements = EventDivisionReplacement::query()
            ->whereIn('event_division_id', $divisionIds)
            ->with(self::LIST_WITH)
            ->orderByDesc('id_replacement')
            ->get();

        return $this->successResponse(
            EventDivisionReplacementResource::collection($replacements),
            'Replacements retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/replacements",
     *     summary="Create a replacement",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"original_participation_id"},
     *             @OA\Property(property="original_participation_id", type="integer"),
     *             @OA\Property(property="substitute_participation_id", type="integer", nullable=true),
     *             @OA\Property(property="substitute_player_id", type="integer", nullable=true),
     *             @OA\Property(property="match_id", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=409, description="Blocked"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, EventDivision $eventDivision)
    {
        $blocked = $this->blockedResponse($eventDivision);
        if ($blocked !== null) {
            return $blocked;
        }

        $validated = $request->validate($this->writeRules($eventDivision, requiredOriginal: true));

        $subPart = $validated['substitute_participation_id'] ?? null;
        $subPlayer = $validated['substitute_player_id'] ?? null;
        if ($subPart !== null && $subPlayer !== null) {
            return $this->errorResponse(
                'Provide at most one of substitute_participation_id or substitute_player_id.',
                422
            );
        }

        $matchId = $validated['match_id'] ?? null;
        if ($this->scopeConflictExists(
            $eventDivision->id_event_division,
            (int) $validated['original_participation_id'],
            $matchId !== null ? (int) $matchId : null,
        )) {
            return $this->errorResponse(
                'A replacement for this player and match scope already exists.',
                422
            );
        }

        try {
            $replacement = EventDivisionReplacement::create([
                'event_division_id' => $eventDivision->id_event_division,
                'original_participation_id' => $validated['original_participation_id'],
                'substitute_participation_id' => $subPart,
                'substitute_player_id' => $subPlayer,
                'match_id' => $validated['match_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to create replacement: ' . $e->getMessage(), 422);
        }

        return $this->resourceResponse(
            new EventDivisionReplacementResource($replacement->load(self::LIST_WITH)),
            'Replacement created successfully',
            201
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/event-division-replacements/{replacement}",
     *     summary="Update a replacement",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="replacement", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="original_participation_id", type="integer"),
     *             @OA\Property(property="substitute_participation_id", type="integer", nullable=true),
     *             @OA\Property(property="substitute_player_id", type="integer", nullable=true),
     *             @OA\Property(property="match_id", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=409, description="Blocked"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function patch(Request $request, EventDivisionReplacement $replacement)
    {
        $replacement->loadMissing('eventDivision.event');
        $eventDivision = $replacement->eventDivision;
        if ($eventDivision === null) {
            return $this->errorResponse('Replacement edition division was not found.', 422);
        }

        $blocked = $this->blockedResponse($eventDivision);
        if ($blocked !== null) {
            return $blocked;
        }

        $validated = $request->validate($this->writeRules($eventDivision, requiredOriginal: false));

        $sentPartNonNull = array_key_exists('substitute_participation_id', $validated)
            && $validated['substitute_participation_id'] !== null;
        $sentPlayerNonNull = array_key_exists('substitute_player_id', $validated)
            && $validated['substitute_player_id'] !== null;

        if ($sentPartNonNull && $sentPlayerNonNull) {
            return $this->errorResponse(
                'Provide at most one of substitute_participation_id or substitute_player_id.',
                422
            );
        }

        if ($sentPartNonNull) {
            $validated['substitute_player_id'] = null;
        } elseif ($sentPlayerNonNull) {
            $validated['substitute_participation_id'] = null;
        }

        $resultingPart = array_key_exists('substitute_participation_id', $validated)
            ? $validated['substitute_participation_id']
            : $replacement->substitute_participation_id;
        $resultingPlayer = array_key_exists('substitute_player_id', $validated)
            ? $validated['substitute_player_id']
            : $replacement->substitute_player_id;

        if ($resultingPart !== null && $resultingPlayer !== null) {
            return $this->errorResponse(
                'Provide at most one of substitute_participation_id or substitute_player_id.',
                422
            );
        }

        $resultingOriginal = array_key_exists('original_participation_id', $validated)
            ? (int) $validated['original_participation_id']
            : (int) $replacement->original_participation_id;
        $resultingMatchId = array_key_exists('match_id', $validated)
            ? ($validated['match_id'] !== null ? (int) $validated['match_id'] : null)
            : ($replacement->match_id !== null ? (int) $replacement->match_id : null);

        if ($this->scopeConflictExists(
            $eventDivision->id_event_division,
            $resultingOriginal,
            $resultingMatchId,
            (int) $replacement->id_replacement,
        )) {
            return $this->errorResponse(
                'A replacement for this player and match scope already exists.',
                422
            );
        }

        try {
            $replacement->update($validated);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update replacement: ' . $e->getMessage(), 422);
        }

        return $this->resourceResponse(
            new EventDivisionReplacementResource($replacement->fresh()->load(self::LIST_WITH)),
            'Replacement updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/event-division-replacements/{replacement}",
     *     summary="Delete a replacement",
     *     tags={"🐧 Manager: PadelEvent Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="replacement", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=409, description="Finalized")
     * )
     */
    public function destroy(EventDivisionReplacement $replacement)
    {
        $replacement->loadMissing('eventDivision.event');
        $eventDivision = $replacement->eventDivision;

        if ($eventDivision?->event?->finalized_at !== null) {
            return $this->errorResponse('Replacements cannot be deleted after finalize.', 409);
        }

        $replacement->delete();

        return $this->successResponse(null, 'Replacement deleted successfully');
    }

    private function blockedResponse(EventDivision $eventDivision)
    {
        $eventDivision->loadMissing(['event', 'schedule']);
        if ($eventDivision->event?->finalized_at !== null) {
            return $this->errorResponse('Replacements cannot be created after finalize.', 409);
        }

        $schedule = $eventDivision->schedule;
        if ($schedule === null) {
            return $this->errorResponse('PadelEvent division has no schedule.', 422);
        }

        if (! $schedule->allow_replacements) {
            return $this->errorResponse('Replacements are not allowed for this edition division.', 409);
        }

        return null;
    }

    private function scopeConflictExists(
        int $eventDivisionId,
        int $originalParticipationId,
        ?int $matchId,
        ?int $excludeReplacementId = null,
    ): bool {
        $query = EventDivisionReplacement::query()
            ->where('event_division_id', $eventDivisionId)
            ->where('original_participation_id', $originalParticipationId);

        if ($matchId === null) {
            $query->whereNull('match_id');
        } else {
            $query->where('match_id', $matchId);
        }

        if ($excludeReplacementId !== null) {
            $query->where('id_replacement', '!=', $excludeReplacementId);
        }

        return $query->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function writeRules(EventDivision $eventDivision, bool $requiredOriginal): array
    {
        $original = [
            $requiredOriginal ? 'required' : 'sometimes',
            'integer',
            Rule::exists('event_participations', 'id_participation')
                ->where('event_division_id', $eventDivision->id_event_division),
        ];

        return [
            'original_participation_id' => $original,
            'substitute_participation_id' => [
                'nullable',
                'integer',
                Rule::exists('event_participations', 'id_participation')
                    ->where('event_division_id', $eventDivision->id_event_division),
            ],
            'substitute_player_id' => 'nullable|integer|exists:players,id_player',
            'match_id' => [
                'nullable',
                'integer',
                Rule::exists('matches', 'id_match')
                    ->where('event_division_id', $eventDivision->id_event_division),
            ],
        ];
    }
}
