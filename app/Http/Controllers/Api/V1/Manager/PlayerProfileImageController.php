<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Requests\Manager\ManagerPlayerProfileImageUploadRequest;
use App\Models\Player;
use App\Models\PlayerProfileImage;
use App\Services\PlayerProfileImage\PlayerProfileImagePrimaryService;
use App\Services\PlayerProfileImage\PlayerProfileImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Player Profile Images",
 *     description="(v1) Manager upload/delete of player profile images"
 * )
 */
class PlayerProfileImageController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function __construct(
        private readonly PlayerProfileImageUploadService $uploadService,
        private readonly PlayerProfileImagePrimaryService $primaryService,
    ) {
    }

    public function store(ManagerPlayerProfileImageUploadRequest $request, Player $player): JsonResponse
    {
        try {
            $this->uploadService->upload($player, $request->file('image'), [
                'uploaded_via' => PlayerProfileImage::UPLOADED_VIA_MANAGER,
                'is_pdf_primary' => false,
            ]);
        } catch (\Throwable $e) {
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
                ->map(static fn (PlayerProfileImage $img) => $img->toApiArray())
                ->values()
                ->all(),
        ], 'Image uploaded successfully', 201);
    }

    public function destroy(Player $player, PlayerProfileImage $playerProfileImage): JsonResponse
    {
        if ((int) $playerProfileImage->player_id !== (int) $player->id_player) {
            return $this->errorResponse('Profile image does not belong to this player.', 404);
        }

        $wasPrimary = $this->primaryService->wasResolvedPrimary($player, $playerProfileImage);
        $playerProfileImage->delete();

        if ($wasPrimary) {
            $player->load('profileImages');
            $this->primaryService->promoteSurvivorAfterDelete($player);
        }

        $player->load('profileImages');

        return $this->successResponse([
            'uploaded_profile_images' => $player->profileImages
                ->map(static fn (PlayerProfileImage $img) => $img->toApiArray())
                ->values()
                ->all(),
        ], 'Image deleted successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/players/{player}/profile-images/{playerProfileImage}/pdf-primary",
     *     summary="Set a profile image as the PDF source",
     *     tags={"🐧 Manager: Player Profile Images"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Updated images")
     * )
     */
    public function setPdfPrimary(Player $player, PlayerProfileImage $playerProfileImage): JsonResponse
    {
        if ((int) $playerProfileImage->player_id !== (int) $player->id_player) {
            return $this->errorResponse('Profile image does not belong to this player.', 404);
        }

        try {
            $this->primaryService->setPdfPrimary($player, $playerProfileImage);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        $player->load('profileImages');

        return $this->successResponse([
            'uploaded_profile_images' => $player->profileImages
                ->map(static fn (PlayerProfileImage $img) => $img->toApiArray())
                ->values()
                ->all(),
        ], 'PDF primary image updated');
    }
}
