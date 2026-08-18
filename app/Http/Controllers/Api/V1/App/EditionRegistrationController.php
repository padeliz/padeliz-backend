<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Resources\EditionRegistrationResource;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionRegistration;
use App\Models\Eligibility;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐥 App: Edition Registrations",
 *     description="(v1) Player edition registration"
 * )
 */
class EditionRegistrationController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/app/edition-registrations",
     *     summary="Register for an edition",
     *     tags={"🐥 App: Edition Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"edition_id"},
     *             @OA\Property(property="edition_id", type="integer"),
     *             @OA\Property(property="edition_division_id", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Registration created"),
     *     @OA\Response(response=403, description="Player not eligible"),
     *     @OA\Response(response=422, description="Validation error or past edition")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'edition_id' => 'required|exists:editions,id_edition',
        ]);

        $edition = Edition::query()->findOrFail((int) $request->input('edition_id'));

        if (strtotime($edition->date->format('Y-m-d')) <= strtotime(date('Y-m-d'))) {
            return $this->validationErrorResponse(['edition' => ['Cannot join past editions']]);
        }

        $eligibility = Eligibility::where('player_id', $request->user()->id_player)
            ->where('review_status', 'approved')
            ->first();

        if (! $eligibility) {
            return $this->forbiddenResponse('You are not eligible yet. Please submit your eligibility request first.');
        }

        $validated = $request->validate([
            'edition_division_id' => [
                'nullable',
                Rule::exists('edition_divisions', 'id_edition_division')
                    ->where('edition_id', $edition->id_edition),
            ],
        ]);

        $playerId = (int) $request->user()->id_player;
        $editionDivisionId = array_key_exists('edition_division_id', $validated)
            ? ($validated['edition_division_id'] !== null ? (int) $validated['edition_division_id'] : null)
            : null;

        $xor = EditionRegistration::xorConflict((int) $edition->id_edition, $playerId, $editionDivisionId);
        if ($xor !== null) {
            return $this->validationErrorResponse($xor);
        }

        if ($editionDivisionId !== null) {
            $editionDivision = EditionDivision::query()->findOrFail($editionDivisionId);
            $currentPlayers = EditionRegistration::query()
                ->where('edition_division_id', $editionDivisionId)
                ->count();

            if ($currentPlayers >= $editionDivision->players_limit) {
                return $this->validationErrorResponse(['edition_division_id' => ['This division is already full']]);
            }
        }

        $isStudent = (bool) ($eligibility->being_student ?? false);
        $registration = EditionRegistration::query()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $playerId,
            'edition_division_id' => $editionDivisionId,
            'is_minor' => false,
            'is_student' => $isStudent,
            'discount' => 0,
            'payment' => $isStudent ? $edition->students_entry_fee : $edition->entry_fee,
        ]);

        return $this->resourceResponse(
            new EditionRegistrationResource($registration),
            'Registration created',
            201
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/app/edition-registrations/{editionRegistration}",
     *     summary="Cancel own registration",
     *     tags={"🐥 App: Edition Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="editionRegistration",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Registration deleted"),
     *     @OA\Response(response=404, description="Registration not found"),
     *     @OA\Response(response=422, description="Past edition")
     * )
     */
    public function destroy(Request $request, int $editionRegistration)
    {
        $registration = EditionRegistration::query()
            ->where('id_registration', $editionRegistration)
            ->where('player_id', $request->user()->id_player)
            ->firstOrFail();

        $registration->loadMissing('edition');
        $edition = $registration->edition;
        if ($edition !== null && strtotime($edition->date->format('Y-m-d')) <= strtotime(date('Y-m-d'))) {
            return $this->validationErrorResponse(['edition' => ['Cannot cancel registration for past editions']]);
        }

        $registration->delete();

        return $this->successResponse(null, 'Registration deleted');
    }
}
