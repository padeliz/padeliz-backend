<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\MatchesTeamResource;
use App\Models\MatchesTeam;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Matches Teams",
 *     description="(v1) Match teams management and operations"
 * )
 */
class MatchesTeamController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/matches-teams",
     *     summary="Get paginated list of match teams",
     *     tags={"🐧 Manager: Matches Teams"},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         description="Filter parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="player_1", type="integer"),
     *             @OA\Property(property="player_2", type="integer")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="player_1", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="player_2", type="string", enum={"asc", "desc"}),
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
     *                         @OA\Items(ref="#/components/schemas/MatchesTeam")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = MatchesTeam::with(['player1', 'player2']);

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            MatchesTeam::$searchableFields,
            MatchesTeam::$filterableFields,
            MatchesTeam::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_team', 'desc');
        }

        $teams = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return MatchesTeamResource::collection($teams);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/matches-teams/{team}",
     *     summary="Get match team details",
     *     tags={"🐧 Manager: Matches Teams"},
     *     @OA\Parameter(
     *         name="team",
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
     *                     @OA\Property(property="data", ref="#/components/schemas/MatchesTeam")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function show(MatchesTeam $team)
    {
        $team->load(['player1', 'player2']);

        return $this->resourceResponse(
            new MatchesTeamResource($team),
            'Matches team details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/matches-teams",
     *     summary="Create a new match team",
     *     tags={"🐧 Manager: Matches Teams"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"player_1", "player_2"},
     *             @OA\Property(property="player_1", type="integer", description="Player 1 ID"),
     *             @OA\Property(property="player_2", type="integer", description="Player 2 ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Match team created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/MatchesTeam")
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'player_1' => 'required|integer|exists:players,id_player',
            'player_2' => 'required|integer|exists:players,id_player|different:player_1',
        ]);

        // Check if team already exists
        $existingTeam = MatchesTeam::where(function ($query) use ($validated) {
            $query->where('player_1', $validated['player_1'])
                ->where('player_2', $validated['player_2']);
        })->orWhere(function ($query) use ($validated) {
            $query->where('player_1', $validated['player_2'])
                ->where('player_2', $validated['player_1']);
        })->first();

        if ($existingTeam) {
            return $this->errorResponse(
                'This team already exists',
                422
            );
        }

        $validated['inserted_at'] = time();
        $validated['updated_at'] = time();

        $team = MatchesTeam::create($validated);
        $team->load(['player1', 'player2']);

        return $this->resourceResponse(
            new MatchesTeamResource($team),
            'Matches team created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/matches-teams/{team}",
     *     summary="Update match team details",
     *     tags={"🐧 Manager: Matches Teams"},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="player_1", type="integer", description="Player 1 ID"),
     *             @OA\Property(property="player_2", type="integer", description="Player 2 ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Match team updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/MatchesTeam")
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
    public function update(Request $request, MatchesTeam $team)
    {
        $validated = $request->validate([
            'player_1' => 'sometimes|integer|exists:players,id_player',
            'player_2' => 'sometimes|integer|exists:players,id_player',
        ]);

        // Ensure players are different
        if (isset($validated['player_1']) && isset($validated['player_2']) && $validated['player_1'] === $validated['player_2']) {
            return $this->errorResponse(
                'Players must be different',
                422
            );
        }

        // Check if team already exists with new players
        if (isset($validated['player_1']) || isset($validated['player_2'])) {
            $player1 = $validated['player_1'] ?? $team->player_1;
            $player2 = $validated['player_2'] ?? $team->player_2;

            $existingTeam = MatchesTeam::where('id_team', '!=', $team->id_team)
                ->where(function ($query) use ($player1, $player2) {
                    $query->where('player_1', $player1)
                        ->where('player_2', $player2);
                })->orWhere(function ($query) use ($player1, $player2) {
                    $query->where('player_1', $player2)
                        ->where('player_2', $player1);
                })->first();

            if ($existingTeam) {
                return $this->errorResponse(
                    'This team already exists',
                    422
                );
            }
        }

        $validated['updated_at'] = time();
        $team->update($validated);
        $team->load(['player1', 'player2']);

        return $this->resourceResponse(
            new MatchesTeamResource($team),
            'Matches team updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/matches-teams/{team}",
     *     summary="Delete a match team",
     *     tags={"🐧 Manager: Matches Teams"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Match team deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     */
    public function destroy(MatchesTeam $team)
    {
        return $this->deleteResourceWithResponse(
            $team,
            [],
            'match team',
            'Matches team deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/matches-teams/bulk",
     *     summary="Bulk delete match teams",
     *     tags={"🐧 Manager: Matches Teams"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"team_ids"},
     *             @OA\Property(
     *                 property="team_ids",
     *                 type="array",
     *                 description="Array of team IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All match teams deleted successfully",
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
     *         description="Some match teams failed to delete (Multi-Status)",
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
     *         description="No match teams provided for deletion",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'team_ids' => 'required|array|min:1',
            'team_ids.*' => 'integer',
        ]);

        return $this->deleteBulkResourcesWithResponse(
            $request->team_ids,
            MatchesTeam::class,
            [],
            'match team',
            'Match teams deleted successfully'
        );
    }
}
