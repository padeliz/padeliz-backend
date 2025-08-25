<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Models\PlayerEligibility;
use App\Models\Edition;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Player: Eligibilities",
 *     description="(v1) Player eligibility and verification"
 * )
 */
class EligibilityController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/player/eligibility/approved",
     *     summary="Get user's last approved eligibility",
     *     tags={"Player: Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Last approved eligibility",
     *         @OA\JsonContent(ref="#/components/schemas/PlayerEligibility")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No approved eligibility found"
     *     )
     * )
     */
    public function approved(Request $request)
    {
        $eligibility = PlayerEligibility::where('player_id', $request->user()->id_player)
            ->where('review_status', 'approved')
            ->latest('reviewed_at')
            ->first();

        if (!$eligibility) {
            return response()->json(['message' => 'No approved eligibility found'], 404);
        }

        return response()->json($eligibility);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/player/eligibility/pending",
     *     summary="Get user's pending eligibility",
     *     tags={"Player: Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Pending eligibility",
     *         @OA\JsonContent(ref="#/components/schemas/PlayerEligibility")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No pending eligibility found"
     *     )
     * )
     */
    public function pending(Request $request)
    {
        $eligibility = PlayerEligibility::where('player_id', $request->user()->id_player)
            ->where('review_status', 'pending')
            ->first();

        if (!$eligibility) {
            return response()->json(['message' => 'No pending eligibility found'], 404);
        }

        return response()->json($eligibility);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/player/eligibility/rejected",
     *     summary="Get user's last rejected eligibility",
     *     tags={"Player: Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Last rejected eligibility",
     *         @OA\JsonContent(ref="#/components/schemas/PlayerEligibility")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No rejected eligibility found"
     *     )
     * )
     */
    public function rejected(Request $request)
    {
        $eligibility = PlayerEligibility::where('player_id', $request->user()->id_player)
            ->where('review_status', 'rejected')
            ->latest('reviewed_at')
            ->first();

        if (!$eligibility) {
            return response()->json(['message' => 'No rejected eligibility found'], 404);
        }

        return response()->json($eligibility);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/player/eligibility",
     *     summary="Update pending eligibility with current player data",
     *     tags={"Player: Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Eligibility updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/PlayerEligibility")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No pending eligibility found"
     *     )
     * )
     */
    public function update(Request $request)
    {
        $eligibility = PlayerEligibility::where('player_id', $request->user()->id_player)
            ->where('review_status', 'pending')
            ->first();

        if (!$eligibility) {
            return response()->json(['message' => 'No pending eligibility found'], 404);
        }

        $player = $request->user();

        $eligibility->update([
            'name' => $player->name,
            'phone' => $player->phone,
            'skill_division' => $player->skill_division,
            'first_name' => $player->first_name,
            'middle_name' => $player->middle_name,
            'last_name' => $player->last_name,
            'year_of_birth' => $player->year_of_birth,
            'being_student' => $player->being_student,
            'playtomic_id' => $player->playtomic_id,
            'rankedin_id' => $player->rankedin_id,
            'instagram_username' => $player->instagram_username,
            'facebook_username' => $player->facebook_username,
            'resubmitted_at' => time()
        ]);

        return response()->json($eligibility);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/player/eligibility",
     *     summary="Create new eligibility",
     *     tags={"Player: Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"edition_id"},
     *             @OA\Property(property="edition_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Eligibility created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/PlayerEligibility")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or pending eligibility exists or past edition"
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Check if pending eligibility exists
        $hasPending = PlayerEligibility::where('player_id', $request->user()->id_player)
            ->where('review_status', 'pending')
            ->exists();

        if ($hasPending) {
            return response()->json([
                'message' => 'You already have a pending eligibility'
            ], 422);
        }

        $request->validate([
            'edition_id' => 'required|exists:editions,id_edition'
        ]);

        // Check if edition is in the past
        $edition = Edition::findOrFail($request->edition_id);
        if (strtotime($edition->date) <= strtotime(date('Y-m-d'))) {
            return response()->json([
                'message' => 'Cannot create eligibility for past editions'
            ], 422);
        }

        $player = $request->user();

        $eligibility = PlayerEligibility::create([
            'edition_id' => $request->edition_id,
            'player_id' => $player->id_player,
            'name' => $player->name,
            'phone' => $player->phone,
            'google_id' => $player->google_id,
            'google_name' => $player->google_name,
            'google_email' => $player->google_email,
            'google_avatar' => $player->google_avatar,
            'skill_division' => $player->skill_division,
            'first_name' => $player->first_name,
            'middle_name' => $player->middle_name,
            'last_name' => $player->last_name,
            'year_of_birth' => $player->year_of_birth,
            'being_student' => $player->being_student,
            'playtomic_id' => $player->playtomic_id,
            'rankedin_id' => $player->rankedin_id,
            'instagram_username' => $player->instagram_username,
            'facebook_username' => $player->facebook_username,
        ]);

        return response()->json($eligibility, 201);
    }
}
