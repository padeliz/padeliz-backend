<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Requests\Manager\ManagerLogoUploadRequest;
use App\Http\Resources\EventResource;
use App\Models\PadelEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Cover",
 *     description="(v1) Manager edition cover upload/replace"
 * )
 */
class EventCoverController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/manager/events/{event}/cover",
     *     summary="Upload or replace the edition cover",
     *     tags={"🐧 Manager: PadelEvent Cover"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(@OA\Property(property="file", type="string", format="binary")))),
     *     @OA\Response(response=200, description="Uploaded"),
     *     @OA\Response(response=422, description="Validation or storage failure"),
     *     @OA\Response(response=500, description="Server error while saving cover")
     * )
     */
    public function store(ManagerLogoUploadRequest $request, PadelEvent $event): JsonResponse
    {
        $disk = $event->storageDisk();
        $storedPath = null;

        try {
            $previousPath = $event->cover_path;

            $storedPath = Storage::disk($disk)->putFile(
                'events/' . $event->id_event . '/cover',
                $request->file('file'),
                ['visibility' => 'public']
            );

            if ($storedPath === false || $storedPath === null) {
                throw new \RuntimeException('Could not store the uploaded file.');
            }

            $event->cover_path = $storedPath;
            $event->save();

            if ($previousPath !== null && $previousPath !== '') {
                Storage::disk($disk)->delete($previousPath);
            }
        } catch (QueryException $e) {
            $this->cleanupStoredCover($disk, $storedPath);
            Log::error('Manager EventCoverController::store failed', [
                'event_id' => $event->id_event,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'PadelEvent cover could not be saved.',
                500
            );
        } catch (\Throwable $e) {
            $this->cleanupStoredCover($disk, $storedPath);
            Log::error('Manager EventCoverController::store failed', [
                'event_id' => $event->id_event,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The cover failed to upload. Please try again with a different file.',
                422
            );
        }

        return $this->resourceResponse(
            new EventResource($event->fresh()),
            'PadelEvent cover uploaded successfully'
        );
    }

    private function cleanupStoredCover(string $disk, mixed $storedPath): void
    {
        if ($storedPath !== null && $storedPath !== false) {
            Storage::disk($disk)->delete($storedPath);
        }
    }
}
