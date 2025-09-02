<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Requests\PlayerFormRequest;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="🐥 App: Players",
 *     description="(v1) Player profile management and operations"
 * )
 */
class PlayerController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/app/players",
     *     summary="Create a new player",
     *     tags={"🐥 App: Players"},
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
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Player")
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
    public function store(PlayerFormRequest $request)
    {
        $validated = $request->validated();

        $player = $request->user();
        $player->update($validated);

        return $this->resourceResponse(
            new PlayerResource($player),
            'Player profile updated successfully',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/players/{player}",
     *     summary="Get player details",
     *     tags={"🐥 App: Players"},
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
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Player")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function show(Player $player)
    {
        $player->load(['participations.edition', 'participations.division']);

        return $this->resourceResponse(
            new PlayerResource($player),
            'Player details retrieved successfully'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/app/players/{player}",
     *     summary="Update player details",
     *     tags={"🐥 App: Players"},
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
     *             @OA\Property(property="being_student", type="integer", enum={0, 1}, description="Being student (use 0 or 1, not true/false)"),
     *             @OA\Property(property="playtomic_id", type="string", maxLength=10),
     *             @OA\Property(property="rankedin_id", type="string", maxLength=15),
     *             @OA\Property(property="instagram_username", type="string", maxLength=50),
     *             @OA\Property(property="facebook_username", type="string", maxLength=50)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Player")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized to update this player",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function update(PlayerFormRequest $request, Player $player)
    {
        // Only allow users to update their own profile
        if ($request->user()->id_player !== $player->id_player) {
            return $this->forbiddenResponse('You can only update your own profile');
        }

        $validatedData = $request->validated();
        $player->update($validatedData);

        return $this->resourceResponse(
            new PlayerResource($player),
            'Player profile updated successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/players/image",
     *     summary="Upload a profile image",
     *     tags={"🐥 App: Players"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="image",
     *                     type="file",
     *                     format="binary",
     *                     description="Image file (JPEG, PNG, GIF, WebP) - max 5MB"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Image uploaded successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="object",
     *                         @OA\Property(property="urls", type="array", @OA\Items(type="string"))
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error, too many images, or upload failure",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,gif,webp',
                'max:5120', // 5MB max
            ],
        ], [
            'image.required' => 'Please select an image to upload.',
            'image.file' => 'The uploaded file is not valid.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a JPEG, PNG, GIF, or WebP file.',
            'image.max' => 'The image size must not exceed 5MB.',
        ]);

        /** @var Player $player */
        $player = $request->user();
        $currentImages = $player->getUploadedFiles('images');

        if (count($currentImages) >= 3) {
            return $this->errorResponse(
                'Maximum number of profile images (3) reached. Delete an existing image first.',
                422
            );
        }

        try {
            $player->saveUploadedFile($request->file('image'), 'images');
        } catch (\Exception $e) {
            Log::error('PlayerController::uploadImage - Error uploading file', [
                'player_id' => $player->id_player,
                'error' => $e->getMessage(),
                'file_name' => $request->file('image')->getClientOriginalName(),
                'file_size' => $request->file('image')->getSize(),
                'file_mime' => $request->file('image')->getMimeType(),
            ]);

            return $this->errorResponse(
                'The image failed to upload. Please try again with a different image.',
                422
            );
        }

        return $this->successResponse([
            'urls' => $player->getUploadedUrls('images'),
        ], 'Image uploaded successfully', 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/app/players/image/{filename}",
     *     summary="Delete a profile image",
     *     tags={"🐥 App: Players"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image deleted successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="object",
     *                         @OA\Property(property="urls", type="array", @OA\Items(type="string"))
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Image not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function deleteImage(Request $request, string $filename)
    {
        /** @var Player $player */
        $player = $request->user();

        if (! $player->deleteUploadedFile($filename, 'images')) {
            return $this->notFoundResponse('Image not found');
        }

        return $this->successResponse([
            'urls' => $player->getUploadedUrls('images'),
        ], 'Image deleted successfully');
    }
}
