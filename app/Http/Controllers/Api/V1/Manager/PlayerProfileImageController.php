<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Requests\Manager\ManagerPlayerProfileImageUploadRequest;
use App\Models\Player;
use App\Models\PlayerProfileImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Player Profile Images",
 *     description="(v1) Manager upload/delete of player profile images"
 * )
 */
class PlayerProfileImageController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/manager/players/{player}/profile-images",
     *     summary="Upload a profile image for a player",
     *     tags={"🐧 Manager: Player Profile Images"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="player", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(@OA\Property(property="image", type="string", format="binary"))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Uploaded"),
     *     @OA\Response(response=422, description="Validation or max files")
     * )
     */
    public function store(ManagerPlayerProfileImageUploadRequest $request, Player $player): JsonResponse
    {
        if ($player->profileImages()->count() >= PlayerProfileImage::MAX_PER_PLAYER) {
            return $this->errorResponse(
                'Maximum number of profile images (3) reached. Delete an existing image first.',
                422
            );
        }

        $disk = config('filesystems.user_uploads_disk', 'public');
        $storedPath = null;

        try {
            DB::beginTransaction();

            $image = PlayerProfileImage::create([
                'player_id' => $player->id_player,
                'profile_path' => '',
                'sort_order' => $player->profileImages()->count(),
            ]);

            $storedPath = Storage::disk($disk)->putFile(
                'player-profile-images/' . $image->getKey() . '/profile',
                $request->file('image'),
                ['visibility' => 'public']
            );

            if ($storedPath === false || $storedPath === null) {
                throw new \RuntimeException('Could not store the uploaded file.');
            }

            $image->update(['profile_path' => $storedPath]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($storedPath !== null && $storedPath !== false) {
                Storage::disk($disk)->delete($storedPath);
            }
            Log::error('Manager PlayerProfileImageController::store failed', [
                'player_id' => $player->id_player,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The image failed to upload. Please try again with a different image.',
                422
            );
        }

        $player->load('profileImages');

        return $this->successResponse([
            'uploaded_profile_images' => $player->profileImages
                ->map(static fn (PlayerProfileImage $img) => [
                    'id' => $img->id,
                    'url' => $img->url,
                ])
                ->values()
                ->all(),
        ], 'Image uploaded successfully', 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/players/{player}/profile-images/{playerProfileImage}",
     *     summary="Delete a player profile image",
     *     tags={"🐧 Manager: Player Profile Images"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="player", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="playerProfileImage", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(Player $player, PlayerProfileImage $playerProfileImage): JsonResponse
    {
        if ((int) $playerProfileImage->player_id !== (int) $player->id_player) {
            return $this->errorResponse('Profile image does not belong to this player.', 404);
        }

        $playerProfileImage->delete();
        $player->load('profileImages');

        return $this->successResponse([
            'uploaded_profile_images' => $player->profileImages
                ->map(static fn (PlayerProfileImage $img) => [
                    'id' => $img->id,
                    'url' => $img->url,
                ])
                ->values()
                ->all(),
        ], 'Image deleted successfully');
    }
}
