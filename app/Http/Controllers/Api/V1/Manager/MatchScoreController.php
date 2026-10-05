<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\GameMatchResource;
use App\Models\GameMatch;
use App\Models\MatchSide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Matches",
 *     description="(v1) Match scores management"
 * )
 */
class MatchScoreController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Patch(
     *     path="/api/v1/manager/matches/{match}/scores",
     *     summary="Update side scores for a match",
     *     tags={"🐧 Manager: Matches"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="match", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="left_score", type="integer", nullable=true),
     *             @OA\Property(property="right_score", type="integer", nullable=true),
     *             @OA\Property(
     *                 property="sides",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="side", type="string", enum={"LEFT", "RIGHT"}),
     *                     @OA\Property(property="score", type="integer", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Scores updated"),
     *     @OA\Response(response=409, description="PadelEvent finalized")
     * )
     */
    public function update(Request $request, GameMatch $match)
    {
        $match->loadMissing('eventDivision.event');

        if ($match->eventDivision?->event?->finalized_at !== null) {
            return $this->errorResponse('Scores cannot be updated after finalize.', 409);
        }

        $validated = $request->validate([
            'left_score' => 'nullable|integer|min:0|max:127',
            'right_score' => 'nullable|integer|min:0|max:127',
            'sides' => 'nullable|array',
            'sides.*.side' => 'required_with:sides|in:LEFT,RIGHT',
            'sides.*.score' => 'nullable|integer|min:0|max:127',
        ]);

        $updates = [];
        if (array_key_exists('left_score', $validated)) {
            $updates['LEFT'] = $validated['left_score'];
        }
        if (array_key_exists('right_score', $validated)) {
            $updates['RIGHT'] = $validated['right_score'];
        }
        foreach ($validated['sides'] ?? [] as $sideRow) {
            $updates[$sideRow['side']] = $sideRow['score'] ?? null;
        }

        if ($updates === []) {
            return $this->errorResponse('Provide left_score/right_score or sides[].', 422);
        }

        DB::transaction(function () use ($match, $updates) {
            foreach ($updates as $sideName => $score) {
                MatchSide::query()->updateOrCreate(
                    ['match_id' => $match->id_match, 'side' => $sideName],
                    ['score' => $score]
                );
            }
        });

        $match->load([
            'sides.players.player',
            'sides.players.participation',
            'eventDivisionCourt.court',
        ]);

        return $this->resourceResponse(
            new GameMatchResource($match),
            'Match scores updated successfully'
        );
    }
}
