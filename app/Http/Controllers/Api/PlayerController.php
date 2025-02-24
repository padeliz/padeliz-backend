<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Player;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Players",
 *     description="API Endpoints for players"
 * )
 */
class PlayerController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/players",
     *     summary="Create a new player",
     *     tags={"Players"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "phone"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="phone", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Player created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Player")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:pm_players,email',
            'phone' => 'required|string|unique:pm_players,phone',
        ]);

        $player = Player::create($validated);
        return response()->json($player, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/players/{player}",
     *     summary="Get player details",
     *     tags={"Players"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Player")
     *     )
     * )
     */
    public function show(Player $player)
    {
        return response()->json($player->load(['participations.edition', 'participations.division']));
    }

    /**
     * @OA\Put(
     *     path="/api/players/{player}",
     *     summary="Update player details",
     *     tags={"Players"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="phone", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Player")
     *     )
     * )
     */
    public function update(Request $request, Player $player)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'email' => 'email|unique:pm_players,email,' . $player->id_player . ',id_player',
            'phone' => 'string|unique:pm_players,phone,' . $player->id_player . ',id_player',
        ]);

        $player->update($validated);
        return response()->json($player);
    }
}
