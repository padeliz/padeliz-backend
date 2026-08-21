<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Requests\Manager\ManagerLogoUploadRequest;
use App\Http\Resources\OrganizerResource;
use App\Models\Organizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Organizer Logo",
 *     description="(v1) Manager organizer logo upload/delete"
 * )
 */
class OrganizerLogoController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/manager/organizers/{organizer}/logo",
     *     summary="Upload or replace the organizer logo",
     *     tags={"🐧 Manager: Organizer Logo"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="organizer", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(@OA\Property(property="file", type="string", format="binary")))),
     *     @OA\Response(response=200, description="Uploaded"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(ManagerLogoUploadRequest $request, Organizer $organizer): JsonResponse
    {
        $disk = $organizer->storageDisk();
        $storedPath = null;

        try {
            $previousPath = $organizer->logo_path;

            $storedPath = Storage::disk($disk)->putFile(
                'organizers/' . $organizer->id_organizer . '/logo',
                $request->file('file'),
                ['visibility' => 'public']
            );

            if ($storedPath === false || $storedPath === null) {
                throw new \RuntimeException('Could not store the uploaded file.');
            }

            $organizer->logo_path = $storedPath;
            $organizer->save();

            if ($previousPath !== null && $previousPath !== '') {
                Storage::disk($disk)->delete($previousPath);
            }
        } catch (\Throwable $e) {
            if ($storedPath !== null && $storedPath !== false) {
                Storage::disk($disk)->delete($storedPath);
            }
            Log::error('Manager OrganizerLogoController::store failed', [
                'organizer_id' => $organizer->id_organizer,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The logo failed to upload. Please try again with a different file.',
                422
            );
        }

        return $this->resourceResponse(
            new OrganizerResource($organizer->fresh()),
            'Organizer logo uploaded successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/organizers/{organizer}/logo",
     *     summary="Delete the organizer logo",
     *     tags={"🐧 Manager: Organizer Logo"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="organizer", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function destroy(Organizer $organizer): JsonResponse
    {
        if ($organizer->logo_path !== null && $organizer->logo_path !== '') {
            Storage::disk($organizer->storageDisk())->delete($organizer->logo_path);
            $organizer->logo_path = null;
            $organizer->save();
        }

        return $this->resourceResponse(
            new OrganizerResource($organizer->fresh()),
            'Organizer logo deleted successfully'
        );
    }
}
