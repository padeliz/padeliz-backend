<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\PlayerEligibility;
use App\Models\EditionDivision;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Player: Edition Participations",
 *     description="(v1) Player edition participation and registration"
 * )
 */
class EditionParticipationController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/player/editions/{edition}/participate",
     *     summary="Register for an edition",
     *     tags={"Player: Edition Participations"},
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
     *             required={"division_id"},
     *             @OA\Property(property="division_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successfully registered"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Player not eligible for this edition"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or past edition"
     *     )
     * )
     */
    public function store(Request $request, Edition $edition)
    {
        // Check if edition is in the past
        if (strtotime($edition->date) <= strtotime(date('Y-m-d'))) {
            return response()->json([
                'message' => 'Cannot join past editions'
            ], 422);
        }

        // Check if player has approved eligibility
        $eligibility = PlayerEligibility::where('player_id', $request->user()->id_player)
            ->where('review_status', 'approved')
        ->first();

        if (!$eligibility) {
            return response()->json([
                'message' => 'You are not eligible yet. Please submit your eligibility request first.'
            ], 403);
        }

        $validatedData = $request->validate([
            'division_id' => [
                'required',
                Rule::exists('edition_divisions', 'division_id')
                ->where('edition_id', $edition->id_edition)
            ]
        ]);

        // Get division details for this edition
        $editionDivision = EditionDivision::where('edition_id', $edition->id_edition)
        ->where('division_id', $validatedData['division_id'])
        ->firstOrFail();

        // Check if division is full
        $currentPlayers = $edition->participations()
            ->where('division_id', $editionDivision->division_id)
            ->count();

        if (
            $currentPlayers >= $editionDivision->players_limit
        ) {
            return response()->json([
                'message' => 'This division is already full'
            ], 422);
        }

        // Create participation
        $participation = $edition->participations()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $request->user()->id_player,
            'division_id' => $editionDivision->division_id,
            'is_minor' => 0,
            'is_student' => $eligibility->being_student,
            'discount' => 0,
            'payment' => $eligibility->being_student ? $edition->students_entry_fee : $edition->entry_fee,
        ]);

        return response()->json(
            $participation,
            201
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/player/editions/{edition}/withdraw",
     *     summary="Withdraw from an edition",
     *     tags={"Player: Edition Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Withdrawn successfully"
     *     )
     * )
     */
    public function destroy(Edition $edition, Request $request)
    {
        $participation = $edition->participations()
            ->where('player_id', $request->user()->id_player)
            ->firstOrFail();

        $participation->delete();

        return response()->json(['message' => 'Withdrawn successfully']);
    }
}
