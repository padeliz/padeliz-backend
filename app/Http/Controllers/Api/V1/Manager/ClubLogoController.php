<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Requests\Manager\ManagerLogoUploadRequest;
use App\Http\Resources\ClubResource;
use App\Models\Club;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Club Logo",
 *     description="(v1) Manager club logo upload/delete"
 * )
 */
class ClubLogoController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/manager/clubs/{club}/logo",
     *     summary="Upload or replace the club logo",
     *     tags={"🐧 Manager: Club Logo"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="club", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(@OA\Property(property="file", type="string", format="binary")))),
     *     @OA\Response(response=200, description="Uploaded"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(ManagerLogoUploadRequest $request, Club $club): JsonResponse
    {
        $disk = $club->storageDisk();
        $storedPath = null;

        try {
            $previousPath = $club->logo_path;

            $storedPath = Storage::disk($disk)->putFile(
                'clubs/' . $club->id_club . '/logo',
                $request->file('file'),
                ['visibility' => 'public']
            );

            if ($storedPath === false || $storedPath === null) {
                throw new \RuntimeException('Could not store the uploaded file.');
            }

            $club->logo_path = $storedPath;
            $club->save();

            if ($previousPath !== null && $previousPath !== '') {
                Storage::disk($disk)->delete($previousPath);
            }
        } catch (\Throwable $e) {
            if ($storedPath !== null && $storedPath !== false) {
                Storage::disk($disk)->delete($storedPath);
            }
            Log::error('Manager ClubLogoController::store failed', [
                'club_id' => $club->id_club,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The logo failed to upload. Please try again with a different file.',
                422
            );
        }

        return $this->resourceResponse(
            new ClubResource($club->fresh()),
            'Club logo uploaded successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/clubs/{club}/logo",
     *     summary="Delete the club logo",
     *     tags={"🐧 Manager: Club Logo"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="club", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function destroy(Club $club): JsonResponse
    {
        if ($club->logo_path !== null && $club->logo_path !== '') {
            Storage::disk($club->storageDisk())->delete($club->logo_path);
            $club->logo_path = null;
            $club->save();
        }

        return $this->resourceResponse(
            new ClubResource($club->fresh()),
            'Club logo deleted successfully'
        );
    }
}
