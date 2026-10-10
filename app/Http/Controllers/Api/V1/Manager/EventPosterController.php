<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventDivisionPosterResource;
use App\Models\EventDivisionPoster;
use App\Models\PadelEvent;
use App\Services\Pdf\EventMergedPosterPdfException;
use App\Services\Pdf\EventMergedPosterPdfService;
use App\Services\Pdf\PosterPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Posters",
 *     description="(v1) PadelEvent-level poster PDF generation and downloads"
 * )
 */
class EventPosterController extends Controller
{
    public function __construct(
        private readonly PosterPdfService $posterPdfService,
        private readonly EventMergedPosterPdfService $mergedPosterPdfService,
    ) {
    }

    public function index(PadelEvent $event): JsonResponse
    {
        $posters = EventDivisionPoster::query()
            ->where('event_id', $event->id_event)
            ->whereNull('event_division_id')
            ->orderBy('id_event_division_poster')
            ->get();

        return response()->json([
            'data' => EventDivisionPosterResource::collection($posters),
        ]);
    }

    public function mergedFile(PadelEvent $event): StreamedResponse|JsonResponse
    {
        try {
            $result = $this->mergedPosterPdfService->mergeForEdition($event);
        } catch (EventMergedPosterPdfException $e) {
            return response()->json(array_merge(['message' => $e->getMessage()], $e->payload()), $e->statusCode());
        }

        return response()->streamDownload(
            static function () use ($result): void {
                echo $result['bytes'];
            },
            $result['filename'],
            ['Content-Type' => 'application/pdf']
        );
    }

    public function attendance(PadelEvent $event): JsonResponse
    {
        if ($event->finalized_at !== null) {
            return response()->json(['message' => 'PadelEvent is finalized.'], 409);
        }

        $result = $this->posterPdfService->generateAttendanceForEdition($event);

        return response()->json([
            'message' => 'Poster generated.',
            'data' => new EventDivisionPosterResource($result->poster),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/events/{event}/posters/attendance/preview",
     *     operationId="managerEditionAttendancePosterPreview",
     *     tags={"🐧 Manager: PadelEvent Posters"},
     *     summary="Preview attendance sheet HTML",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="HTML preview"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function attendancePreview(PadelEvent $event): Response
    {
        $html = $this->posterPdfService->renderAttendanceHtmlForEdition($event);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function attendanceFile(PadelEvent $event): StreamedResponse|JsonResponse
    {
        $poster = EventDivisionPoster::query()
            ->where('event_id', $event->id_event)
            ->whereNull('event_division_id')
            ->where('poster_kind', 'ATTENDANCE')
            ->first();

        return $this->streamStoredFile($poster, 'generated_path', 'application/pdf');
    }

    private function streamStoredFile(?EventDivisionPoster $poster, string $pathAttr, string $contentType): StreamedResponse|JsonResponse
    {
        $relative = $poster?->{$pathAttr};
        if ($poster === null || $relative === null || $relative === '') {
            return response()->json(['message' => 'File is not available.'], 404);
        }

        $disk = $poster->storageDisk();
        if (! Storage::disk($disk)->exists($relative)) {
            return response()->json(['message' => 'File is not available.'], 404);
        }

        $filename = basename((string) $relative);
        $absolute = Storage::disk($disk)->path($relative);

        return response()->streamDownload(static function () use ($absolute): void {
            echo file_get_contents($absolute);
        }, $filename, [
            'Content-Type' => $contentType,
        ]);
    }
}
