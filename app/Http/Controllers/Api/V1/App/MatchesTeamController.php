<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Controller;
use App\Models\MatchesTeam;
use App\Models\Player;
use App\Http\Resources\MatchesTeamResource;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐥 App: MatchesTeam",
 *     description="(v1) Player match teams and partnerships"
 * )
 */
class MatchesTeamController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/app/matches/teams",
     *     summary="Get paginated list of matches teams",
     *     tags={"🐥 App: MatchesTeam"},
     *     security={{"bearerAuth":{}}},
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
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index()
    {
        $teams = MatchesTeam::with(['player1', 'player2'])
            ->orderBy('id_team', 'desc')
            ->paginate($this->paginationLimit);

        return MatchesTeamResource::collection($teams);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/matches/teams",
     *     summary="Create a new team",
     *     tags={"🐥 App: MatchesTeam"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"player_1", "player_2"},
     *             @OA\Property(property="player_1", type="integer"),
     *             @OA\Property(property="player_2", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Team created successfully",
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
     *         description="Team already exists",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'player_1' => 'required|exists:players,id_player',
            'player_2' => 'required|exists:players,id_player|different:player_1',
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
            return $this->errorResponse('Team already exists', 422);
        }

        $team = new MatchesTeam();
        $team->player_1 = $validated['player_1'];
        $team->player_2 = $validated['player_2'];
        $team->inserted_at = now()->timestamp;
        $team->updated_at = now()->timestamp;
        $team->save();

        $team->load(['player1', 'player2']);
        return $this->resourceResponse(
            new MatchesTeamResource($team),
            'Team created successfully',
            201
        );
    }
}
