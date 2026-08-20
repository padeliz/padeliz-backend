<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\EditionDivisionPosterResource;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionDivisionPoster;
use App\Services\Pdf\MissingMatchParticipationException;
use App\Services\Pdf\PosterPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Edition Division Posters",
 *     description="(v1) Mode A MATCHES/RANKING poster PDF generation, downloads, and printed uploads"
 * )
 */
class EditionDivisionPosterController extends Controller
{
    public function __construct(
        private readonly PosterPdfService $posterPdfService,
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/posters/matches",
     *     summary="Generate MATCHES poster PDF (Mode A)",
     *     tags={"🐧 Manager: Edition Division Posters"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"court"},
     *             @OA\Property(property="court", type="integer"),
     *             @OA\Property(property="organizer", type="integer", default=1),
     *             @OA\Property(property="partner", type="integer", default=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Generated poster JSON"),
     *     @OA\Response(response=409, description="Edition is finalized"),
     *     @OA\Response(response=422, description="Missing match participation or validation error")
     * )
     */
    public function matches(Request $request, EditionDivision $editionDivision): JsonResponse
    {
        $editionDivision->loadMissing('edition');
        if ($editionDivision->edition?->finalized_at !== null) {
            return response()->json(['message' => 'Edition is finalized.'], 409);
        }

        $validated = $request->validate([
            'court' => [
                'required',
                'integer',
                Rule::exists('edition_division_courts', 'id_edition_division_court')
                    ->where('edition_division_id', $editionDivision->id_edition_division),
            ],
            'organizer' => ['nullable', 'integer', Rule::in(array_keys(config('padeliz_branding.organizers', [])))],
            'partner' => ['nullable', 'integer', Rule::in(array_keys(config('padeliz_branding.partners', [])))],
        ]);

        $court = EditionDivisionCourt::query()->findOrFail((int) $validated['court']);

        try {
            $result = $this->posterPdfService->generateMatchesForEditionDivision(
                $editionDivision,
                $court,
                (int) ($validated['organizer'] ?? 1),
                (int) ($validated['partner'] ?? 1),
            );
        } catch (MissingMatchParticipationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Poster generated.',
            'data' => new EditionDivisionPosterResource($result->poster),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/posters/ranking",
     *     summary="Generate RANKING poster PDF (Mode A)",
     *     tags={"🐧 Manager: Edition Division Posters"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionDivision", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Generated poster JSON"),
     *     @OA\Response(response=409, description="Edition is finalized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function ranking(Request $request, EditionDivision $editionDivision): JsonResponse
    {
        $editionDivision->loadMissing('edition');
        if ($editionDivision->edition?->finalized_at !== null) {
            return response()->json(['message' => 'Edition is finalized.'], 409);
        }

        $validated = $request->validate([
            'organizer' => ['nullable', 'integer', Rule::in(array_keys(config('padeliz_branding.organizers', [])))],
            'partner' => ['nullable', 'integer', Rule::in(array_keys(config('padeliz_branding.partners', [])))],
        ]);

        $result = $this->posterPdfService->generateRankingForEditionDivision(
            $editionDivision,
            (int) ($validated['organizer'] ?? 1),
            (int) ($validated['partner'] ?? 1),
        );

        return response()->json([
            'message' => 'Poster generated.',
            'data' => new EditionDivisionPosterResource($result->poster),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/posters/matches/file",
     *     summary="Download stored MATCHES poster PDF",
     *     tags={"🐧 Manager: Edition Division Posters"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="court", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="PDF stream", @OA\MediaType(mediaType="application/pdf")),
     *     @OA\Response(response=404, description="No generated file")
     * )
     */
    public function matchesFile(Request $request, EditionDivision $editionDivision): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'court' => [
                'required',
                'integer',
                Rule::exists('edition_division_courts', 'id_edition_division_court')
                    ->where('edition_division_id', $editionDivision->id_edition_division),
            ],
        ]);

        $poster = EditionDivisionPoster::query()
            ->where('edition_division_id', $editionDivision->id_edition_division)
            ->where('poster_kind', 'MATCHES')
            ->where('edition_division_court_id', (int) $validated['court'])
            ->first();

        return $this->streamStoredFile($poster, 'generated_path', 'application/pdf');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/posters/ranking/file",
     *     summary="Download stored RANKING poster PDF",
     *     tags={"🐧 Manager: Edition Division Posters"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="PDF stream", @OA\MediaType(mediaType="application/pdf")),
     *     @OA\Response(response=404, description="No generated file")
     * )
     */
    public function rankingFile(EditionDivision $editionDivision): StreamedResponse|JsonResponse
    {
        $poster = EditionDivisionPoster::query()
            ->where('edition_division_id', $editionDivision->id_edition_division)
            ->where('poster_kind', 'RANKING')
            ->whereNull('edition_division_court_id')
            ->first();

        return $this->streamStoredFile($poster, 'generated_path', 'application/pdf');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/posters/{poster}/printed/file",
     *     summary="Download stored printed poster image",
     *     tags={"🐧 Manager: Edition Division Posters"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Image stream"),
     *     @OA\Response(response=404, description="No printed file")
     * )
     */
    public function printedFile(EditionDivision $editionDivision, EditionDivisionPoster $poster): StreamedResponse|JsonResponse
    {
        if ((int) $poster->edition_division_id !== (int) $editionDivision->id_edition_division) {
            return response()->json(['message' => 'Poster does not belong to this edition division.'], 404);
        }

        return $this->streamStoredFile($poster, 'printed_path', 'application/octet-stream');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/posters",
     *     summary="List posters for an edition division",
     *     tags={"🐧 Manager: Edition Division Posters"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Poster list")
     * )
     */
    public function index(EditionDivision $editionDivision): JsonResponse
    {
        $posters = $editionDivision->posters()->orderBy('id_edition_division_poster')->get();

        return response()->json([
            'data' => EditionDivisionPosterResource::collection($posters),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-divisions/{editionDivision}/posters/{poster}/printed",
     *     summary="Upload printed poster photo",
     *     tags={"🐧 Manager: Edition Division Posters"},
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
    public function uploadPrinted(Request $request, EditionDivision $editionDivision, EditionDivisionPoster $poster): JsonResponse
    {
        if ((int) $poster->edition_division_id !== (int) $editionDivision->id_edition_division) {
            return response()->json(['message' => 'Poster does not belong to this edition division.'], 404);
        }

        $editionDivision->loadMissing('edition');
        if ($editionDivision->edition?->finalized_at !== null) {
            return response()->json(['message' => 'Edition is finalized.'], 409);
        }

        $request->validate([
            'image' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $disk = $poster->storageDisk();
        if ($poster->printed_path) {
            Storage::disk($disk)->delete($poster->printed_path);
        }

        $stored = Storage::disk($disk)->putFile(
            'edition-division-posters/' . $poster->id_edition_division_poster . '/printed',
            $request->file('image'),
            ['visibility' => 'public']
        );

        $poster->printed_path = $stored;
        $poster->save();

        return response()->json([
            'data' => new EditionDivisionPosterResource($poster->fresh()),
        ]);
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
