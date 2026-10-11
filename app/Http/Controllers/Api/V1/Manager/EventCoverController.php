<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Requests\Manager\ManagerLogoUploadRequest;
use App\Http\Resources\EventResource;
use App\Models\EventCoverImage;
use App\Models\PadelEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Cover",
 *     description="(v1) Manager edition cover image upload/delete (max 2)"
 * )
 */
class EventCoverController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/manager/events/{event}/cover-images",
     *     summary="Upload an edition cover image",
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
        $existingCount = $event->coverImages()->count();
        if ($existingCount >= EventCoverImage::MAX_PER_EVENT) {
            throw ValidationException::withMessages([
                'file' => [sprintf('An edition may have at most %d cover images.', EventCoverImage::MAX_PER_EVENT)],
            ]);
        }

        $disk = $event->storageDisk();
        $storedPath = null;

        try {
            $file = $request->file('file');
            $storedPath = Storage::disk($disk)->putFile(
                'events/' . $event->id_event . '/cover',
                $file,
                ['visibility' => 'public']
            );

            if ($storedPath === false || $storedPath === null) {
                throw new \RuntimeException('Could not store the uploaded file.');
            }

            $absolute = Storage::disk($disk)->path($storedPath);
            $info = @getimagesize($absolute);
            if ($info === false || ($info[0] ?? 0) <= 0 || ($info[1] ?? 0) <= 0) {
                throw new \RuntimeException('Could not read image dimensions.');
            }

            $nextSort = (int) ($event->coverImages()->max('sort_order') ?? -1) + 1;

            EventCoverImage::query()->create([
                'event_id' => $event->id_event,
                'cover_path' => $storedPath,
                'aspect_ratio' => ((float) $info[0]) / ((float) $info[1]),
                'sort_order' => $nextSort,
            ]);
        } catch (ValidationException $e) {
            $this->cleanupStoredCover($disk, $storedPath);
            throw $e;
        } catch (QueryException $e) {
            $this->cleanupStoredCover($disk, $storedPath);
            Log::error('Manager EventCoverController::store failed', [
                'event_id' => $event->id_event,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('PadelEvent cover could not be saved.', 500);
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
            new EventResource($event->fresh()->load('coverImages')),
            'PadelEvent cover uploaded successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/events/{event}/cover-images/{coverImage}",
     *     summary="Delete an edition cover image",
     *     tags={"🐧 Manager: PadelEvent Cover"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="coverImage", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=422, description="Cannot delete the last cover")
     * )
     */
    public function destroy(PadelEvent $event, EventCoverImage $coverImage): JsonResponse
    {
        if ((int) $coverImage->event_id !== (int) $event->id_event) {
            return $this->errorResponse('Cover image not found for this edition.', 404);
        }

        if ($event->coverImages()->count() <= 1) {
            throw ValidationException::withMessages([
                'cover_images' => ['An edition must keep at least one cover image.'],
            ]);
        }

        $coverImage->delete();

        return $this->resourceResponse(
            new EventResource($event->fresh()->load('coverImages')),
            'PadelEvent cover deleted successfully'
        );
    }

    private function cleanupStoredCover(string $disk, mixed $storedPath): void
    {
        if ($storedPath !== null && $storedPath !== false) {
            Storage::disk($disk)->delete($storedPath);
        }
    }
}
