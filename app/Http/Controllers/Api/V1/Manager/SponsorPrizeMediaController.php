<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Requests\Manager\ManagerPrizeMediaUploadRequest;
use App\Http\Resources\SponsorPrizeResource;
use App\Models\SponsorPrize;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Sponsor Prize Media",
 *     description="(v1) Manager sponsor-prize media upload/delete"
 * )
 */
class SponsorPrizeMediaController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/manager/sponsor-prizes/{sponsorPrize}/media",
     *     summary="Upload or replace sponsor prize media",
     *     tags={"🐧 Manager: Sponsor Prize Media"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sponsorPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(@OA\Property(property="file", type="string", format="binary")))),
     *     @OA\Response(response=200, description="Uploaded")
     * )
     */
    public function store(ManagerPrizeMediaUploadRequest $request, SponsorPrize $sponsorPrize): JsonResponse
    {
        $disk = $sponsorPrize->storageDisk();
        $storedPath = null;

        try {
            if ($sponsorPrize->media_path) {
                Storage::disk($disk)->delete($sponsorPrize->media_path);
            }

            $storedPath = Storage::disk($disk)->putFile(
                'sponsor-prizes/' . $sponsorPrize->id_sponsor_prize . '/media',
                $request->file('file'),
                ['visibility' => 'public']
            );

            if ($storedPath === false || $storedPath === null) {
                throw new \RuntimeException('Could not store the uploaded file.');
            }

            $sponsorPrize->media_path = $storedPath;
            $sponsorPrize->save();
        } catch (\Throwable $e) {
            if ($storedPath !== null && $storedPath !== false) {
                Storage::disk($disk)->delete($storedPath);
            }
            Log::error('Manager SponsorPrizeMediaController::store failed', [
                'sponsor_prize_id' => $sponsorPrize->id_sponsor_prize,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The media failed to upload. Please try again with a different file.',
                422
            );
        }

        return $this->resourceResponse(
            new SponsorPrizeResource($sponsorPrize->fresh()),
            'Sponsor prize media uploaded successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/sponsor-prizes/{sponsorPrize}/media",
     *     summary="Delete sponsor prize media",
     *     tags={"🐧 Manager: Sponsor Prize Media"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sponsorPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function destroy(SponsorPrize $sponsorPrize): JsonResponse
    {
        $disk = $sponsorPrize->storageDisk();

        if ($sponsorPrize->media_path) {
            Storage::disk($disk)->delete($sponsorPrize->media_path);
            $sponsorPrize->media_path = null;
            $sponsorPrize->save();
        }

        return $this->resourceResponse(
            new SponsorPrizeResource($sponsorPrize->fresh()),
            'Sponsor prize media deleted successfully'
        );
    }
}
