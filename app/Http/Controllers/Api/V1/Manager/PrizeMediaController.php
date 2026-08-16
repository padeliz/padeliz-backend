<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Requests\Manager\ManagerPrizeMediaUploadRequest;
use App\Http\Resources\PrizeResource;
use App\Models\Prize;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Prize Media",
 *     description="(v1) Manager prize media upload/delete"
 * )
 */
class PrizeMediaController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/manager/prizes/{prize}/media",
     *     summary="Upload or replace prize media",
     *     tags={"🐧 Manager: Prize Media"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="prize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(@OA\Property(property="file", type="string", format="binary")))),
     *     @OA\Response(response=200, description="Uploaded")
     * )
     */
    public function store(ManagerPrizeMediaUploadRequest $request, Prize $prize): JsonResponse
    {
        $disk = $prize->storageDisk();
        $storedPath = null;

        try {
            if ($prize->media_path) {
                Storage::disk($disk)->delete($prize->media_path);
            }

            $storedPath = Storage::disk($disk)->putFile(
                'prizes/' . $prize->id_prize . '/media',
                $request->file('file'),
                ['visibility' => 'public']
            );

            if ($storedPath === false || $storedPath === null) {
                throw new \RuntimeException('Could not store the uploaded file.');
            }

            $prize->media_path = $storedPath;
            $prize->save();
        } catch (\Throwable $e) {
            if ($storedPath !== null && $storedPath !== false) {
                Storage::disk($disk)->delete($storedPath);
            }
            Log::error('Manager PrizeMediaController::store failed', [
                'prize_id' => $prize->id_prize,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The media failed to upload. Please try again with a different file.',
                422
            );
        }

        return $this->resourceResponse(
            new PrizeResource($prize->fresh()),
            'Prize media uploaded successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/prizes/{prize}/media",
     *     summary="Delete prize media",
     *     tags={"🐧 Manager: Prize Media"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="prize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function destroy(Prize $prize): JsonResponse
    {
        $disk = $prize->storageDisk();

        if ($prize->media_path) {
            Storage::disk($disk)->delete($prize->media_path);
            $prize->media_path = null;
            $prize->save();
        }

        return $this->resourceResponse(
            new PrizeResource($prize->fresh()),
            'Prize media deleted successfully'
        );
    }
}
