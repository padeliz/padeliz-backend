<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\PlayerEligibility;
use App\Models\EditionDivision;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
     *     tags={"Editions"},
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
        $hasEligibility = PlayerEligibility::where('player_id', $request->user()->id_player)
            ->where('review_status', 'approved')
            ->exists();

        if (!$hasEligibility) {
            return response()->json([
                'message' => 'You are not eligible yet. Please submit your eligibility request first.'
            ], 403);
        }

        $validatedData = $request->validate([
            'division_id' => [
                'required',
                Rule::exists('edition_divisions', 'id_division')
                ->where('edition_id', $edition->id_edition)
            ]
        ]);

        // Get division details
        $division = EditionDivision::findOrFail($validatedData['division_id']);

        // Check if division is full
        $currentPlayers = $edition->participations()
            ->where('division_id', $division->id_division)
            ->count();

        if ($currentPlayers >= $division->players_limit) {
            return response()->json([
                'message' => 'This division is already full'
            ], 422);
        }

        // Create participation
        $participation = $edition->participations()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $request->user()->id_player,
            'division_id' => $division->id_division,
            'is_minor' => 0,
            'is_student' => $hasEligibility->being_student,
            'discount' => 0,
            'payment' => $hasEligibility->being_student ? $edition->student_entry_fee : $edition->entry_fee,
        ]);

        return response()->json(
            $participation,
            201
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/editions/{edition}/withdraw",
     *     summary="Withdraw from an edition",
     *     tags={"Editions"},
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
        $validated = $request->validate(['player_id' => 'required|exists:players,id_player',
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
