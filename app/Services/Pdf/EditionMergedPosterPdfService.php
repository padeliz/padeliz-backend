<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionDivisionPoster;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Fpdi;

final class EditionMergedPosterPdfService
{
    /**
     * @return array{
     *     bytes: string,
     *     page_count: int,
     *     filename: string,
     *     files_merged: int
     * }
     */
    public function mergeForEdition(Edition $edition): array
    {
        $edition->loadMissing('divisions');
        $files = $this->collectMergeFiles($edition);

        if ($files === []) {
            throw new EditionMergedPosterPdfException(
                'No generated division posters found for this edition.',
                422,
                [
                    'edition_id' => (int) $edition->id_edition,
                    'files_attempted' => [],
                ]
            );
        }

        $attempted = [];
        $pdf = new Fpdi();

        try {
            foreach ($files as $file) {
                $attempted[] = $file;
                $absolute = $this->absolutePath($file['path']);
                if (! is_file($absolute)) {
                    throw new EditionMergedPosterPdfException(
                        'Failed to merge generated posters.',
                        422,
                        [
                            'edition_id' => (int) $edition->id_edition,
                            'files_attempted' => $attempted,
                            'failed_at' => [
                                'path' => $file['path'],
                                'reason' => 'File missing on disk.',
                            ],
                        ]
                    );
                }

                try {
                    $pageCount = $pdf->setSourceFile($absolute);
                } catch (\Throwable $e) {
                    throw new EditionMergedPosterPdfException(
                        'Failed to merge generated posters.',
                        422,
                        [
                            'edition_id' => (int) $edition->id_edition,
                            'files_attempted' => $attempted,
                            'failed_at' => [
                                'path' => $file['path'],
                                'reason' => $e->getMessage(),
                            ],
                        ]
                    );
                }

                for ($page = 1; $page <= $pageCount; $page++) {
                    $templateId = $pdf->importPage($page);
                    $size = $pdf->getTemplateSize($templateId);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            }
        } catch (EditionMergedPosterPdfException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new EditionMergedPosterPdfException(
                'Failed to merge generated posters.',
                422,
                [
                    'edition_id' => (int) $edition->id_edition,
                    'files_attempted' => $attempted,
                    'failed_at' => [
                        'path' => $attempted !== [] ? ($attempted[array_key_last($attempted)]['path'] ?? '') : '',
                        'reason' => $e->getMessage(),
                    ],
                ]
            );
        }

        $pageCount = max(0, $pdf->PageNo());
        $bytes = $pdf->Output('S');
        $filename = sprintf(
            'A2 Hârtie Color Laminat - %s Afișe Terenuri - %d pagini',
            (string) $edition->name,
            $pageCount
        );

        return [
            'bytes' => $bytes,
            'page_count' => $pageCount,
            'filename' => $filename,
            'files_merged' => count($files),
        ];
    }

    /**
     * @return list<array{
     *     edition_division_id: int,
     *     poster_kind: string,
     *     court_id: int|null,
     *     path: string,
     *     status: string
     * }>
     */
    public function collectMergeFiles(Edition $edition): array
    {
        $divisions = EditionDivision::query()
            ->where('edition_id', $edition->id_edition)
            ->orderBy('skill_order')
            ->orderBy('id_edition_division')
            ->get();

        $files = [];

        foreach ($divisions as $division) {
            $ranking = EditionDivisionPoster::query()
                ->where('edition_division_id', $division->id_edition_division)
                ->where('poster_kind', 'RANKING')
                ->whereNull('edition_division_court_id')
                ->whereNotNull('generated_path')
                ->orderByDesc('id_edition_division_poster')
                ->first();

            if ($ranking?->generated_path) {
                $files[] = [
                    'edition_division_id' => (int) $division->id_edition_division,
                    'poster_kind' => 'RANKING',
                    'court_id' => null,
                    'path' => (string) $ranking->generated_path,
                    'status' => 'included',
                ];
            }

            $courts = EditionDivisionCourt::query()
                ->where('edition_division_id', $division->id_edition_division)
                ->orderBy('display_order')
                ->orderBy('id_edition_division_court')
                ->get();

            foreach ($courts as $court) {
                $matches = EditionDivisionPoster::query()
                    ->where('edition_division_id', $division->id_edition_division)
                    ->where('poster_kind', 'MATCHES')
                    ->where('edition_division_court_id', $court->id_edition_division_court)
                    ->whereNotNull('generated_path')
                    ->orderByDesc('id_edition_division_poster')
                    ->first();

                if ($matches?->generated_path) {
                    $files[] = [
                        'edition_division_id' => (int) $division->id_edition_division,
                        'poster_kind' => 'MATCHES',
                        'court_id' => (int) $court->id_edition_division_court,
                        'path' => (string) $matches->generated_path,
                        'status' => 'included',
                    ];
                }
            }
        }

        return $files;
    }

    private function absolutePath(string $relative): string
    {
        $disk = config('filesystems.user_uploads_disk', 'public');

        return Storage::disk($disk)->path($relative);
    }
}

final class EditionMergedPosterPdfException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly array $payload,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }
}
