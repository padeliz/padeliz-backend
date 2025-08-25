<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Models\Player;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Player: Players",
 *     description="(v1) Player profile management and operations"
 * )
 */
class PlayerController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/players/profile",
     *     summary="Create a new player",
     *     tags={"Player: Players"},
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
            'google_email' => 'required|email|unique:players,google_email,' . $request->user()->id_player . ',id_player',
            'phone' => 'required|string|unique:players,phone,' . $request->user()->id_player . ',id_player',
        ]);

        $player = $request->user();
        $player->update($validated);
        return response()->json($player, 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/players/profile/{player}",
     *     summary="Get player details",
     *     tags={"Player: Players"},
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
     *     path="/api/v1/players/{player}",
     *     summary="Update player details",
     *     tags={"Player: Players"},
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

    /**
     * @OA\Post(
     *     path="/api/v1/players/profile/image",
     *     summary="Upload a profile image",
     *     tags={"Player: Players"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="image", type="file", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Image uploaded successfully"),
     *     @OA\Response(response=422, description="Validation error or too many images")
     * )
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:2048' // 2MB max
        ]);

        $player = $request->user();
        $currentImages = $player->getUploadedFiles('images');

        if (count($currentImages) >= 3) {
            return response()->json([
                'message' => 'Maximum number of profile images (3) reached. Delete an existing image first.'
            ], 422);
        }

        $player->saveUploadedFile($request->file('image'), 'images');

        return response()->json([
            'message' => 'Image uploaded successfully',
            'urls' => $player->getUploadedUrls('images')
        ], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/players/profile/image/{filename}",
     *     summary="Delete a profile image",
     *     tags={"Player: Players"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Image deleted successfully"),
     *     @OA\Response(response=404, description="Image not found")
     * )
     */
    public function deleteImage(Request $request, string $filename)
    {
        /** @var Player $player  */
        $player = $request->user();

        if (!$player->deleteUploadedFile($filename, 'images')) {
            return response()->json([
                'message' => 'Image not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Image deleted successfully',
            'urls' => $player->getUploadedUrls('images')
        ]);
    }
}
