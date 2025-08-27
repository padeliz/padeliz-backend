<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Models\{Edition, EditionParticipation, Player};
use App\Http\Resources\EditionParticipationResource;
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
     *         description="Player added to edition successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionParticipation")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
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
            return $this->validationErrorResponse(['player_id' => ['Player is already participating in this edition']]);
        }

        $participation = EditionParticipation::create([
            'edition_id' => $edition->id,
            'player_id' => $request->player_id,
            'division_id' => $request->division_id
        ]);

        return $this->resourceResponse(
            new EditionParticipationResource($participation),
            'Player added to edition successfully',
            201
        );
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
     *         description="Player removed from edition successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Participation not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function destroy(Edition $edition, EditionParticipation $participation)
    {
        // Ensure the participation belongs to the specified edition
        if ($participation->edition_id !== $edition->id) {
            return $this->notFoundResponse('Participation does not belong to this edition');
        }

        $participation->delete();
        return $this->successResponse(null, 'Player removed from edition successfully');
    }
}
