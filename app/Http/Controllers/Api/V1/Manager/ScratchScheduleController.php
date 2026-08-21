<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Services\Pdf\PosterPdfService;
use App\Services\Schedule\PlayingWindow;
use App\Services\Schedule\ScheduleGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Mode B scratch schedule tools (preview + stream-only PDFs, no DB writes).
 *
 * @OA\Tag(
 *     name="🐧 Manager: Tools Schedule",
 *     description="(v1) Mode B ephemeral schedule preview and PDF download"
 * )
 */
class ScratchScheduleController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function __construct(
        private readonly ScheduleGenerationService $scheduleGenerationService,
        private readonly PosterPdfService $posterPdfService,
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/tools/schedule/preview",
     *     summary="Preview a scratch schedule (Mode B, no DB writes)",
     *     tags={"🐧 Manager: Tools Schedule"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"player_ids", "court_names"},
     *             @OA\Property(property="player_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="court_names", type="array", maxItems=4, @OA\Items(type="string")),
     *             @OA\Property(property="player_opponents_nr", type="integer"),
     *             @OA\Property(property="opponents_per_player", type="integer"),
     *             @OA\Property(property="player_matches_repeat", type="integer"),
     *             @OA\Property(property="repeat_partners", type="integer"),
     *             @OA\Property(property="playing_start_time", type="string"),
     *             @OA\Property(property="time_start", type="string"),
     *             @OA\Property(property="playing_end_time", type="string"),
     *             @OA\Property(property="time_end", type="string"),
     *             @OA\Property(property="include_final", type="boolean"),
     *             @OA\Property(property="has_demonstrative_match", type="boolean"),
     *             @OA\Property(property="template_version", type="integer", nullable=true),
     *             @OA\Property(property="adjust", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Schedule preview JSON"),
     *     @OA\Response(response=422, description="Validation / generation error")
     * )
     */
    public function preview(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateScratchBase($request);
            $preview = $this->buildPreview($validated);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($preview, 'Scratch schedule preview generated successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/tools/schedule/pdf/matches",
     *     summary="Stream MATCHES PDF from scratch schedule (Mode B)",
     *     tags={"🐧 Manager: Tools Schedule"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="PDF stream", @OA\MediaType(mediaType="application/pdf")),
     *     @OA\Response(response=501, description="PDF service unavailable")
     * )
     */
    public function pdfMatches(Request $request): StreamedResponse|JsonResponse
    {
        try {
            $validated = $this->validateScratchBase($request);
            $validated += $request->validate([
                'court_index' => ['required', 'integer', 'min:0'],
                'title' => ['required', 'string', 'max:64'],
                'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'edition_label' => ['nullable', 'string', 'max:25'],
                'organizer_id' => ['nullable', 'integer', 'exists:organizers,id_organizer'],
                'club_id' => ['nullable', 'integer', 'exists:clubs,id_club'],
                'points_per_match' => ['nullable', 'integer', 'min:0'],
                'final_points_target' => ['nullable', 'integer', 'min:1'],
                'include_scores' => ['nullable', 'boolean'],
                'allow_replacements' => ['nullable', 'boolean'],
                'collecting_player_ids' => ['nullable', 'array'],
                'collecting_player_ids.*' => ['integer', 'distinct'],
            ]);

            if ((int) $validated['court_index'] >= count($validated['court_names'])) {
                return $this->errorResponse('court_index is out of range for court_names.', 422);
            }

            $result = $this->posterPdfService->generateMatchesFromScratch([
                ...$validated,
                'organizer_id' => $validated['organizer_id'] ?? null,
                'club_id' => $validated['club_id'] ?? null,
                'final_points_target' => (int) ($validated['final_points_target'] ?? 24),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 501);
        }

        return $this->pdfResponse($result->bytes, $result->filename);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/tools/schedule/pdf/ranking",
     *     summary="Stream RANKING PDF from scratch schedule (Mode B)",
     *     tags={"🐧 Manager: Tools Schedule"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="PDF stream", @OA\MediaType(mediaType="application/pdf")),
     *     @OA\Response(response=501, description="PDF service unavailable")
     * )
     */
    public function pdfRanking(Request $request): StreamedResponse|JsonResponse
    {
        try {
            $validated = $this->validateScratchBase($request);
            $validated += $request->validate([
                'title' => ['required', 'string', 'max:64'],
                'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'edition_label' => ['nullable', 'string', 'max:25'],
                'organizer_id' => ['nullable', 'integer', 'exists:organizers,id_organizer'],
                'club_id' => ['nullable', 'integer', 'exists:clubs,id_club'],
                'points_per_match' => ['nullable', 'integer', 'min:0'],
                'include_scores' => ['nullable', 'boolean'],
                'allow_replacements' => ['nullable', 'boolean'],
                'collecting_player_ids' => ['nullable', 'array'],
                'collecting_player_ids.*' => ['integer', 'distinct'],
            ]);

            $result = $this->posterPdfService->generateRankingFromScratch([
                ...$validated,
                'organizer_id' => $validated['organizer_id'] ?? null,
                'club_id' => $validated['club_id'] ?? null,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 501);
        }

        return $this->pdfResponse($result->bytes, $result->filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateScratchBase(Request $request): array
    {
        $validated = $request->validate([
            'player_ids' => ['required', 'array', 'min:1'],
            'player_ids.*' => ['integer', 'distinct', 'exists:players,id_player'],
            'court_names' => ['required', 'array', 'min:1', 'max:4'],
            'court_names.*' => ['string', 'max:30'],
            'time_start' => ['nullable', PlayingWindow::clockRegexRule()],
            'time_end' => ['nullable', PlayingWindow::clockRegexRule()],
            'playing_start_time' => ['nullable', PlayingWindow::clockRegexRule()],
            'playing_end_time' => ['nullable', PlayingWindow::clockRegexRule()],
            'opponents_per_player' => ['nullable', 'integer', 'min:1'],
            'player_opponents_nr' => ['nullable', 'integer', 'min:1'],
            'repeat_partners' => ['nullable', 'integer', 'min:1'],
            'player_matches_repeat' => ['nullable', 'integer', 'min:1'],
            'include_final' => ['nullable', 'boolean'],
            'has_demonstrative_match' => ['nullable', 'boolean'],
            'template_version' => ['nullable', 'integer', 'min:1'],
            'adjust' => ['nullable', 'integer'],
        ]);

        $validated['time_start'] = $this->toHi(
            (string) ($validated['time_start'] ?? $validated['playing_start_time'] ?? '')
        );
        $validated['time_end'] = $this->toHi(
            (string) ($validated['time_end'] ?? $validated['playing_end_time'] ?? '')
        );
        $validated['opponents_per_player'] = (int) (
            $validated['opponents_per_player']
            ?? $validated['player_opponents_nr']
            ?? 0
        );
        $validated['repeat_partners'] = (int) (
            $validated['repeat_partners']
            ?? $validated['player_matches_repeat']
            ?? 1
        );

        if ($validated['time_start'] === '' || $validated['time_end'] === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'time_start' => ['time_start/playing_start_time and time_end/playing_end_time are required.'],
            ]);
        }
        if ($validated['opponents_per_player'] < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'opponents_per_player' => ['opponents_per_player/player_opponents_nr is required.'],
            ]);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildPreview(array $validated): array
    {
        $preview = $this->scheduleGenerationService->preview(
            array_values(array_map('intval', $validated['player_ids'])),
            (int) $validated['opponents_per_player'],
            (int) $validated['repeat_partners'],
            array_values($validated['court_names']),
            (string) $validated['time_start'],
            (string) $validated['time_end'],
            (bool) ($validated['include_final'] ?? false),
            (bool) ($validated['has_demonstrative_match'] ?? false),
            isset($validated['template_version']) ? (int) $validated['template_version'] : null,
        );

        if (isset($validated['adjust'])) {
            $preview['points_per_match'] = max(0, (int) $preview['points_per_match'] + (int) $validated['adjust']);
        }

        return $preview;
    }

    private function toHi(string $time): string
    {
        if ($time === '') {
            return '';
        }

        $clock = PlayingWindow::toClockString($time);
        if ($clock === null || $clock === '') {
            return '';
        }

        return substr($clock, 0, 5);
    }

    private function pdfResponse(string $bytes, string $filename): StreamedResponse
    {
        return response()->streamDownload(static function () use ($bytes): void {
            echo $bytes;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
