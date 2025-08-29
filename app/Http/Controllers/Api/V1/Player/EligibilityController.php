<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\Controller;
use App\Models\PlayerEligibility;
use App\Models\Edition;
use App\Http\Resources\PlayerEligibilityResource;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="🐥 Player: Eligibilities",
 *     description="(v1) Player eligibility and verification"
 * )
 */
class EligibilityController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/player/eligibility/approved",
     *     summary="Get user's last approved eligibility",
     *     tags={"🐥 Player: Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Last approved eligibility",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/PlayerEligibility")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No approved eligibility found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
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
            return $this->notFoundResponse('No approved eligibility found');
        }

        return $this->resourceResponse(
            new PlayerEligibilityResource($eligibility),
            'Approved eligibility retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/player/eligibility/pending",
     *     summary="Get user's pending eligibility",
     *     tags={"🐥 Player: Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Pending eligibility",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/PlayerEligibility")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No pending eligibility found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function pending(Request $request)
    {
        $eligibility = PlayerEligibility::where('player_id', $request->user()->id_player)
            ->where('review_status', 'pending')
            ->first();

        if (!$eligibility) {
            return $this->notFoundResponse('No pending eligibility found');
        }

        return $this->resourceResponse(
            new PlayerEligibilityResource($eligibility),
            'Pending eligibility retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/player/eligibility/rejected",
     *     summary="Get user's last rejected eligibility",
     *     tags={"🐥 Player: Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Last rejected eligibility",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/PlayerEligibility")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No rejected eligibility found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
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
            return $this->notFoundResponse('No rejected eligibility found');
        }

        return $this->resourceResponse(
            new PlayerEligibilityResource($eligibility),
            'Rejected eligibility retrieved successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/player/eligibility",
     *     summary="Update pending eligibility with current player data",
     *     tags={"🐥 Player: Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Eligibility updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/PlayerEligibility")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No pending eligibility found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function update(Request $request)
    {
        $eligibility = PlayerEligibility::where('player_id', $request->user()->id_player)
            ->where('review_status', 'pending')
            ->first();

        if (!$eligibility) {
            return $this->notFoundResponse('No pending eligibility found');
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

        return $this->resourceResponse(
            new PlayerEligibilityResource($eligibility),
            'Eligibility updated successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/player/eligibility",
     *     summary="Create new eligibility",
     *     tags={"🐥 Player: Eligibilities"},
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
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/PlayerEligibility")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or pending eligibility exists or past edition",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
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
            return $this->validationErrorResponse(['edition_id' => ['You already have a pending eligibility']]);
        }

        $request->validate([
            'edition_id' => 'required|exists:editions,id_edition'
        ]);

        // Check if edition is in the past
        $edition = Edition::findOrFail($request->edition_id);
        if (strtotime($edition->date) <= strtotime(date('Y-m-d'))) {
            return $this->validationErrorResponse(['edition_id' => ['Cannot create eligibility for past editions']]);
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

        return $this->resourceResponse(
            new PlayerEligibilityResource($eligibility),
            'Eligibility created successfully',
            201
        );
    }
}
