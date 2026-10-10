<?php

declare(strict_types=1);

namespace App\Services\PlayerCustomField;

use App\Enums\PlayerCustomFieldType;
use App\Models\Player;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldOption;
use App\Models\PlayerCustomFieldSubmission;
use App\Models\PlayerCustomFieldValueImport;
use App\Models\PlayerCustomFieldValueImportRow;
use App\Services\PlayerCustomField\Formats\GoogleFormMiniTourWelcomeCsvFormat;
use App\Services\PlayerImport\PlayerImportMapper;
use App\Support\PlayerNameSplitter;
use App\Support\PlayerPhoneNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PlayerCustomFieldValueImportService
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
        private readonly PlayerCustomFieldValueCsvFormatRegistry $registry,
        private readonly PlayerImportMapper $mapper,
        private readonly PlayerCustomFieldSubmissionWriter $writer,
    ) {
    }

    /**
     * @return array{format: string, rows: list<array<string, mixed>>}
     */
    public function preview(UploadedFile $file): array
    {
        $this->assertPreviewFile($file);

        $parsed = $this->parseCsv($file);
        $format = $this->registry->detect($parsed['headers']);
        if ($format === null) {
            throw new PlayerCustomFieldValueImportFailedException(
                'Unknown CSV format. Supported: google_form_minitour_welcome.',
                ['file' => ['Unknown CSV format. Supported: google_form_minitour_welcome.']],
            );
        }

        $normalized = [];
        foreach ($parsed['rows'] as $index => $assoc) {
            $raw = $format->parseRow($assoc);
            $parts = PlayerNameSplitter::splitForPreview($raw['full_name']);
            $email = $this->normalizeEmail($raw['email']);
            $phone = PlayerPhoneNormalizer::normalize($raw['phone']);
            $answers = $this->trimAnswers($raw['answers']);

            $normalized[] = [
                'index' => $index,
                'full_name' => $raw['full_name'],
                'first_name' => $parts['first_name'],
                'middle_name' => $parts['middle_name'],
                'last_name' => $parts['last_name'],
                'email' => $email,
                'phone' => $phone,
                'answers' => $answers,
            ];
        }

        $mapped = $this->mapper->mapRows($this->identityRows($normalized));

        $rows = [];
        foreach ($mapped as $row) {
            $source = $normalized[$row['index']] ?? null;
            $mapping = $row['mapping'];
            $status = $mapping['status'];

            $rows[] = [
                'index' => $row['index'],
                'full_name' => $source['full_name'] ?? '',
                'email' => $row['email'],
                'phone' => $row['phone'],
                'answers' => $source['answers'] ?? [],
                'mapping' => [
                    'status' => $status,
                    'matched_by' => $mapping['matched_by'],
                    'player_id' => $mapping['player']?->id_player,
                    'candidate_ids' => array_map(
                        static fn (Player $player): int => (int) $player->id_player,
                        $mapping['candidates']
                    ),
                    'name_match_ids' => array_map(
                        static fn (Player $player): int => (int) $player->id_player,
                        $mapping['name_matches']
                    ),
                ],
                'included' => $status === 'mapped',
            ];
        }

        return [
            'format' => $format->formatKey(),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{format: string, filename: string, event_id?: null|int, rows: list<array<string, mixed>>}  $payload
     */
    public function save(array $payload, int $importedByPlayerId): PlayerCustomFieldValueImport
    {
        $formatKey = (string) ($payload['format'] ?? '');
        if ($this->registry->byKey($formatKey) === null) {
            $supported = implode(', ', $this->registry->formatKeys()) ?: GoogleFormMiniTourWelcomeCsvFormat::FORMAT_KEY;

            throw new PlayerCustomFieldValueImportFailedException(
                'Unknown format. Supported: ' . $supported . '.',
                ['format' => ['Unknown format. Supported: ' . $supported . '.']],
            );
        }

        $filename = trim((string) ($payload['filename'] ?? ''));
        if ($filename === '' || mb_strlen($filename) > 255) {
            throw new PlayerCustomFieldValueImportFailedException(
                'Validation failed',
                ['filename' => ['A filename of at most 255 characters is required.']],
            );
        }

        $editionId = $payload['event_id'] ?? null;
        if ($editionId === '' || $editionId === false) {
            $editionId = null;
        }
        if ($editionId !== null) {
            $editionId = (int) $editionId;
            $validator = Validator::make(['event_id' => $editionId], [
                'event_id' => ['integer', 'exists:events,id_event'],
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        }

        $rows = $payload['rows'] ?? [];
        if (! is_array($rows) || $rows === []) {
            throw new PlayerCustomFieldValueImportFailedException(
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

        $this->assertSaveRows($prepared);
        $fields = $this->resolveImportFields();

        return DB::transaction(function () use (
            $prepared,
            $formatKey,
            $filename,
            $importedByPlayerId,
            $editionId,
            $fields
        ): PlayerCustomFieldValueImport {
            $import = PlayerCustomFieldValueImport::create([
                'imported_by_player_id' => $importedByPlayerId,
                'format' => $formatKey,
                'original_filename' => $filename,
                'event_id' => $editionId,
                'included_count' => 0,
                'values_new_count' => 0,
                'values_replaced_count' => 0,
            ]);

            $valuesNew = 0;
            $valuesReplaced = 0;

            foreach ($prepared as $row) {
                $answersJson = [];
                $player = Player::query()->findOrFail($row['player_id']);

                $submission = PlayerCustomFieldSubmission::create([
                    'player_id' => $player->id_player,
                    'submitted_via' => PlayerCustomFieldSubmission::SUBMITTED_VIA_CSV,
                    'submitted_by_player_id' => $importedByPlayerId,
                ]);

                foreach ($fields as $code => $field) {
                    $answer = $row['answers'][$code] ?? '';
                    if ($answer === '') {
                        continue;
                    }

                    $hadCurrent = $this->writer->currentValue(
                        (int) $player->id_player,
                        (int) $field->id_player_custom_field
                    ) !== null;

                    $this->writer->write(
                        $submission,
                        $field,
                        $this->inputFor($field, $answer),
                        $importedByPlayerId,
                        (int) $import->id_player_custom_field_value_import,
                    );

                    if ($hadCurrent) {
                        $valuesReplaced++;
                    } else {
                        $valuesNew++;
                    }

                    $answersJson[$code] = $answer;
                }

                PlayerCustomFieldValueImportRow::create([
                    'player_custom_field_value_import_id' => $import->id_player_custom_field_value_import,
                    'row_index' => $row['index'],
                    'full_name' => $row['full_name'] !== '' ? $row['full_name'] : null,
                    'email' => $row['email'] !== '' ? $row['email'] : null,
                    'phone' => $row['phone'] !== '' ? $row['phone'] : null,
                    'player_id' => $player->id_player,
                    'action' => PlayerCustomFieldValueImportRow::ACTION_IMPORTED,
                    'answers_json' => $answersJson,
                ]);
            }

            $import->update([
                'included_count' => count($prepared),
                'values_new_count' => $valuesNew,
                'values_replaced_count' => $valuesReplaced,
            ]);

            return $import->fresh(['rows', 'importedByPlayer', 'event']) ?? $import;
        });
    }

    /**
     * Turns a CSV answer into the writer input for the field's type. Selects are matched on an
     * active option's code or label (case-insensitive).
     *
     * @return array<string, mixed>
     */
    private function inputFor(PlayerCustomField $field, string $answer): array
    {
        if ($field->type !== PlayerCustomFieldType::SingleSelect) {
            return ['value' => $answer];
        }

        $needle = mb_strtolower(trim($answer));
        $option = PlayerCustomFieldOption::query()
            ->where('player_custom_field_id', $field->id_player_custom_field)
            ->where('is_active', true)
            ->get()
            ->first(static fn (PlayerCustomFieldOption $candidate): bool => mb_strtolower((string) $candidate->code) === $needle
                || mb_strtolower((string) $candidate->label) === $needle);

        if ($option === null) {
            throw new PlayerCustomFieldValueImportFailedException(
                'Validation failed',
                ["answers.{$field->code}" => ["'{$answer}' is not a valid option for {$field->code}."]],
            );
        }

        return ['option_id' => (int) $option->id_player_custom_field_option];
    }

    /**
     * @return array<string, PlayerCustomField>
     */
    private function resolveImportFields(): array
    {
        $codes = GoogleFormMiniTourWelcomeCsvFormat::fieldCodes();
        $fields = PlayerCustomField::query()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        $errors = [];
        foreach ($codes as $code) {
            $field = $fields->get($code);
            if ($field === null) {
                $errors['fields'][] = "Missing custom field definition for {$code}.";
            } elseif ($field->isArchived()) {
                $errors['fields'][] = "Custom field {$code} is archived.";
            }
        }

        if ($errors !== []) {
            throw new PlayerCustomFieldValueImportFailedException('Validation failed', $errors);
        }

        /** @var array<string, PlayerCustomField> */
        return $fields->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{index: int, first_name: string, middle_name: ?string, last_name: ?string, email: string, phone: string}>
     */
    private function identityRows(array $rows): array
    {
        return array_map(static fn (array $row): array => [
            'index' => (int) $row['index'],
            'first_name' => (string) ($row['first_name'] ?? ''),
            'middle_name' => $row['middle_name'] ?? null,
            'last_name' => $row['last_name'] ?? null,
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
        ], $rows);
    }

    /**
     * @param  array<string, string>  $answers
     * @return array<string, string>
     */
    private function trimAnswers(array $answers): array
    {
        $trimmed = [];
        foreach ($answers as $code => $value) {
            $trimmed[$code] = trim($value);
        }

        return $trimmed;
    }

    /**
     * @return array{headers: list<string>, rows: list<array<string, string>>}
     */
    private function parseCsv(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new PlayerCustomFieldValueImportFailedException(
                'Could not read the uploaded file.',
                ['file' => ['Could not read the uploaded file.']],
            );
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new PlayerCustomFieldValueImportFailedException(
                'Could not read the uploaded file.',
                ['file' => ['Could not read the uploaded file.']],
            );
        }

        try {
            $headerLine = fgetcsv($handle);
            if ($headerLine === false || $headerLine === [null] || $headerLine === []) {
                throw new PlayerCustomFieldValueImportFailedException(
                    'Unknown CSV format. Supported: google_form_minitour_welcome.',
                    ['file' => ['Unknown CSV format. Supported: google_form_minitour_welcome.']],
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
                    throw new PlayerCustomFieldValueImportFailedException(
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
     *     full_name: string,
     *     email: string,
     *     phone: string,
     *     player_id: int,
     *     answers: array<string, string>
     * }
     */
    private function prepareSaveRow(array $row): array
    {
        $playerId = $row['player_id'] ?? null;
        if ($playerId === '' || $playerId === false || $playerId === null) {
            throw new PlayerCustomFieldValueImportFailedException(
                'Validation failed',
                ['rows.' . ($row['index'] ?? 0) . '.player_id' => ['Player is required for included rows.']],
            );
        }

        $answers = [];
        foreach ((array) ($row['answers'] ?? []) as $code => $value) {
            $answers[(string) $code] = trim(is_scalar($value) ? (string) $value : '');
        }

        return [
            'index' => (int) ($row['index'] ?? 0),
            'full_name' => trim((string) ($row['full_name'] ?? '')),
            'email' => $this->normalizeEmail(isset($row['email']) ? (string) $row['email'] : ''),
            'phone' => isset($row['phone']) && trim((string) $row['phone']) !== ''
                ? PlayerPhoneNormalizer::normalize((string) $row['phone'])
                : '',
            'player_id' => (int) $playerId,
            'answers' => $answers,
        ];
    }

    /**
     * @param  list<array{index: int, full_name: string, email: string, phone: string, player_id: int, answers: array<string, string>}>  $rows
     */
    private function assertSaveRows(array $rows): void
    {
        $errors = [];

        foreach ($rows as $row) {
            $index = $row['index'];
            $prefix = 'rows.' . $index;

            if (! Player::query()->whereKey($row['player_id'])->exists()) {
                $errors[$prefix . '.player_id'][] = 'The selected player does not exist.';
            }
        }

        if ($errors !== []) {
            throw new PlayerCustomFieldValueImportFailedException('Validation failed', $errors);
        }
    }

    private function normalizeEmail(string $raw): string
    {
        $trimmed = trim($raw);

        return $trimmed === '' ? '' : mb_strtolower($trimmed);
    }
}
