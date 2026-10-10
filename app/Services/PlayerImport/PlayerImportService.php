<?php

declare(strict_types=1);

namespace App\Services\PlayerImport;

use App\Models\Player;
use App\Models\PlayerEmail;
use App\Models\PlayerImport;
use App\Models\PlayerImportRow;
use App\Models\PlayerPhone;
use App\Support\PlayerNameSplitter;
use App\Support\PlayerPhoneNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PlayerImportService
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
    public function preview(UploadedFile $file): array
    {
        $this->assertPreviewFile($file);

        $parsed = $this->parseCsv($file);
        $format = $this->registry->detect($parsed['headers']);
        if ($format === null) {
            throw new PlayerImportFailedException(
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

        return [
            'format' => $format->formatKey(),
            'rows' => $mapped,
        ];
    }

    /**
     * @param  array{format: string, filename: string, rows: list<array<string, mixed>>}  $payload
     */
    public function save(array $payload, int $importedByPlayerId): PlayerImport
    {
        $formatKey = (string) ($payload['format'] ?? '');
        if ($this->registry->byKey($formatKey) === null) {
            $supported = implode(', ', $this->registry->formatKeys()) ?: 'playtomic';

            throw new PlayerImportFailedException(
                'Unknown format. Supported: ' . $supported . '.',
                ['format' => ['Unknown format. Supported: ' . $supported . '.']],
            );
        }

        $filename = trim((string) ($payload['filename'] ?? ''));
        if ($filename === '' || mb_strlen($filename) > 255) {
            throw new PlayerImportFailedException(
                'Validation failed',
                ['filename' => ['A filename of at most 255 characters is required.']],
            );
        }

        $rows = $payload['rows'] ?? [];
        if (! is_array($rows) || $rows === []) {
            throw new PlayerImportFailedException(
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

        return DB::transaction(function () use ($prepared, $formatKey, $filename, $importedByPlayerId): PlayerImport {
            $import = PlayerImport::create([
                'imported_by_player_id' => $importedByPlayerId,
                'format' => $formatKey,
                'original_filename' => $filename,
                'included_count' => 0,
                'created_count' => 0,
                'attached_count' => 0,
            ]);

            $created = 0;
            $attached = 0;

            foreach ($prepared as $row) {
                if ($row['player_id'] === null) {
                    $player = $this->createPlayer($row, $import);
                    $this->attachContacts($player, $row['email'], $row['phone'], $row['index']);
                    $action = PlayerImportRow::ACTION_CREATED;
                    $created++;
                } else {
                    $player = Player::query()->findOrFail($row['player_id']);
                    $originalName = $player->name;
                    $originalVia = $player->created_via;
                    $originalFormat = $player->created_via_format;
                    $originalImportId = $player->created_via_import_id;

                    $this->attachContacts($player, $row['email'], $row['phone'], $row['index']);

                    $player->refresh();
                    if (
                        $player->name !== $originalName
                        || $player->created_via !== $originalVia
                        || $player->created_via_format !== $originalFormat
                        || $player->created_via_import_id !== $originalImportId
                    ) {
                        throw new PlayerImportFailedException(
                            'Validation failed',
                            ['rows.' . $row['index'] => ['Mapped save must not change name, skill, or provenance.']],
                        );
                    }

                    $action = PlayerImportRow::ACTION_ATTACHED;
                    $attached++;
                }

                PlayerImportRow::create([
                    'player_import_id' => $import->id_player_import,
                    'row_index' => $row['index'],
                    'first_name' => $row['first_name'] !== '' ? $row['first_name'] : null,
                    'middle_name' => $row['middle_name'],
                    'last_name' => $row['last_name'],
                    'email' => $row['email'] !== '' ? $row['email'] : null,
                    'phone' => $row['phone'] !== '' ? $row['phone'] : null,
                    'player_id' => $player->id_player,
                    'action' => $action,
                ]);
            }

            $import->update([
                'included_count' => count($prepared),
                'created_count' => $created,
                'attached_count' => $attached,
            ]);

            return $import->fresh(['rows', 'importedByPlayer']) ?? $import;
        });
    }

    /**
     * @return array{headers: list<string>, rows: list<array<string, string>>}
     */
    private function parseCsv(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new PlayerImportFailedException(
                'Could not read the uploaded file.',
                ['file' => ['Could not read the uploaded file.']],
            );
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new PlayerImportFailedException(
                'Could not read the uploaded file.',
                ['file' => ['Could not read the uploaded file.']],
            );
        }

        try {
            $headerLine = fgetcsv($handle);
            if ($headerLine === false || $headerLine === [null] || $headerLine === []) {
                throw new PlayerImportFailedException(
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
                    throw new PlayerImportFailedException(
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
     * @return array{index: int, first_name: string, middle_name: ?string, last_name: ?string, email: string, phone: string, player_id: ?int}
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

        return [
            'index' => (int) ($row['index'] ?? 0),
            'first_name' => $first,
            'middle_name' => $middle === '' ? null : $middle,
            'last_name' => $last === '' ? null : $last,
            'email' => $email,
            'phone' => $phone,
            'player_id' => $playerId,
        ];
    }

    /**
     * @param  list<array{index: int, first_name: string, middle_name: ?string, last_name: ?string, email: string, phone: string, player_id: ?int}>  $rows
     */
    private function assertSaveRows(array $rows): void
    {
        $errors = [];
        $seenEmails = [];
        $seenPhones = [];

        foreach ($rows as $row) {
            $index = $row['index'];
            $prefix = 'rows.' . $index;

            if ($row['player_id'] === null) {
                if ($row['first_name'] === '') {
                    $errors[$prefix . '.first_name'][] = 'First name is required to create a player.';
                }
                if (mb_strlen($row['first_name']) > 50) {
                    $errors[$prefix . '.first_name'][] = 'The first name may not be greater than 50 characters.';
                }
                if ($row['middle_name'] !== null && mb_strlen($row['middle_name']) > 50) {
                    $errors[$prefix . '.middle_name'][] = 'The middle name may not be greater than 50 characters.';
                }
                if ($row['last_name'] !== null && mb_strlen($row['last_name']) > 50) {
                    $errors[$prefix . '.last_name'][] = 'The last name may not be greater than 50 characters.';
                }
                if ($row['email'] === '' && $row['phone'] === '') {
                    $errors[$prefix][] = 'An email or phone is required to create a player.';
                }
            } elseif (! Player::query()->whereKey($row['player_id'])->exists()) {
                $errors[$prefix . '.player_id'][] = 'The selected player does not exist.';
            }

            if ($row['email'] !== '') {
                if (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[$prefix . '.email'][] = 'The email must be a valid email address.';
                }
                if (isset($seenEmails[$row['email']])) {
                    $errors[$prefix . '.email'][] = 'Duplicate email in this import.';
                    $errors['rows.' . $seenEmails[$row['email']] . '.email'][] = 'Duplicate email in this import.';
                } else {
                    $seenEmails[$row['email']] = $index;
                }
            }

            if ($row['phone'] !== '') {
                if (mb_strlen($row['phone']) > 30) {
                    $errors[$prefix . '.phone'][] = 'The phone may not be greater than 30 characters.';
                }
                if (isset($seenPhones[$row['phone']])) {
                    $errors[$prefix . '.phone'][] = 'Duplicate phone in this import.';
                    $errors['rows.' . $seenPhones[$row['phone']] . '.phone'][] = 'Duplicate phone in this import.';
                } else {
                    $seenPhones[$row['phone']] = $index;
                }
            }
        }

        if ($errors !== []) {
            foreach ($errors as $key => $messages) {
                $errors[$key] = array_values(array_unique($messages));
            }

            throw new PlayerImportFailedException('Validation failed', $errors);
        }
    }

    /**
     * @param  array{first_name: string, middle_name: ?string, last_name: ?string}  $row
     */
    private function createPlayer(array $row, PlayerImport $import): Player
    {
        return Player::create([
            'first_name' => $row['first_name'],
            'middle_name' => $row['middle_name'],
            'last_name' => $row['last_name'],
            'user_types' => ['player'],
            'created_via' => 'csv',
            'created_via_format' => 'playtomic',
            'created_via_import_id' => $import->id_player_import,
        ]);
    }

    private function attachContacts(Player $player, string $email, string $phone, int $index): void
    {
        if ($email !== '') {
            $this->attachEmail($player, $email, $index);
        }
        if ($phone !== '') {
            $this->attachPhone($player, $phone, $index);
        }
    }

    private function attachEmail(Player $player, string $email, int $index): void
    {
        $existing = PlayerEmail::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->get();

        $others = $existing->filter(static fn (PlayerEmail $row): bool => (int) $row->player_id !== (int) $player->id_player);
        if ($others->isNotEmpty()) {
            throw new PlayerImportFailedException(
                'Validation failed',
                ['rows.' . $index . '.email' => ['This email already belongs to another player.']],
            );
        }

        if ($existing->isNotEmpty()) {
            return;
        }

        $hasPrimary = $player->emails()->where('is_primary', true)->exists();
        PlayerEmail::create([
            'player_id' => $player->id_player,
            'email' => $email,
            'purposes' => ['contact'],
            'is_primary' => ! $hasPrimary,
        ]);
    }

    private function attachPhone(Player $player, string $phone, int $index): void
    {
        $matches = PlayerPhone::query()->get()->filter(
            static fn (PlayerPhone $row): bool => PlayerPhoneNormalizer::normalize((string) $row->phone) === $phone
        );

        $others = $matches->filter(static fn (PlayerPhone $row): bool => (int) $row->player_id !== (int) $player->id_player);
        if ($others->isNotEmpty()) {
            throw new PlayerImportFailedException(
                'Validation failed',
                ['rows.' . $index . '.phone' => ['This phone already belongs to another player.']],
            );
        }

        if ($matches->isNotEmpty()) {
            return;
        }

        $hasPrimary = $player->phones()->where('is_primary', true)->exists();
        PlayerPhone::create([
            'player_id' => $player->id_player,
            'phone' => $phone,
            'purposes' => ['contact'],
            'is_primary' => ! $hasPrimary,
        ]);
    }

    private function normalizeEmail(string $raw): string
    {
        $trimmed = trim($raw);

        return $trimmed === '' ? '' : mb_strtolower($trimmed);
    }
}
