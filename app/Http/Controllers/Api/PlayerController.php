<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'email' => 'required|email|unique:players,email',
            'phone' => 'required|string|unique:players,phone',
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
     * @OA\Patch(
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
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255),
     *             @OA\Property(property="phone", type="string", maxLength=255),
     *             @OA\Property(property="skill_division", type="number", format="float", maximum=9.9),
     *             @OA\Property(property="first_name", type="string", maxLength=50),
     *             @OA\Property(property="middle_name", type="string", maxLength=50),
     *             @OA\Property(property="last_name", type="string", maxLength=50),
     *             @OA\Property(property="year_of_birth", type="integer", minimum=1900, maximum=9999),
     *             @OA\Property(property="being_student", type="boolean"),
     *             @OA\Property(property="playtomic_id", type="string", maxLength=10),
     *             @OA\Property(property="rankedin_id", type="string", maxLength=15),
     *             @OA\Property(property="instagram_username", type="string", maxLength=50),
     *             @OA\Property(property="facebook_username", type="string", maxLength=50)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Player")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized to update this player"
     *     )
     * )
     */
    public function update(Request $request, Player $player)
    {
        // Only allow users to update their own profile
        if ($request->user()->id_player !== $player->id_player) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:255',
            'skill_division' => 'sometimes|numeric|min:0|max:9.9',
            'first_name' => 'sometimes|string|max:50|nullable',
            'middle_name' => 'sometimes|string|max:50|nullable',
            'last_name' => 'sometimes|string|max:50|nullable',
            'year_of_birth' => 'sometimes|integer|min:1900|max:9999|nullable',
            'being_student' => 'sometimes|boolean',
            'playtomic_id' => 'sometimes|string|max:10|nullable',
            'rankedin_id' => 'sometimes|string|max:15|nullable',
            'instagram_username' => 'sometimes|string|max:50|nullable',
            'facebook_username' => 'sometimes|string|max:50|nullable',
        ]);

        $player->update($validatedData);

        return response()->json($player);
    }
}
