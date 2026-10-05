<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventDivisionPosterResource;
use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\EventDivisionPoster;
use App\Services\Pdf\MissingMatchParticipationException;
use App\Services\Pdf\PosterPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Division Posters",
 *     description="(v1) Mode A MATCHES/RANKING poster PDF generation, downloads, and printed uploads"
 * )
 */
class EventDivisionPosterController extends Controller
{
    public function __construct(
        private readonly PosterPdfService $posterPdfService,
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/posters/matches",
     *     summary="Generate MATCHES poster PDF (Mode A)",
     *     tags={"🐧 Manager: PadelEvent Division Posters"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"court"},
     *             @OA\Property(property="court", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Generated poster JSON"),
     *     @OA\Response(response=409, description="PadelEvent is finalized"),
     *     @OA\Response(response=422, description="Missing schedule, missing match participation, or validation error")
     * )
     */
    public function matches(Request $request, EventDivision $eventDivision): JsonResponse
    {
        $eventDivision->loadMissing(['event.organizer', 'event.location.club', 'schedule']);
        if ($eventDivision->event?->finalized_at !== null) {
            return response()->json(['message' => 'PadelEvent is finalized.'], 409);
        }

        if ($eventDivision->schedule === null) {
            return response()->json(['message' => 'PadelEvent division has no schedule.'], 422);
        }

        $validated = $request->validate([
            'court' => [
                'required',
                'integer',
                Rule::exists('event_division_courts', 'id_event_division_court')
                    ->where('event_division_id', $eventDivision->id_event_division),
            ],
        ]);

        $court = EventDivisionCourt::query()->findOrFail((int) $validated['court']);

        try {
            $result = $this->posterPdfService->generateMatchesForEventDivision($eventDivision, $court);
        } catch (MissingMatchParticipationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Poster generated.',
            'data' => new EventDivisionPosterResource($result->poster),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/posters/ranking",
     *     summary="Generate RANKING poster PDF (Mode A)",
     *     tags={"🐧 Manager: PadelEvent Division Posters"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Generated poster JSON"),
     *     @OA\Response(response=409, description="PadelEvent is finalized"),
     *     @OA\Response(response=422, description="Missing schedule")
     * )
     */
    public function ranking(EventDivision $eventDivision): JsonResponse
    {
        $eventDivision->loadMissing(['event.organizer', 'event.location.club', 'schedule']);
        if ($eventDivision->event?->finalized_at !== null) {
            return response()->json(['message' => 'PadelEvent is finalized.'], 409);
        }

        if ($eventDivision->schedule === null) {
            return response()->json(['message' => 'PadelEvent division has no schedule.'], 422);
        }

        $result = $this->posterPdfService->generateRankingForEventDivision($eventDivision);

        return response()->json([
            'message' => 'Poster generated.',
            'data' => new EventDivisionPosterResource($result->poster),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/posters/matches/file",
     *     summary="Download stored MATCHES poster PDF",
     *     tags={"🐧 Manager: PadelEvent Division Posters"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="court", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="PDF stream", @OA\MediaType(mediaType="application/pdf")),
     *     @OA\Response(response=404, description="No generated file")
     * )
     */
    public function matchesFile(Request $request, EventDivision $eventDivision): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'court' => [
                'required',
                'integer',
                Rule::exists('event_division_courts', 'id_event_division_court')
                    ->where('event_division_id', $eventDivision->id_event_division),
            ],
        ]);

        $poster = EventDivisionPoster::query()
            ->where('event_division_id', $eventDivision->id_event_division)
            ->where('poster_kind', 'MATCHES')
            ->where('event_division_court_id', (int) $validated['court'])
            ->first();

        return $this->streamStoredFile($poster, 'generated_path', 'application/pdf');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/posters/ranking/file",
     *     summary="Download stored RANKING poster PDF",
     *     tags={"🐧 Manager: PadelEvent Division Posters"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="PDF stream", @OA\MediaType(mediaType="application/pdf")),
     *     @OA\Response(response=404, description="No generated file")
     * )
     */
    public function rankingFile(EventDivision $eventDivision): StreamedResponse|JsonResponse
    {
        $poster = EventDivisionPoster::query()
            ->where('event_division_id', $eventDivision->id_event_division)
            ->where('poster_kind', 'RANKING')
            ->whereNull('event_division_court_id')
            ->first();

        return $this->streamStoredFile($poster, 'generated_path', 'application/pdf');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/posters/{poster}/printed/file",
     *     summary="Download stored printed poster image",
     *     tags={"🐧 Manager: PadelEvent Division Posters"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Image stream"),
     *     @OA\Response(response=404, description="No printed file")
     * )
     */
    public function printedFile(EventDivision $eventDivision, EventDivisionPoster $poster): StreamedResponse|JsonResponse
    {
        if ((int) $poster->event_division_id !== (int) $eventDivision->id_event_division) {
            return response()->json(['message' => 'Poster does not belong to this edition division.'], 404);
        }

        return $this->streamStoredFile($poster, 'printed_path', 'application/octet-stream');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/posters",
     *     summary="List posters for an edition division",
     *     tags={"🐧 Manager: PadelEvent Division Posters"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Poster list")
     * )
     */
    public function index(EventDivision $eventDivision): JsonResponse
    {
        $posters = $eventDivision->posters()->orderBy('id_event_division_poster')->get();

        return response()->json([
            'data' => EventDivisionPosterResource::collection($posters),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-divisions/{eventDivision}/posters/{poster}/printed",
     *     summary="Upload printed poster photo",
     *     tags={"🐧 Manager: PadelEvent Division Posters"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"image"},
     *                 @OA\Property(property="image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated poster")
     * )
     */
    public function uploadPrinted(Request $request, EventDivision $eventDivision, EventDivisionPoster $poster): JsonResponse
    {
        if ((int) $poster->event_division_id !== (int) $eventDivision->id_event_division) {
            return response()->json(['message' => 'Poster does not belong to this edition division.'], 404);
        }

        $eventDivision->loadMissing('event');
        if ($eventDivision->event?->finalized_at !== null) {
            return response()->json(['message' => 'PadelEvent is finalized.'], 409);
        }

        $request->validate([
            'image' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $disk = $poster->storageDisk();
        if ($poster->printed_path) {
            Storage::disk($disk)->delete($poster->printed_path);
        }

        $stored = Storage::disk($disk)->putFile(
            'event-division-posters/' . $poster->id_event_division_poster . '/printed',
            $request->file('image'),
            ['visibility' => 'public']
        );

        $poster->printed_path = $stored;
        $poster->save();

        return response()->json([
            'data' => new EventDivisionPosterResource($poster->fresh()),
        ]);
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
