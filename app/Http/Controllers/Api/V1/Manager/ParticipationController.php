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
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term for absence_reason, ban_reason fields",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         description="Filter parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="division_id", type="integer"),
     *             @OA\Property(property="is_minor", type="integer", enum={0, 1}, description="Is minor player (use 0 or 1, not true/false)"),
     *             @OA\Property(property="is_student", type="integer", enum={0, 1}, description="Is student (use 0 or 1, not true/false)"),
     *             @OA\Property(property="discount", type="number"),
     *             @OA\Property(property="payment", type="number")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="player_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="division_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="is_minor", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="is_student", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="discount", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="payment", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"})
     *         )
     *     ),
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
    public function index(Request $request, Edition $edition)
    {
        $query = $edition->participations()->with(['player', 'division'])->getQuery();

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            EditionParticipation::$searchableFields,
            EditionParticipation::$filterableFields,
            EditionParticipation::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_participation', 'desc');
        }

        $participations = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

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
     *     summary="Update participation details (complete replacement)",
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
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
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
            'player_id' => 'required|exists:players,id_player',
            'division_id' => 'required|exists:divisions,id_division',
            'is_minor' => 'required|boolean',
            'is_student' => 'required|boolean',
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
     * @OA\Patch(
     *     path="/api/v1/manager/editions/{edition}/participations/{participation}",
     *     summary="Partially update participation details",
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
     *         description="Participation partially updated successfully",
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
    public function patch(Request $request, Edition $edition, EditionParticipation $participation)
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
            'Participation partially updated successfully'
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

        return $this->deleteResourceWithResponse(
            $participation,
            [],
            'participation',
            'Participation deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/editions/{edition}/participations/bulk",
     *     summary="Bulk remove players from edition",
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
     *             required={"participation_ids"},
     *             @OA\Property(
     *                 property="participation_ids",
     *                 type="array",
     *                 description="Array of participation IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All participations deleted successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="successful", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="failed", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="total_requested", type="integer"),
     *                         @OA\Property(property="total_successful", type="integer"),
     *                         @OA\Property(property="total_failed", type="integer")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=207,
     *         description="Some participations failed to delete (Multi-Status)",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         @OA\Property(property="successful", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="failed", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="total_requested", type="integer"),
     *                         @OA\Property(property="total_successful", type="integer"),
     *                         @OA\Property(property="total_failed", type="integer")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No participations provided for deletion",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function bulkDestroy(Request $request, Edition $edition)
    {
        $request->validate([
            'participation_ids' => 'required|array|min:1',
            'participation_ids.*' => 'integer',
        ]);

        $results = [
            'successful' => [],
            'failed' => [],
            'total_requested' => count($request->participation_ids),
            'total_successful' => 0,
            'total_failed' => 0,
        ];

        foreach ($request->participation_ids as $participationId) {
            try {
                $participation = EditionParticipation::find($participationId);

                if (! $participation) {
                    $results['failed'][] = [
                        'id' => $participationId,
                        'reason' => 'Resource not found',
                    ];
                    $results['total_failed']++;

                    continue;
                }

                // Ensure the participation belongs to the specified edition
                if ($participation->edition_id !== $edition->id_edition) {
                    $results['failed'][] = [
                        'id' => $participationId,
                        'reason' => 'Participation does not belong to this edition',
                    ];
                    $results['total_failed']++;

                    continue;
                }

                $participation->delete();

                $results['successful'][] = [
                    'id' => $participationId,
                    'name' => $participation->player->name ?? $participationId,
                ];
                $results['total_successful']++;

            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $participationId,
                    'reason' => 'Unexpected error: ' . $e->getMessage(),
                ];
                $results['total_failed']++;
            }
        }

        // Determine response based on results
        if ($results['total_failed'] === 0) {
            return $this->successResponse(
                $results,
                'Participations deleted successfully'
            );
        } elseif ($results['total_successful'] === 0) {
            return $this->errorResponse(
                'All participations failed to delete',
                409,
                $results
            );
        } else {
            return $this->errorResponse(
                'Some participations failed to delete',
                207, // Multi-Status
                $results
            );
        }
    }
}
