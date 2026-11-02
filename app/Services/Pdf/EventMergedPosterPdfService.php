<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\EventDivisionPoster;
use App\Models\PadelEvent;
use App\Services\Storage\UserUploadsDisk;
use RuntimeException;
use setasign\Fpdi\Fpdi;

final class EventMergedPosterPdfService
{
    /**
     * @return array{
     *     bytes: string,
     *     page_count: int,
     *     filename: string,
     *     files_merged: int
     * }
     */
    public function mergeForEdition(PadelEvent $event): array
    {
        $event->loadMissing('divisions');
        $files = $this->collectMergeFiles($event);

        if ($files === []) {
            throw new EventMergedPosterPdfException(
                'No generated division posters found for this edition.',
                422,
                [
                    'event_id' => (int) $event->id_event,
                    'files_attempted' => [],
                ]
            );
        }

        $attempted = [];
        $pdf = new Fpdi();
        $temps = [];

        try {
            foreach ($files as $file) {
                $attempted[] = $file;
                try {
                    $local = $this->localPathForRead($file['path']);
                } catch (\Throwable) {
                    throw new EventMergedPosterPdfException(
                        'Failed to merge generated posters.',
                        422,
                        [
                            'event_id' => (int) $event->id_event,
                            'files_attempted' => $attempted,
                            'failed_at' => [
                                'path' => $file['path'],
                                'reason' => 'File missing on disk.',
                            ],
                        ]
                    );
                }
                $absolute = $local['path'];
                if ($local['delete_after']) {
                    $temps[] = $absolute;
                }

                try {
                    $pageCount = $pdf->setSourceFile($absolute);
                } catch (\Throwable $e) {
                    throw new EventMergedPosterPdfException(
                        'Failed to merge generated posters.',
                        422,
                        [
                            'event_id' => (int) $event->id_event,
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
        } catch (EventMergedPosterPdfException $e) {
            foreach ($temps as $temp) {
                @unlink($temp);
            }
            throw $e;
        } catch (\Throwable $e) {
            foreach ($temps as $temp) {
                @unlink($temp);
            }
            throw new EventMergedPosterPdfException(
                'Failed to merge generated posters.',
                422,
                [
                    'event_id' => (int) $event->id_event,
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
        foreach ($temps as $temp) {
            @unlink($temp);
        }
        $filename = sprintf(
            'A2 Hârtie Color Laminat - %s Afișe Terenuri - %d pagini',
            (string) $event->name,
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
     *     event_division_id: int,
     *     poster_kind: string,
     *     court_id: null|int,
     *     path: string,
     *     status: string
     * }>
     */
    public function collectMergeFiles(PadelEvent $event): array
    {
        $divisions = EventDivision::query()
            ->where('event_id', $event->id_event)
            ->orderBy('skill_order')
            ->orderBy('id_event_division')
            ->get();

        $files = [];

        foreach ($divisions as $division) {
            $ranking = EventDivisionPoster::query()
                ->where('event_division_id', $division->id_event_division)
                ->where('poster_kind', 'RANKING')
                ->whereNull('event_division_court_id')
                ->whereNotNull('generated_path')
                ->orderByDesc('id_event_division_poster')
                ->first();

            if ($ranking?->generated_path) {
                $files[] = [
                    'event_division_id' => (int) $division->id_event_division,
                    'poster_kind' => 'RANKING',
                    'court_id' => null,
                    'path' => (string) $ranking->generated_path,
                    'status' => 'included',
                ];
            }

            $courts = EventDivisionCourt::query()
                ->where('event_division_id', $division->id_event_division)
                ->orderBy('display_order')
                ->orderBy('id_event_division_court')
                ->get();

            foreach ($courts as $court) {
                $matches = EventDivisionPoster::query()
                    ->where('event_division_id', $division->id_event_division)
                    ->where('poster_kind', 'MATCHES')
                    ->where('event_division_court_id', $court->id_event_division_court)
                    ->whereNotNull('generated_path')
                    ->orderByDesc('id_event_division_poster')
                    ->first();

                if ($matches?->generated_path) {
                    $files[] = [
                        'event_division_id' => (int) $division->id_event_division,
                        'poster_kind' => 'MATCHES',
                        'court_id' => (int) $court->id_event_division_court,
                        'path' => (string) $matches->generated_path,
                        'status' => 'included',
                    ];
                }
            }
        }

        return $files;
    }

    /**
     * @return array{path: string, delete_after: bool}
     */
    private function localPathForRead(string $relative): array
    {
        return app(UserUploadsDisk::class)->localPathForRead($relative);
    }
}

final class EventMergedPosterPdfException extends RuntimeException
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
