<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\MatchesTeam;
use App\Http\Resources\MatchesTeamResource;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Manager: Matches Teams",
 *     description="(v1) Match teams management and operations"
 * )
 */
class MatchesTeamController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/matches-teams",
     *     summary="Get paginated list of match teams",
     *     tags={"Manager: Matches Teams"},
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
     *                         @OA\Items(ref="#/components/schemas/MatchesTeam")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index()
    {
        $teams = MatchesTeam::with(['player1', 'player2'])
            ->orderBy('inserted_at', 'desc')
            ->paginate($this->paginationLimit);

        return MatchesTeamResource::collection($teams);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/matches-teams/{team}",
     *     summary="Get match team details",
     *     tags={"Manager: Matches Teams"},
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
     *     tags={"Manager: Matches Teams"},
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
     *     tags={"Manager: Matches Teams"},
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
     *     tags={"Manager: Matches Teams"},
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
        $team->delete();

        return $this->successResponse(null, 'Matches team deleted successfully');
    }
}

