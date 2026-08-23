<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\EditionDivisionPosterResource;
use App\Models\Edition;
use App\Models\EditionDivisionPoster;
use App\Services\Pdf\EditionMergedPosterPdfException;
use App\Services\Pdf\EditionMergedPosterPdfService;
use App\Services\Pdf\PosterPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Edition Posters",
 *     description="(v1) Edition-level poster PDF generation and downloads"
 * )
 */
class EditionPosterController extends Controller
{
    public function __construct(
        private readonly PosterPdfService $posterPdfService,
        private readonly EditionMergedPosterPdfService $mergedPosterPdfService,
    ) {
    }

    public function index(Edition $edition): JsonResponse
    {
        $posters = EditionDivisionPoster::query()
            ->where('edition_id', $edition->id_edition)
            ->whereNull('edition_division_id')
            ->orderBy('id_edition_division_poster')
            ->get();

        return response()->json([
            'data' => EditionDivisionPosterResource::collection($posters),
        ]);
    }

    public function mergedFile(Edition $edition): StreamedResponse|JsonResponse
    {
        try {
            $result = $this->mergedPosterPdfService->mergeForEdition($edition);
        } catch (EditionMergedPosterPdfException $e) {
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

    public function attendance(Edition $edition): JsonResponse
    {
        if ($edition->finalized_at !== null) {
            return response()->json(['message' => 'Edition is finalized.'], 409);
        }

        $result = $this->posterPdfService->generateAttendanceForEdition($edition);

        return response()->json([
            'message' => 'Poster generated.',
            'data' => new EditionDivisionPosterResource($result->poster),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/editions/{edition}/posters/attendance/preview",
     *     operationId="managerEditionAttendancePosterPreview",
     *     tags={"🐧 Manager: Edition Posters"},
     *     summary="Preview attendance sheet HTML",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="edition", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="HTML preview"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function attendancePreview(Edition $edition): Response
    {
        $html = $this->posterPdfService->renderAttendanceHtmlForEdition($edition);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function attendanceFile(Edition $edition): StreamedResponse|JsonResponse
    {
        $poster = EditionDivisionPoster::query()
            ->where('edition_id', $edition->id_edition)
            ->whereNull('edition_division_id')
            ->where('poster_kind', 'ATTENDANCE')
            ->first();

        return $this->streamStoredFile($poster, 'generated_path', 'application/pdf');
    }

    private function streamStoredFile(?EditionDivisionPoster $poster, string $pathAttr, string $contentType): StreamedResponse|JsonResponse
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
