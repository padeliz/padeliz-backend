<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\EditionParticipation;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Edition Participations",
 *     description="API Endpoints for edition participations"
 * )
 */
class EditionParticipationController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/editions/{edition}/participate",
     *     summary="Register for an edition",
     *     tags={"Edition Participations"},
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
     *             required={"division_id", "player_id"},
     *             @OA\Property(property="division_id", type="integer"),
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="is_minor", type="boolean"),
     *             @OA\Property(property="is_student", type="boolean"),
     *             @OA\Property(property="discount", type="number"),
     *             @OA\Property(property="payment", type="number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Participation created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/EditionParticipation")
     *     )
     * )
     */
    public function store(Request $request, Edition $edition)
    {
        $validated = $request->validate([
            'division_id' => 'required|exists:pm_divisions,id_division',
            'player_id' => 'required|exists:pm_players,id_player',
            'is_minor' => 'boolean',
            'is_student' => 'boolean',
            'discount' => 'nullable|numeric|min:0',
            'payment' => 'nullable|numeric|min:0',
        ]);

        $participation = $edition->participations()->create($validated);
        return response()->json($participation->load(['player', 'division']), 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/editions/{edition}/withdraw",
     *     summary="Withdraw from an edition",
     *     tags={"Edition Participations"},
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
     *             required={"player_id"},
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="absence_reason", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Withdrawn successfully"
     *     )
     * )
     */
    public function destroy(Edition $edition, Request $request)
    {
        $validated = $request->validate([
            'player_id' => 'required|exists:pm_players,id_player',
            'absence_reason' => 'nullable|string|max:255',
        ]);

        $participation = $edition->participations()
            ->where('player_id', $validated['player_id'])
            ->firstOrFail();

        $participation->update([
            'absence_reason' => $validated['absence_reason'] ?? null
        ]);

        return response()->json(['message' => 'Withdrawn successfully']);
    }
}
