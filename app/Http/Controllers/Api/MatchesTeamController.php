<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchesTeam;
use App\Models\Player;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Matches Teams",
 *     description="API Endpoints for matches teams"
 * )
 */
class MatchesTeamController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/matches/teams",
     *     summary="Get paginated list of matches teams",
     *     tags={"Matches Teams"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/PaginatedResponse"),
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
        return response()->json(
            MatchesTeam::with(['player1', 'player2'])
            ->orderBy('id_matches_team', 'desc')
            ->paginate($this->paginationLimit)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/matches/teams",
     *     summary="Create a new team",
     *     tags={"Matches Teams"},
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
     *         @OA\JsonContent(ref="#/components/schemas/MatchesTeam")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Team already exists"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'player_1' => 'required|exists:pm_players,id_player',
            'player_2' => 'required|exists:pm_players,id_player|different:player_1',
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
            return response()->json(['message' => 'Team already exists'], 422);
        }

        $team = MatchesTeam::create($validated);
        return response()->json($team->load(['player1', 'player2']), 201);
    }
}
