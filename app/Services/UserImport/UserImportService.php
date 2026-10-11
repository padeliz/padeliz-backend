<?php

declare(strict_types=1);

namespace App\Services\UserImport;

use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserImport;
use App\Models\UserImportRow;
use App\Models\UserPhone;
use App\Support\PlayerNameSplitter;
use App\Support\PlayerPhoneNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UserImportService
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
        private readonly UserCsvFormatRegistry $registry,
        private readonly UserImportMapper $mapper,
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
            throw new UserImportFailedException(
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
    public function save(array $payload, int $importedByPlayerId): UserImport
    {
        $formatKey = (string) ($payload['format'] ?? '');
        if ($this->registry->byKey($formatKey) === null) {
            $supported = implode(', ', $this->registry->formatKeys()) ?: 'playtomic';

            throw new UserImportFailedException(
                'Unknown format. Supported: ' . $supported . '.',
                ['format' => ['Unknown format. Supported: ' . $supported . '.']],
            );
        }

        $filename = trim((string) ($payload['filename'] ?? ''));
        if ($filename === '' || mb_strlen($filename) > 255) {
            throw new UserImportFailedException(
                'Validation failed',
                ['filename' => ['A filename of at most 255 characters is required.']],
            );
        }

        $rows = $payload['rows'] ?? [];
        if (! is_array($rows) || $rows === []) {
            throw new UserImportFailedException(
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

        return DB::transaction(function () use ($prepared, $formatKey, $filename, $importedByPlayerId): UserImport {
            $import = UserImport::create([
                'imported_by_manager_user_id' => $importedByPlayerId,
                'format' => $formatKey,
                'original_filename' => $filename,
                'included_count' => 0,
                'created_count' => 0,
                'attached_count' => 0,
            ]);

            $created = 0;
            $attached = 0;

            foreach ($prepared as $row) {
                if ($row['user_id'] === null) {
                    $player = $this->createPlayer($row, $import);
                    $this->attachContacts($player, $row['email'], $row['phone'], $row['index']);
                    $action = UserImportRow::ACTION_CREATED;
                    $created++;
                } else {
                    $player = User::query()->findOrFail($row['user_id']);
                    $originalName = $player->name;
                    $originalVia = $player->created_via;
                    $originalFormat = $player->created_via_format;
                    $originalImportId = $player->created_via_user_import_id;

                    $this->attachContacts($player, $row['email'], $row['phone'], $row['index']);

                    $player->refresh();
                    if (
                        $player->name !== $originalName
                        || $player->created_via !== $originalVia
                        || $player->created_via_format !== $originalFormat
                        || $player->created_via_user_import_id !== $originalImportId
                    ) {
                        throw new UserImportFailedException(
                            'Validation failed',
                            ['rows.' . $row['index'] => ['Mapped save must not change name, skill, or provenance.']],
                        );
                    }

                    $action = UserImportRow::ACTION_ATTACHED;
                    $attached++;
                }

                UserImportRow::create([
                    'user_import_id' => $import->id_user_import,
                    'row_index' => $row['index'],
                    'first_name' => $row['first_name'] !== '' ? $row['first_name'] : null,
                    'middle_name' => $row['middle_name'],
                    'last_name' => $row['last_name'],
                    'email' => $row['email'] !== '' ? $row['email'] : null,
                    'phone' => $row['phone'] !== '' ? $row['phone'] : null,
                    'user_id' => $player->id_user,
                    'action' => $action,
                ]);
            }

            $import->update([
                'included_count' => count($prepared),
                'created_count' => $created,
                'attached_count' => $attached,
            ]);

            return $import->fresh(['rows', 'importedByManager']) ?? $import;
        });
    }

    /**
     * @return array{headers: list<string>, rows: list<array<string, string>>}
     */
    private function parseCsv(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new UserImportFailedException(
                'Could not read the uploaded file.',
                ['file' => ['Could not read the uploaded file.']],
            );
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new UserImportFailedException(
                'Could not read the uploaded file.',
                ['file' => ['Could not read the uploaded file.']],
            );
        }

        try {
            $headerLine = fgetcsv($handle);
            if ($headerLine === false || $headerLine === [null] || $headerLine === []) {
                throw new UserImportFailedException(
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
                    throw new UserImportFailedException(
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
     * @return array{index: int, first_name: string, middle_name: ?string, last_name: ?string, email: string, phone: string, user_id: ?int}
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

        $userId = $row['user_id'] ?? null;
        if ($userId === '' || $userId === false) {
            $userId = null;
        }
        if ($userId !== null) {
            $userId = (int) $userId;
        }

        return [
            'index' => (int) ($row['index'] ?? 0),
            'first_name' => $first,
            'middle_name' => $middle === '' ? null : $middle,
            'last_name' => $last === '' ? null : $last,
            'email' => $email,
            'phone' => $phone,
            'user_id' => $userId,
        ];
    }

    /**
     * @param  list<array{index: int, first_name: string, middle_name: ?string, last_name: ?string, email: string, phone: string, user_id: ?int}>  $rows
     */
    private function assertSaveRows(array $rows): void
    {
        $errors = [];
        $seenEmails = [];
        $seenPhones = [];

        foreach ($rows as $row) {
            $index = $row['index'];
            $prefix = 'rows.' . $index;

            if ($row['user_id'] === null) {
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
            } elseif (! User::query()->whereKey($row['user_id'])->exists()) {
                $errors[$prefix . '.user_id'][] = 'The selected player does not exist.';
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

            throw new UserImportFailedException('Validation failed', $errors);
        }
    }

    /**
     * @param  array{first_name: string, middle_name: ?string, last_name: ?string}  $row
     */
    private function createPlayer(array $row, UserImport $import): User
    {
        return User::create([
            'first_name' => $row['first_name'],
            'middle_name' => $row['middle_name'],
            'last_name' => $row['last_name'],
            'user_types' => ['player'],
            'created_via' => 'csv',
            'created_via_format' => 'playtomic',
            'created_via_user_import_id' => $import->id_user_import,
        ]);
    }

    private function attachContacts(User $player, string $email, string $phone, int $index): void
    {
        if ($email !== '') {
            $this->attachEmail($player, $email, $index);
        }
        if ($phone !== '') {
            $this->attachPhone($player, $phone, $index);
        }
    }

    private function attachEmail(User $player, string $email, int $index): void
    {
        $existing = UserEmail::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->get();

        $others = $existing->filter(static fn (UserEmail $row): bool => (int) $row->user_id !== (int) $player->id_user);
        if ($others->isNotEmpty()) {
            throw new UserImportFailedException(
                'Validation failed',
                ['rows.' . $index . '.email' => ['This email already belongs to another player.']],
            );
        }

        if ($existing->isNotEmpty()) {
            return;
        }

        $hasPrimary = $player->emails()->where('is_primary', true)->exists();
        UserEmail::create([
            'user_id' => $player->id_user,
            'email' => $email,
            'purposes' => ['contact'],
            'is_primary' => ! $hasPrimary,
        ]);
    }

    private function attachPhone(User $player, string $phone, int $index): void
    {
        $matches = UserPhone::query()->get()->filter(
            static fn (UserPhone $row): bool => PlayerPhoneNormalizer::normalize((string) $row->phone) === $phone
        );

        $others = $matches->filter(static fn (UserPhone $row): bool => (int) $row->user_id !== (int) $player->id_user);
        if ($others->isNotEmpty()) {
            throw new UserImportFailedException(
                'Validation failed',
                ['rows.' . $index . '.phone' => ['This phone already belongs to another player.']],
            );
        }

        if ($matches->isNotEmpty()) {
            return;
        }

        $hasPrimary = $player->phones()->where('is_primary', true)->exists();
        UserPhone::create([
            'user_id' => $player->id_user,
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
