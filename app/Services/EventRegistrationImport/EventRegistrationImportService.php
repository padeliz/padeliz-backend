<?php

declare(strict_types=1);

namespace App\Services\EventRegistrationImport;

use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\PadelEvent;
use App\Models\Player;
use App\Services\PlayerImport\PlayerCsvFormatRegistry;
use App\Services\PlayerImport\PlayerImportMapper;
use App\Support\PlayerNameSplitter;
use App\Support\PlayerPhoneNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class EventRegistrationImportService
{
    public const MAX_FILE_KB = 2048;

    public const MAX_ROWS = 500;

    public const ALLOWED_MIMES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
    ];

    public function __construct(
        private readonly PlayerCsvFormatRegistry $registry,
        private readonly PlayerImportMapper $mapper,
    ) {
    }

    /**
     * @return array{format: string, rows: list<array<string, mixed>>}
     */
    public function preview(PadelEvent $event, UploadedFile $file): array
    {
        $this->assertPreviewFile($file);

        $parsed = $this->parseCsv($file);
        $format = $this->registry->detect($parsed['headers']);
        if ($format === null) {
            throw new EventRegistrationImportFailedException(
                'Unknown CSV format. Supported: Playtomic.',
                ['file' => ['Unknown CSV format. Supported: Playtomic.']],
            );
        }

        $normalized = [];
        foreach ($parsed['rows'] as $index => $assoc) {
            $raw = $format->parseRow($assoc);
            $parts = PlayerNameSplitter::splitForPreview($raw['name']);
            $email = $this->normalizeEmail($raw['email']);
            $phone = PlayerPhoneNormalizer::normalize($raw['phone']);
            $normalized[] = [
                'index' => $index,
                'first_name' => $parts['first_name'],
                'middle_name' => $parts['middle_name'],
                'last_name' => $parts['last_name'],
                'email' => $email,
                'phone' => $phone,
            ];
        }

        $mapped = $this->mapper->mapRows($normalized);
        $mappedPlayerIds = [];
        foreach ($mapped as $row) {
            $player = $row['mapping']['player'] ?? null;
            if ($row['mapping']['status'] === 'mapped' && $player instanceof Player) {
                $mappedPlayerIds[(int) $player->id_player] = true;
            }
        }

        $alreadyRegistered = [];
        if ($mappedPlayerIds !== []) {
            $alreadyRegistered = EventRegistration::query()
                ->active()
                ->where('event_id', $event->id_event)
                ->whereIn('player_id', array_keys($mappedPlayerIds))
                ->pluck('player_id')
                ->map(static fn ($id): int => (int) $id)
                ->unique()
                ->flip()
                ->all();
        }

        $rows = [];
        foreach ($mapped as $row) {
            $player = $row['mapping']['player'] ?? null;
            $already = false;
            if ($row['mapping']['status'] === 'mapped' && $player instanceof Player) {
                $already = isset($alreadyRegistered[(int) $player->id_player]);
            }

            $rows[] = [
                'index' => $row['index'],
                'first_name' => $row['first_name'],
                'middle_name' => $row['middle_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'already_registered' => $already,
                'mapping' => $row['mapping'],
            ];
        }

        return [
            'format' => $format->formatKey(),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{format?: string, rows: list<array<string, mixed>>}  $payload
     * @return array{
     *     registered: int,
     *     skipped_already_registered: int,
     *     skipped_unmapped: int,
     *     skipped_conflict: int,
     *     rows: list<array{index: int, player_id: ?int, action: string, registration_id: ?int}>
     * }
     */
    public function save(PadelEvent $event, array $payload): array
    {
        $formatKey = (string) ($payload['format'] ?? '');
        if ($this->registry->byKey($formatKey) === null) {
            $supported = implode(', ', $this->registry->formatKeys()) ?: 'playtomic';

            throw new EventRegistrationImportFailedException(
                'Unknown format. Supported: ' . $supported . '.',
                ['format' => ['Unknown format. Supported: ' . $supported . '.']],
            );
        }

        $rows = $payload['rows'] ?? [];
        if (! is_array($rows) || $rows === []) {
            throw new EventRegistrationImportFailedException(
                'Validation failed',
                ['rows' => ['At least one included row is required.']],
            );
        }

        $prepared = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $prepared[] = $this->prepareSaveRow($row);
        }

        if ($prepared === []) {
            throw new EventRegistrationImportFailedException(
                'Validation failed',
                ['rows' => ['At least one included row is required.']],
            );
        }

        $this->assertSaveRows($prepared);

        $eventDivisionId = $payload['event_division_id'] ?? null;
        if ($eventDivisionId === null || $eventDivisionId === '') {
            throw new EventRegistrationImportFailedException(
                'Validation failed',
                ['event_division_id' => ['The edition division field is required.']],
            );
        }

        $eventDivisionId = (int) $eventDivisionId;
        $eventDivision = EventDivision::query()
            ->whereKey($eventDivisionId)
            ->where('event_id', $event->id_event)
            ->first();

        if ($eventDivision === null) {
            throw new EventRegistrationImportFailedException(
                'Validation failed',
                ['event_division_id' => ['The selected edition division does not belong to this edition.']],
            );
        }

        $entryFee = (int) $eventDivision->entry_fee;
        $editionId = (int) $event->id_event;

        return DB::transaction(function () use ($prepared, $editionId, $eventDivisionId, $entryFee): array {
            $seenPlayerIds = EventRegistration::query()
                ->active()
                ->where('event_id', $editionId)
                ->pluck('player_id')
                ->map(static fn ($id): int => (int) $id)
                ->flip()
                ->all();

            $registered = 0;
            $skippedAlready = 0;
            $skippedUnmapped = 0;
            $skippedConflict = 0;
            $outRows = [];

            foreach ($prepared as $row) {
                $index = $row['index'];
                $playerId = $row['player_id'];
                $status = $row['status'];

                if ($status === 'unmapped' || $playerId === null) {
                    $skippedUnmapped++;
                    $outRows[] = [
                        'index' => $index,
                        'player_id' => $playerId,
                        'action' => 'skipped_unmapped',
                        'registration_id' => null,
                    ];

                    continue;
                }

                if ($status === 'conflict') {
                    $skippedConflict++;
                    $outRows[] = [
                        'index' => $index,
                        'player_id' => $playerId,
                        'action' => 'skipped_conflict',
                        'registration_id' => null,
                    ];

                    continue;
                }

                if (isset($seenPlayerIds[$playerId])) {
                    $skippedAlready++;
                    $outRows[] = [
                        'index' => $index,
                        'player_id' => $playerId,
                        'action' => 'skipped_already_registered',
                        'registration_id' => null,
                    ];

                    continue;
                }

                if (EventRegistration::hasDeclinedForEdition($editionId, $playerId)) {
                    $skippedConflict++;
                    $outRows[] = [
                        'index' => $index,
                        'player_id' => $playerId,
                        'action' => 'skipped_conflict',
                        'registration_id' => null,
                    ];

                    continue;
                }

                $registration = EventRegistration::create([
                    'event_id' => $editionId,
                    'player_id' => $playerId,
                    'event_division_id' => $eventDivisionId,
                    'is_minor' => false,
                    'is_student' => false,
                    'payment' => $entryFee,
                    'lifecycle_status' => RegistrationLifecycleStatus::Active,
                    'payment_status' => RegistrationPaymentStatus::Paid,
                    'paid_at' => time(),
                ]);

                $seenPlayerIds[$playerId] = true;
                $registered++;
                $outRows[] = [
                    'index' => $index,
                    'player_id' => $playerId,
                    'action' => 'registered',
                    'registration_id' => (int) $registration->id_registration,
                ];
            }

            return [
                'registered' => $registered,
                'skipped_already_registered' => $skippedAlready,
                'skipped_unmapped' => $skippedUnmapped,
                'skipped_conflict' => $skippedConflict,
                'rows' => $outRows,
            ];
        });
    }

    /**
     * @return array{headers: list<string>, rows: list<array<string, string>>}
     */
    private function parseCsv(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new EventRegistrationImportFailedException(
                'Could not read the uploaded file.',
                ['file' => ['Could not read the uploaded file.']],
            );
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new EventRegistrationImportFailedException(
                'Could not read the uploaded file.',
                ['file' => ['Could not read the uploaded file.']],
            );
        }

        try {
            $headerLine = fgetcsv($handle);
            if ($headerLine === false || $headerLine === [null] || $headerLine === []) {
                throw new EventRegistrationImportFailedException(
                    'Unknown CSV format. Supported: Playtomic.',
                    ['file' => ['Unknown CSV format. Supported: Playtomic.']],
                );
            }

            $headers = array_map(function ($header): string {
                $value = is_string($header) ? $header : (string) $header;
                if (str_starts_with($value, "\u{FEFF}")) {
                    $value = substr($value, strlen("\u{FEFF}"));
                }
                if (str_starts_with($value, "\xEF\xBB\xBF")) {
                    $value = substr($value, 3);
                }

                return $value;
            }, $headerLine);

            $rows = [];
            while (($data = fgetcsv($handle)) !== false) {
                if ($this->isEmptyCsvLine($data)) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw new EventRegistrationImportFailedException(
                        'CSV has too many rows (max 500).',
                        ['file' => ['CSV has too many rows (max 500).']],
                    );
                }
                $padded = array_pad($data, count($headers), '');
                $assoc = [];
                foreach ($headers as $i => $header) {
                    $cell = $padded[$i] ?? '';
                    $assoc[$header] = is_string($cell) ? $cell : (string) $cell;
                }
                $rows[] = $assoc;
            }

            return ['headers' => $headers, 'rows' => $rows];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<null|string>  $data
     */
    private function isEmptyCsvLine(array $data): bool
    {
        foreach ($data as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function assertPreviewFile(UploadedFile $file): void
    {
        $validator = Validator::make(
            ['file' => $file],
            [
                'file' => [
                    'required',
                    'file',
                    'extensions:csv',
                    'mimetypes:' . implode(',', self::ALLOWED_MIMES),
                    'max:' . self::MAX_FILE_KB,
                ],
            ],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     index: int,
     *     first_name: string,
     *     middle_name: ?string,
     *     last_name: ?string,
     *     email: string,
     *     phone: string,
     *     player_id: ?int,
     *     status: string
     * }
     */
    private function prepareSaveRow(array $row): array
    {
        $email = $this->normalizeEmail(isset($row['email']) ? (string) $row['email'] : '');
        $phoneRaw = isset($row['phone']) ? trim((string) $row['phone']) : '';
        $phone = $phoneRaw === '' ? '' : PlayerPhoneNormalizer::normalize($phoneRaw);

        $first = isset($row['first_name']) ? trim((string) $row['first_name']) : '';
        $middle = isset($row['middle_name']) && $row['middle_name'] !== null
            ? trim((string) $row['middle_name'])
            : '';
        $last = isset($row['last_name']) && $row['last_name'] !== null
            ? trim((string) $row['last_name'])
            : '';

        $playerId = $row['player_id'] ?? null;
        if ($playerId === '' || $playerId === false) {
            $playerId = null;
        }
        if ($playerId !== null) {
            $playerId = (int) $playerId;
        }

        $status = isset($row['status']) ? trim((string) $row['status']) : '';

        return [
            'index' => (int) ($row['index'] ?? 0),
            'first_name' => $first,
            'middle_name' => $middle === '' ? null : $middle,
            'last_name' => $last === '' ? null : $last,
            'email' => $email,
            'phone' => $phone,
            'player_id' => $playerId,
            'status' => $status,
        ];
    }

    /**
     * @param  list<array{
     *     index: int,
     *     first_name: string,
     *     middle_name: ?string,
     *     last_name: ?string,
     *     email: string,
     *     phone: string,
     *     player_id: ?int,
     *     status: string
     * }>  $rows
     */
    private function assertSaveRows(array $rows): void
    {
        $errors = [];

        foreach ($rows as $row) {
            $index = $row['index'];
            $prefix = 'rows.' . $index;

            if ($row['status'] === 'unmapped' || $row['player_id'] === null || $row['status'] === 'conflict') {
                continue;
            }

            if (! Player::query()->whereKey($row['player_id'])->exists()) {
                $errors[$prefix . '.player_id'][] = 'The selected player does not exist.';
            }
        }

        if ($errors !== []) {
            foreach ($errors as $key => $messages) {
                $errors[$key] = array_values(array_unique($messages));
            }

            throw new EventRegistrationImportFailedException('Validation failed', $errors);
        }
    }

    private function normalizeEmail(string $raw): string
    {
        $trimmed = trim($raw);

        return $trimmed === '' ? '' : mb_strtolower($trimmed);
    }
}
