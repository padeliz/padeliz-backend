<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Requests\App\PlayerProfileImageUploadRequest;
use App\Http\Requests\PlayerFormRequest;
use App\Http\Resources\AppUserResource;
use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserPhone;
use App\Models\PlayerProfileImage;
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
     *             required={"first_name", "email", "phone"},
     *             @OA\Property(property="first_name", type="string", maxLength=50),
     *             @OA\Property(property="middle_name", type="string", maxLength=50, nullable=true),
     *             @OA\Property(property="last_name", type="string", maxLength=50, nullable=true),
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
     *                     @OA\Property(property="data", ref="#/components/schemas/User")
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
        $email = $validated['email'] ?? null;
        $phone = $validated['phone'] ?? null;
        unset($validated['email'], $validated['phone']);

        $player = $request->user();
        $player->update($validated);
        $this->syncPlayerContacts($player, $email, $phone);
        $player->load(['emails', 'phones']);

        return $this->resourceResponse(
            new AppUserResource($player),
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
     *                     @OA\Property(property="data", ref="#/components/schemas/User")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function show(User $player)
    {
        $player->load(['participations.event', 'participations.eventDivision']);

        return $this->resourceResponse(
            new AppUserResource($player),
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
     *             @OA\Property(property="phone", type="string", maxLength=255),
     *             @OA\Property(property="first_name", type="string", maxLength=50),
     *             @OA\Property(property="middle_name", type="string", maxLength=50),
     *             @OA\Property(property="last_name", type="string", maxLength=50)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/User")
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
    public function update(PlayerFormRequest $request, User $player)
    {
        // Only allow users to update their own profile
        if ($request->user()->id_user !== $player->id_user) {
            return $this->forbiddenResponse('You can only update your own profile');
        }

        $validatedData = $request->validated();
        $email = $validatedData['email'] ?? null;
        $phone = $validatedData['phone'] ?? null;
        unset($validatedData['email'], $validatedData['phone']);

        $player->update($validatedData);
        $this->syncPlayerContacts($player, $email, $phone);
        $player->load(['emails', 'phones']);

        return $this->resourceResponse(
            new AppUserResource($player),
            'Player profile updated successfully'
        );
    }

    private function syncPlayerContacts(User $player, ?string $email, ?string $phone): void
    {
        if ($email !== null && $email !== '') {
            $playerEmail = $player->emails()->where('is_primary', true)->first()
                ?? $player->emails()->first();

            if ($playerEmail) {
                $playerEmail->update(['email' => $email, 'is_primary' => true]);
            } else {
                UserEmail::create([
                    'user_id' => $player->id_user,
                    'email' => $email,
                    'purposes' => ['login', 'contact'],
                    'is_primary' => true,
                ]);
            }
        }

        if ($phone !== null && $phone !== '') {
            $playerPhone = $player->phones()->where('is_primary', true)->first()
                ?? $player->phones()->first();

            if ($playerPhone) {
                $playerPhone->update(['phone' => $phone, 'is_primary' => true]);
            } else {
                UserPhone::create([
                    'user_id' => $player->id_user,
                    'phone' => $phone,
                    'purposes' => ['contact'],
                    'is_primary' => true,
                ]);
            }
        }
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
     *                         @OA\Property(
     *                             property="uploaded_profile_images",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer"),
     *                                 @OA\Property(property="url", type="string")
     *                             )
     *                         )
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
    public function uploadImage(PlayerProfileImageUploadRequest $request)
    {
        /** @var User $player */
        $player = $request->user();

        try {
            app(\App\Services\PlayerProfileImage\PlayerProfileImageUploadService::class)
                ->upload($player, $request->file('image'), [
                    'uploaded_via' => PlayerProfileImage::UPLOADED_VIA_APP,
                    'is_pdf_primary' => false,
                ]);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Maximum number of profile images')) {
                return $this->errorResponse($e->getMessage(), 422);
            }

            Log::error('PlayerController::uploadImage - Error uploading file', [
                'player_user_id' => $player->id_user,
                'error' => $e->getMessage(),
                'file_name' => $request->file('image')->getClientOriginalName(),
                'file_size' => $request->file('image')->getSize(),
                'file_mime' => $request->file('image')->getMimeType(),
            ]);

            return $this->errorResponse(
                'The image failed to upload. Please try again with a different image.',
                422
            );
        } catch (\Throwable $e) {
            Log::error('PlayerController::uploadImage - Error uploading file', [
                'player_user_id' => $player->id_user,
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

        $player->load('profileImages');

        return $this->successResponse([
            'uploaded_profile_images' => $player->profileImages
                ->map(static fn (PlayerProfileImage $img) => $img->toApiArray())
                ->values()
                ->all(),
        ], 'Image uploaded successfully', 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/app/players/image/{playerProfileImage}",
     *     summary="Delete a profile image",
     *     tags={"🐥 App: Players"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="playerProfileImage",
     *         in="path",
     *         required=true,
     *         description="player_profile_images.id",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image deleted successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="object",
     *                         @OA\Property(
     *                             property="uploaded_profile_images",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer"),
     *                                 @OA\Property(property="url", type="string")
     *                             )
     *                         )
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not allowed to delete another player's image",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Image not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function deleteImage(Request $request, PlayerProfileImage $playerProfileImage)
    {
        /** @var User $player */
        $player = $request->user();

        if ($player->id_user !== $playerProfileImage->player_user_id) {
            return $this->forbiddenResponse('You can only delete your own profile images.');
        }

        $primaryService = app(\App\Services\PlayerProfileImage\PlayerProfileImagePrimaryService::class);
        $wasPrimary = $primaryService->wasResolvedPrimary($player, $playerProfileImage);
        $playerProfileImage->delete();

        if ($wasPrimary) {
            $player->load('profileImages');
            $primaryService->promoteSurvivorAfterDelete($player);
        }

        $player->load('profileImages');

        return $this->successResponse([
            'uploaded_profile_images' => $player->profileImages
                ->map(static fn (PlayerProfileImage $img) => $img->toApiArray())
                ->values()
                ->all(),
        ], 'Image deleted successfully');
    }
}
