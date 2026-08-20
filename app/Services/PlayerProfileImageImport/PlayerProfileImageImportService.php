<?php

declare(strict_types=1);

namespace App\Services\PlayerProfileImageImport;

use App\Models\Player;
use App\Models\PlayerProfileImage;
use App\Models\PlayerProfileImageImport;
use App\Models\PlayerProfileImageImportRow;
use App\Services\PlayerProfileImage\PlayerProfileImagePrimaryService;
use App\Services\PlayerProfileImage\PlayerProfileImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class PlayerProfileImageImportService
{
    public const MAX_FILENAMES = 100;

    public function __construct(
        private readonly PlayerProfileImageImportMapper $mapper,
        private readonly PlayerProfileImageUploadService $uploadService,
        private readonly PlayerProfileImagePrimaryService $primaryService,
    ) {
    }

    /**
     * @param  list<string>  $filenames
     * @return array{max_images_per_player: int, rows: list<array<string, mixed>>}
     */
    public function preview(array $filenames): array
    {
        $this->assertFilenames($filenames);

        $rows = [];
        foreach (array_values($filenames) as $index => $filename) {
            $rows[] = ['index' => $index, 'filename' => $filename];
        }

        $mapped = $this->mapper->mapFilenames($rows);

        return [
            'max_images_per_player' => PlayerProfileImage::MAX_PER_PLAYER,
            'rows' => $mapped,
        ];
    }

    /**
     * @param  array{original_filename: string, rows: list<array<string, mixed>>}  $payload
     * @param  array<int, UploadedFile>  $filesByIndex
     */
    public function save(array $payload, array $filesByIndex, int $importedByPlayerId): PlayerProfileImageImport
    {
        $label = trim((string) ($payload['original_filename'] ?? ''));
        if ($label === '' || mb_strlen($label) > 255) {
            throw new PlayerProfileImageImportFailedException(
                'Validation failed',
                ['original_filename' => ['A filename label of at most 255 characters is required.']],
            );
        }

        $rows = $payload['rows'] ?? [];
        if (! is_array($rows) || $rows === []) {
            throw new PlayerProfileImageImportFailedException(
                'Validation failed',
                ['rows' => ['At least one row is required.']],
            );
        }

        $this->validateSaveRows($rows, $filesByIndex);

        usort($rows, static fn (array $a, array $b): int => ((int) $a['index']) <=> ((int) $b['index']));

        return DB::transaction(function () use ($rows, $filesByIndex, $label, $importedByPlayerId): PlayerProfileImageImport {
            $import = PlayerProfileImageImport::create([
                'imported_by_player_id' => $importedByPlayerId,
                'original_filename' => $label,
                'included_count' => count($rows),
                'uploaded_count' => 0,
            ]);

            $uploadedCount = 0;
            $countsByPlayer = $this->existingImageCountsForRows($rows);

            foreach ($rows as $row) {
                $index = (int) $row['index'];
                $playerId = (int) $row['player_id'];
                $player = Player::query()->findOrFail($playerId);
                $file = $filesByIndex[$index] ?? null;
                if (! $file instanceof UploadedFile) {
                    throw new PlayerProfileImageImportFailedException(
                        'Validation failed',
                        ["rows.{$index}" => ['Missing uploaded file for this row.']],
                    );
                }

                $current = $countsByPlayer[$playerId] ?? 0;
                if ($current >= PlayerProfileImage::MAX_PER_PLAYER) {
                    throw new PlayerProfileImageImportFailedException(
                        'Validation failed',
                        ["rows.{$index}" => ['This player already has the maximum number of profile images.']],
                    );
                }

                $image = $this->uploadService->upload($player, $file, [
                    'uploaded_via' => PlayerProfileImage::UPLOADED_VIA_IMPORT,
                    'uploaded_via_import_id' => $import->id_player_profile_image_import,
                    'original_filename' => (string) $row['filename'],
                    'is_pdf_primary' => (bool) ($row['is_pdf_primary'] ?? false),
                ]);

                PlayerProfileImageImportRow::create([
                    'player_profile_image_import_id' => $import->id_player_profile_image_import,
                    'row_index' => $index,
                    'original_filename' => (string) $row['filename'],
                    'player_id' => $playerId,
                    'player_profile_image_id' => $image->id,
                    'is_pdf_primary' => (bool) ($row['is_pdf_primary'] ?? false),
                    'action' => 'uploaded',
                ]);

                $countsByPlayer[$playerId] = $current + 1;
                $uploadedCount++;

                if ((bool) ($row['is_pdf_primary'] ?? false)) {
                    $this->primaryService->bustPdfCache($playerId);
                }
            }

            $import->update(['uploaded_count' => $uploadedCount]);

            return $import->fresh(['rows', 'importedByPlayer']) ?? $import;
        });
    }

    /**
     * @param  list<string>  $filenames
     */
    private function assertFilenames(array $filenames): void
    {
        if ($filenames === []) {
            throw new PlayerProfileImageImportFailedException(
                'Validation failed',
                ['filenames' => ['At least one filename is required.']],
            );
        }

        if (count($filenames) > self::MAX_FILENAMES) {
            throw new PlayerProfileImageImportFailedException(
                'Validation failed',
                ['filenames' => ['At most ' . self::MAX_FILENAMES . ' filenames are allowed per batch.']],
            );
        }

        foreach ($filenames as $i => $filename) {
            $name = trim((string) $filename);
            if ($name === '') {
                throw new PlayerProfileImageImportFailedException(
                    'Validation failed',
                    ["filenames.{$i}" => ['Filename cannot be empty.']],
                );
            }
            if (mb_strlen($name) > 255) {
                throw new PlayerProfileImageImportFailedException(
                    'Validation failed',
                    ["filenames.{$i}" => ['Filename is too long.']],
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, UploadedFile>  $filesByIndex
     */
    private function validateSaveRows(array $rows, array $filesByIndex): void
    {
        $errors = [];
        $byPlayer = [];

        foreach ($rows as $row) {
            $index = (int) ($row['index'] ?? -1);
            $playerId = (int) ($row['player_id'] ?? 0);
            $filename = trim((string) ($row['filename'] ?? ''));

            if ($playerId <= 0) {
                $errors["rows.{$index}"] = ['A player is required for each included row.'];
            }
            if ($filename === '') {
                $errors["rows.{$index}.filename"] = ['Filename is required.'];
            }
            if (! isset($filesByIndex[$index])) {
                $errors["rows.{$index}"] = array_merge(
                    $errors["rows.{$index}"] ?? [],
                    ['Missing uploaded file for this row.']
                );
            }

            if ($playerId > 0) {
                $byPlayer[$playerId] ??= [];
                $byPlayer[$playerId][] = [
                    'index' => $index,
                    'is_pdf_primary' => (bool) ($row['is_pdf_primary'] ?? false),
                ];
            }
        }

        foreach ($byPlayer as $playerId => $playerRows) {
            $primaryCount = count(array_filter(
                $playerRows,
                static fn (array $r): bool => $r['is_pdf_primary']
            ));
            $existing = PlayerProfileImage::query()->where('player_id', $playerId)->count();
            $batchCount = count($playerRows);

            if ($existing + $batchCount > PlayerProfileImage::MAX_PER_PLAYER) {
                foreach ($playerRows as $playerRow) {
                    $errors["rows.{$playerRow['index']}"] = ['This player already has the maximum number of profile images.'];
                }
            }

            if (count($playerRows) >= 2) {
                if ($primaryCount !== 1) {
                    foreach ($playerRows as $playerRow) {
                        $errors["rows.{$playerRow['index']}"] = array_merge(
                            $errors["rows.{$playerRow['index']}"] ?? [],
                            ['Exactly one row per player must be marked as PDF primary in this batch.']
                        );
                    }
                }
            } elseif (count($playerRows) === 1 && $existing === 0 && ! $playerRows[0]['is_pdf_primary']) {
                $idx = $playerRows[0]['index'];
                $errors["rows.{$idx}"] = array_merge(
                    $errors["rows.{$idx}"] ?? [],
                    ['The first profile image for a player must be used in PDFs.']
                );
            }
        }

        if ($errors !== []) {
            throw new PlayerProfileImageImportFailedException('Validation failed', $errors);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, int>
     */
    private function existingImageCountsForRows(array $rows): array
    {
        $playerIds = array_unique(array_map(
            static fn (array $row): int => (int) $row['player_id'],
            $rows
        ));

        $counts = [];
        foreach ($playerIds as $playerId) {
            $counts[$playerId] = PlayerProfileImage::query()->where('player_id', $playerId)->count();
        }

        return $counts;
    }
}
