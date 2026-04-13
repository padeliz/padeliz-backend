<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Resources\EditionParticipationResource;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\PlayerEligibility;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐥 App: Edition Participations",
 *     description="(v1) Player edition participation and registration"
 * )
 */
class EditionParticipationController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/app/editions/{edition}/participate",
     *     summary="Register for an edition",
     *     tags={"🐥 App: Edition Participations"},
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
     *         description="Successfully registered",
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
     *         response=403,
     *         description="Player not eligible for this edition",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or past edition",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function store(Request $request, Edition $edition)
    {
        // Check if edition is in the past
        if (strtotime($edition->date->format('Y-m-d')) <= strtotime(date('Y-m-d'))) {
            return $this->validationErrorResponse(['edition' => ['Cannot join past editions']]);
        }

        // Check if player has approved eligibility
        $eligibility = PlayerEligibility::where('player_id', $request->user()->id_player)
            ->where('review_status', 'approved')
            ->first();

        if (! $eligibility) {
            return $this->forbiddenResponse('You are not eligible yet. Please submit your eligibility request first.');
        }

        $validatedData = $request->validate([
            'division_id' => [
                'required',
                Rule::exists('edition_divisions', 'division_id')
                    ->where('edition_id', $edition->id_edition),
            ],
        ]);

        // Get division details for this edition
        $editionDivision = EditionDivision::where('edition_id', $edition->id_edition)
            ->where('division_id', $validatedData['division_id'])
            ->firstOrFail();

        // Check if player is already participating in this edition
        $existingParticipation = $edition->participations()
            ->where('player_id', $request->user()->id_player)
            ->first();

        if ($existingParticipation) {
            return $this->validationErrorResponse(['player' => ['You are already registered for this edition']]);
        }

        // Check if division is full
        $currentPlayers = $edition->participations()
            ->where('division_id', $editionDivision->division_id)
            ->count();

        if (
            $currentPlayers >= $editionDivision->players_limit
        ) {
            return $this->validationErrorResponse(['division_id' => ['This division is already full']]);
        }

        // Create participation
        $isStudent = (bool) ($eligibility->being_student ?? false);
        $participation = $edition->participations()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $request->user()->id_player,
            'division_id' => $editionDivision->division_id,
            'is_minor' => 0,
            'is_student' => $isStudent,
            'discount' => 0,
            'payment' => $isStudent ? $edition->students_entry_fee : $edition->entry_fee,
        ]);

        return $this->resourceResponse(
            new EditionParticipationResource($participation),
            'Successfully registered for edition',
            201
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/app/editions/{edition}/withdraw",
     *     summary="Withdraw from an edition",
     *     tags={"🐥 App: Edition Participations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Withdrawn successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     */
    public function destroy(Edition $edition, Request $request)
    {
        $participation = $edition->participations()
            ->where('player_id', $request->user()->id_player)
            ->firstOrFail();

        $participation->delete();

        return $this->successResponse(null, 'Withdrawn successfully');
    }
}
