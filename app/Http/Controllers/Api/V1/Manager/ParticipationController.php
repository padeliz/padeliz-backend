<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Models\{Edition, EditionParticipation, Player};
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Manager: Participations",
 *     description="(v1) Participation management and operations"
 * )
 */
class ParticipationController extends V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/manager/editions/{edition}/participations",
     *     summary="Add player to edition",
     *     tags={"Manager: Participations"},
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
     *             required={"player_id", "division_id"},
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="division_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Player added to edition successfully"
     *     )
     * )
     */
    public function store(Request $request, Edition $edition)
    {
        $request->validate([
            'player_id' => 'required|exists:players,id_player',
            'division_id' => 'required|exists:divisions,id'
        ]);

        // Check if player is already participating
        $existingParticipation = EditionParticipation::where([
            'edition_id' => $edition->id,
            'player_id' => $request->player_id
        ])->first();

        if ($existingParticipation) {
            return response()->json([
                'message' => 'Player is already participating in this edition'
            ], 422);
        }

        $participation = EditionParticipation::create([
            'edition_id' => $edition->id,
            'player_id' => $request->player_id,
            'division_id' => $request->division_id
        ]);

        return response()->json($participation, 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/editions/{edition}/participations/{participation}",
     *     summary="Remove player from edition",
     *     tags={"Manager: Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="participation",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player removed from edition successfully"
     *     )
     * )
     */
    public function destroy(Edition $edition, EditionParticipation $participation)
    {
        // Ensure the participation belongs to the specified edition
        if ($participation->edition_id !== $edition->id) {
            return response()->json([
                'message' => 'Participation does not belong to this edition'
            ], 404);
        }

        $participation->delete();
        return response()->json(['message' => 'Player removed from edition successfully']);
    }
}
