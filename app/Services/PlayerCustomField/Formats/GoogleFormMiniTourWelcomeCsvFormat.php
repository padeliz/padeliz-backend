<?php

declare(strict_types=1);

namespace App\Services\PlayerCustomField\Formats;

use App\Services\PlayerCustomField\Contracts\PlayerCustomFieldValueCsvFormat;

final class GoogleFormMiniTourWelcomeCsvFormat implements PlayerCustomFieldValueCsvFormat
{
    public const FORMAT_KEY = 'google_form_minitour_welcome';

    private const HEADER_TIMESTAMP = 'Timestamp';

    private const HEADER_USERNAME = 'Username';

    private const HEADER_FULL_NAME = 'Salutare 👋 care este numele tău complet?';

    private const HEADER_PHONE = 'La ce număr de telefon dăm de tine?';

    private const HEADER_BALANCE_NOTE = 'Folosim informațiile în a echilibra diviziile cât mai bine';

    private const HEADER_POSTER_PHOTO = 'Poza este strict pentru afișul cu meciurile';

    /**
     * @var array<string, string>
     */
    private const FIELD_HEADERS = [
        'padel_matches_played' => 'Câte meciuri de padel ai mai jucat?',
        'tennis_experience' => 'Ai jucat tenis vreodată?',
        'racket_lessons' => 'Ai luat lecții de padel sau tenis?',
        'big_tournament_division' => 'În caz că ai participat la turnee mari, în ce divizie te încadrezi?',
        'age' => 'Ce vârstă ai?',
        'playtomic_url' => 'Ajută-ne cu linkul profilului tău de Playtomic',
    ];

    public function formatKey(): string
    {
        return self::FORMAT_KEY;
    }

    public function supports(array $headers): bool
    {
        $normalized = $this->normalizeHeaders($headers);
        $required = array_merge(
            [
                self::HEADER_TIMESTAMP,
                self::HEADER_USERNAME,
                self::HEADER_FULL_NAME,
                self::HEADER_PHONE,
                self::HEADER_BALANCE_NOTE,
                self::HEADER_POSTER_PHOTO,
            ],
            array_values(self::FIELD_HEADERS)
        );

        foreach ($required as $header) {
            if (! in_array($header, $normalized, true)) {
                return false;
            }
        }

        return true;
    }

    public function parseRow(array $assoc): array
    {
        $map = [];
        foreach ($assoc as $header => $value) {
            $map[$this->normalizeHeader((string) $header)] = is_string($value) ? $value : (string) $value;
        }

        $answers = [];
        foreach (self::FIELD_HEADERS as $code => $question) {
            $answers[$code] = trim($map[$question] ?? '');
        }

        return [
            'full_name' => trim($map[self::HEADER_FULL_NAME] ?? ''),
            'email' => trim($map[self::HEADER_USERNAME] ?? ''),
            'phone' => trim($map[self::HEADER_PHONE] ?? ''),
            'answers' => $answers,
        ];
    }

    /**
     * @return list<string>
     */
    public static function fieldCodes(): array
    {
        return array_keys(self::FIELD_HEADERS);
    }

    /**
     * @param  list<string>  $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(fn (string $header): string => $this->normalizeHeader($header), $headers);
    }

    private function normalizeHeader(string $header): string
    {
        $value = trim($header);
        if (str_starts_with($value, "\u{FEFF}")) {
            $value = substr($value, strlen("\u{FEFF}"));
        }
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            $value = substr($value, 3);
        }

        return $value;
    }
}
