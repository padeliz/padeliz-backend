<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionDivisionReplacementResource;
use App\Models\EditionDivision;
use App\Models\EditionDivisionReplacement;
use App\Models\MatchPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     *     summary="Create a replacement and mark match players as got_replaced",
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
     *     @OA\Response(response=409, description="Blocked")
     * )
     */
    public function store(Request $request, EditionDivision $editionDivision)
    {
        $editionDivision->loadMissing('edition');
        if ($editionDivision->edition?->finalized_at !== null) {
            return $this->errorResponse('Replacements cannot be created after finalize.', 409);
        }

        if (! $editionDivision->allow_replacements) {
            return $this->errorResponse('Replacements are not allowed for this edition division.', 409);
        }

        $validated = $request->validate([
            'original_participation_id' => [
                'required',
                'integer',
                Rule::exists('edition_participations', 'id_participation')
                    ->where('edition_division_id', $editionDivision->id_edition_division),
            ],
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
        ]);

        if (empty($validated['substitute_participation_id']) && empty($validated['substitute_player_id'])) {
            return $this->errorResponse(
                'Provide substitute_participation_id or substitute_player_id.',
                422
            );
        }

        try {
            $replacement = DB::transaction(function () use ($editionDivision, $validated) {
                $replacement = EditionDivisionReplacement::create([
                    'edition_division_id' => $editionDivision->id_edition_division,
                    'original_participation_id' => $validated['original_participation_id'],
                    'substitute_participation_id' => $validated['substitute_participation_id'] ?? null,
                    'substitute_player_id' => $validated['substitute_player_id'] ?? null,
                    'match_id' => $validated['match_id'] ?? null,
                ]);

                $this->setGotReplaced(
                    $editionDivision,
                    (int) $validated['original_participation_id'],
                    isset($validated['match_id']) ? (int) $validated['match_id'] : null,
                    true
                );

                return $replacement;
            });
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
     * @OA\Delete(
     *     path="/api/v1/manager/edition-division-replacements/{replacement}",
     *     summary="Delete a replacement and clear got_replaced",
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

        DB::transaction(function () use ($replacement, $editionDivision) {
            $this->setGotReplaced(
                $editionDivision,
                (int) $replacement->original_participation_id,
                $replacement->match_id !== null ? (int) $replacement->match_id : null,
                false
            );
            $replacement->delete();
        });

        return $this->successResponse(null, 'Replacement deleted successfully');
    }

    private function setGotReplaced(
        EditionDivision $editionDivision,
        int $originalParticipationId,
        ?int $matchId,
        bool $gotReplaced,
    ): void {
        $query = MatchPlayer::query()
            ->where('participation_id', $originalParticipationId)
            ->whereHas('matchSide.match', function ($q) use ($editionDivision, $matchId) {
                $q->where('edition_division_id', $editionDivision->id_edition_division);
                if ($matchId !== null) {
                    $q->where('id_match', $matchId);
                }
            });

        $query->update(['got_replaced' => $gotReplaced]);
    }
}
