<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\EditionParticipationResource;
use App\Models\{Edition, EditionParticipation, Player};
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Participations",
 *     description="(v1) Participation management and operations"
 * )
 */
class ParticipationController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/{edition}/participations",
     *     summary="Get all participations for an edition",
     *     tags={"🐧 Manager: Participations"},
     *     @OA\Parameter(
     *         name="edition",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/EditionParticipation")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index(Edition $edition)
    {
        $participations = $edition->participations()
            ->with(['player', 'division'])
            ->orderBy('id_participation', 'desc')
            ->paginate($this->paginationLimit);

        return EditionParticipationResource::collection($participations);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/{edition}/participations/{participation}",
     *     summary="Get participation details",
     *     tags={"🐧 Manager: Participations"},
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
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EditionParticipation")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function show(Edition $edition, EditionParticipation $participation)
    {
        // Ensure the participation belongs to the specified edition
        if ($participation->edition_id !== $edition->id_edition) {
            return $this->notFoundResponse('Participation does not belong to this edition');
        }

        $participation->load(['player', 'division']);

        return $this->resourceResponse(
            new EditionParticipationResource($participation),
            'Participation details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/editions/{edition}/participations",
     *     summary="Add player to edition",
     *     tags={"🐧 Manager: Participations"},
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
     *             @OA\Property(property="division_id", type="integer"),
     *             @OA\Property(property="is_minor", type="boolean", description="Is minor player"),
     *             @OA\Property(property="is_student", type="boolean", description="Is student"),
     *             @OA\Property(property="discount", type="integer", description="Discount amount"),
     *             @OA\Property(property="payment", type="integer", description="Payment amount"),
     *             @OA\Property(property="absence_reason", type="string", enum={"SICK", "FORGOT", "EXAMS", "RECONSIDERED"}, description="Absence reason"),
     *             @OA\Property(property="ban_reason", type="string", enum={"LEFT SUDDENLY", "AGGRESSIVE PLAYER", "LIBIDINOUS", ""}, description="Ban reason")
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
        $validated = $request->validate([
            'player_id' => 'required|exists:players,id_player',
            'division_id' => 'required|exists:divisions,id_division',
            'is_minor' => 'boolean',
            'is_student' => 'boolean',
            'discount' => 'nullable|integer|min:0',
            'payment' => 'nullable|integer|min:0',
            'absence_reason' => 'nullable|in:SICK,FORGOT,EXAMS,RECONSIDERED',
            'ban_reason' => 'nullable|in:LEFT SUDDENLY,AGGRESSIVE PLAYER,LIBIDINOUS,',
        ]);

        // Check if player is already participating
        $existingParticipation = EditionParticipation::where([
            'edition_id' => $edition->id_edition,
            'player_id' => $validated['player_id'],
        ])->first();

        if ($existingParticipation) {
            return $this->errorResponse('This player is already participating in this edition', 422);
        }

        $validated['edition_id'] = $edition->id_edition;

        // Set default values for optional fields
        $validated['is_minor'] = $validated['is_minor'] ?? false;
        $validated['is_student'] = $validated['is_student'] ?? false;
        $validated['discount'] = $validated['discount'] ?? 0;
        $validated['payment'] = $validated['payment'] ?? 0;

        $participation = EditionParticipation::create($validated);

        $participation->load(['player', 'division']);

        return $this->resourceResponse(
            new EditionParticipationResource($participation),
            'Participation created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/editions/{edition}/participations/{participation}",
     *     summary="Update participation details",
     *     tags={"🐧 Manager: Participations"},
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
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="player_id", type="integer", description="Player ID"),
     *             @OA\Property(property="division_id", type="integer", description="Division ID"),
     *             @OA\Property(property="is_minor", type="boolean", description="Is minor player"),
     *             @OA\Property(property="is_student", type="boolean", description="Is student"),
     *             @OA\Property(property="discount", type="integer", description="Discount amount"),
     *             @OA\Property(property="payment", type="integer", description="Payment amount"),
     *             @OA\Property(property="absence_reason", type="string", enum={"SICK", "FORGOT", "EXAMS", "RECONSIDERED"}, description="Absence reason"),
     *             @OA\Property(property="ban_reason", type="string", enum={"LEFT SUDDENLY", "AGGRESSIVE PLAYER", "LIBIDINOUS", ""}, description="Ban reason")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Participation updated successfully",
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
    public function update(Request $request, Edition $edition, EditionParticipation $participation)
    {
        // Ensure the participation belongs to the specified edition
        if ($participation->edition_id !== $edition->id_edition) {
            return $this->notFoundResponse('Participation does not belong to this edition');
        }

        $validated = $request->validate([
            'player_id' => 'sometimes|exists:players,id_player',
            'division_id' => 'sometimes|exists:divisions,id_division',
            'is_minor' => 'boolean',
            'is_student' => 'boolean',
            'discount' => 'nullable|integer|min:0',
            'payment' => 'nullable|integer|min:0',
            'absence_reason' => 'nullable|in:SICK,FORGOT,EXAMS,RECONSIDERED',
            'ban_reason' => 'nullable|in:LEFT SUDDENLY,AGGRESSIVE PLAYER,LIBIDINOUS,',
        ]);

        // Check if player is already participating (excluding current record)
        if (isset($validated['player_id']) && $validated['player_id'] !== $participation->player_id) {
            $existingParticipation = EditionParticipation::where([
                'edition_id' => $edition->id_edition,
                'player_id' => $validated['player_id'],
            ])->first();

            if ($existingParticipation) {
                return $this->errorResponse('This player is already participating in this edition', 422);
            }
        }

        $participation->update($validated);
        $participation->load(['player', 'division']);

        return $this->resourceResponse(
            new EditionParticipationResource($participation),
            'Participation updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/editions/{edition}/participations/{participation}",
     *     summary="Remove player from edition",
     *     tags={"🐧 Manager: Participations"},
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
        if ($participation->edition_id !== $edition->id_edition) {
            return $this->notFoundResponse('Participation does not belong to this edition');
        }

        $participation->delete();

        return $this->successResponse(null, 'Participation deleted successfully');
    }
}
