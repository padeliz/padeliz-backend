<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionDivisionReplacementResource;
use App\Models\EditionDivision;
use App\Models\EditionDivisionReplacement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Edition Divisions",
 *     description="(v1) API Endpoints for edition divisions"
 * )
 */
class EditionDivisionReplacementController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /** @var list<string> */
    private const LIST_WITH = [
        'editionDivision',
        'originalParticipation.player',
        'substituteParticipation.player',
        'substitutePlayer',
    ];

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/replacements",
     *     summary="List replacements for an edition division",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Replacements list")
     * )
     */
    public function index(EditionDivision $editionDivision)
    {
        $replacements = $editionDivision->replacements()
            ->with(self::LIST_WITH)
            ->orderByDesc('id_replacement')
            ->get();

        return $this->successResponse(
            EditionDivisionReplacementResource::collection($replacements),
            'Replacements retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/{edition}/replacements",
     *     summary="List replacements for all edition divisions of an edition",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="edition", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Replacements list")
     * )
     */
    public function indexForEdition(\App\Models\Edition $edition)
    {
        $divisionIds = $edition->divisions()->pluck('id_edition_division');

        $replacements = EditionDivisionReplacement::query()
            ->whereIn('edition_division_id', $divisionIds)
            ->with(self::LIST_WITH)
            ->orderByDesc('id_replacement')
            ->get();

        return $this->successResponse(
            EditionDivisionReplacementResource::collection($replacements),
            'Replacements retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/replacements",
     *     summary="Create a replacement",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionDivision", in="path", required=true, @OA\Schema(type="integer")),
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
    public function store(Request $request, EditionDivision $editionDivision)
    {
        $blocked = $this->blockedResponse($editionDivision);
        if ($blocked !== null) {
            return $blocked;
        }

        $validated = $request->validate($this->writeRules($editionDivision, requiredOriginal: true));

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
            $editionDivision->id_edition_division,
            (int) $validated['original_participation_id'],
            $matchId !== null ? (int) $matchId : null,
        )) {
            return $this->errorResponse(
                'A replacement for this player and match scope already exists.',
                422
            );
        }

        try {
            $replacement = EditionDivisionReplacement::create([
                'edition_division_id' => $editionDivision->id_edition_division,
                'original_participation_id' => $validated['original_participation_id'],
                'substitute_participation_id' => $subPart,
                'substitute_player_id' => $subPlayer,
                'match_id' => $validated['match_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to create replacement: ' . $e->getMessage(), 422);
        }

        return $this->resourceResponse(
            new EditionDivisionReplacementResource($replacement->load(self::LIST_WITH)),
            'Replacement created successfully',
            201
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/edition-division-replacements/{replacement}",
     *     summary="Update a replacement",
     *     tags={"🐧 Manager: Edition Divisions"},
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
    public function patch(Request $request, EditionDivisionReplacement $replacement)
    {
        $replacement->loadMissing('editionDivision.edition');
        $editionDivision = $replacement->editionDivision;
        if ($editionDivision === null) {
            return $this->errorResponse('Replacement edition division was not found.', 422);
        }

        $blocked = $this->blockedResponse($editionDivision);
        if ($blocked !== null) {
            return $blocked;
        }

        $validated = $request->validate($this->writeRules($editionDivision, requiredOriginal: false));

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
            $editionDivision->id_edition_division,
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
            new EditionDivisionReplacementResource($replacement->fresh()->load(self::LIST_WITH)),
            'Replacement updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-division-replacements/{replacement}",
     *     summary="Delete a replacement",
     *     tags={"🐧 Manager: Edition Divisions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="replacement", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=409, description="Finalized")
     * )
     */
    public function destroy(EditionDivisionReplacement $replacement)
    {
        $replacement->loadMissing('editionDivision.edition');
        $editionDivision = $replacement->editionDivision;

        if ($editionDivision?->edition?->finalized_at !== null) {
            return $this->errorResponse('Replacements cannot be deleted after finalize.', 409);
        }

        $replacement->delete();

        return $this->successResponse(null, 'Replacement deleted successfully');
    }

    private function blockedResponse(EditionDivision $editionDivision)
    {
        $editionDivision->loadMissing(['edition', 'schedule']);
        if ($editionDivision->edition?->finalized_at !== null) {
            return $this->errorResponse('Replacements cannot be created after finalize.', 409);
        }

        $schedule = $editionDivision->schedule;
        if ($schedule === null) {
            return $this->errorResponse('Edition division has no schedule.', 422);
        }

        if (! $schedule->allow_replacements) {
            return $this->errorResponse('Replacements are not allowed for this edition division.', 409);
        }

        return null;
    }

    private function scopeConflictExists(
        int $editionDivisionId,
        int $originalParticipationId,
        ?int $matchId,
        ?int $excludeReplacementId = null,
    ): bool {
        $query = EditionDivisionReplacement::query()
            ->where('edition_division_id', $editionDivisionId)
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
    private function writeRules(EditionDivision $editionDivision, bool $requiredOriginal): array
    {
        $original = [
            $requiredOriginal ? 'required' : 'sometimes',
            'integer',
            Rule::exists('edition_participations', 'id_participation')
                ->where('edition_division_id', $editionDivision->id_edition_division),
        ];

        return [
            'original_participation_id' => $original,
            'substitute_participation_id' => [
                'nullable',
                'integer',
                Rule::exists('edition_participations', 'id_participation')
                    ->where('edition_division_id', $editionDivision->id_edition_division),
            ],
            'substitute_player_id' => 'nullable|integer|exists:players,id_player',
            'match_id' => [
                'nullable',
                'integer',
                Rule::exists('matches', 'id_match')
                    ->where('edition_division_id', $editionDivision->id_edition_division),
            ],
        ];
    }
}
