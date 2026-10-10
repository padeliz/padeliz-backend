<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerCustomField;

use App\Services\PlayerCustomField\Formats\GoogleFormMiniTourWelcomeCsvFormat;
use PHPUnit\Framework\TestCase;

final class GoogleFormMiniTourWelcomeCsvFormatTest extends TestCase
{
    private GoogleFormMiniTourWelcomeCsvFormat $format;

    protected function setUp(): void
    {
        parent::setUp();
        $this->format = new GoogleFormMiniTourWelcomeCsvFormat();
    }

    public function test_field_codes_are_the_new_custom_fields_only(): void
    {
        $this->assertSame(
            [
                'racket_experience',
                'big_tournament_division',
                'year_of_birth',
                'playtomic_id',
                'rankedin_id',
                'gender',
                'instagram_username',
                'facebook_username',
            ],
            GoogleFormMiniTourWelcomeCsvFormat::fieldCodes()
        );
    }

    public function test_supports_the_new_headers(): void
    {
        $this->assertTrue($this->format->supports($this->fixtureHeaders()));
    }

    public function test_rejects_any_old_format_header(): void
    {
        foreach ([
            'Câte meciuri de padel ai mai jucat?',
            'Ai jucat tenis vreodată?',
            'Ai luat lecții de padel sau tenis?',
            'Ce vârstă ai?',
            'Ajută-ne cu linkul profilului tău de Playtomic',
        ] as $legacyHeader) {
            $this->assertFalse(
                $this->format->supports([...$this->fixtureHeaders(), $legacyHeader]),
                "A file carrying the old header \"{$legacyHeader}\" must be rejected"
            );
        }
    }

    public function test_rejects_a_file_missing_new_headers(): void
    {
        $headers = array_values(array_filter(
            $this->fixtureHeaders(),
            static fn (string $header): bool => $header !== 'Care este genul tău?'
        ));

        $this->assertFalse($this->format->supports($headers));
    }

    public function test_rejects_the_complete_old_header_row(): void
    {
        $this->assertFalse($this->format->supports([
            'Timestamp',
            'Username',
            'Salutare 👋 care este numele tău complet?',
            'Câte meciuri de padel ai mai jucat?',
            'Ai jucat tenis vreodată?',
            'Ai luat lecții de padel sau tenis?',
            'În caz că ai participat la turnee mari, în ce divizie te încadrezi?',
            'Ce vârstă ai?',
            'La ce număr de telefon dăm de tine?',
            'Ajută-ne cu linkul profilului tău de Playtomic',
            'Folosim informațiile în a echilibra diviziile cât mai bine',
            'Poza este strict pentru afișul cu meciurile',
        ]));
    }

    public function test_parse_row_maps_answers_by_new_field_code(): void
    {
        $headers = $this->fixtureHeaders();
        $row = array_combine($headers, array_fill(0, count($headers), ''));
        $row['Username'] = 'ana.demo@example.test';
        $row['Salutare 👋 care este numele tău complet?'] = 'Ana Demo';
        $row['În ce an te-ai născut?'] = '1998';
        $row['Care este genul tău?'] = 'female';

        $parsed = $this->format->parseRow($row);

        $this->assertSame('Ana Demo', $parsed['full_name']);
        $this->assertSame('ana.demo@example.test', $parsed['email']);
        $this->assertSame(GoogleFormMiniTourWelcomeCsvFormat::fieldCodes(), array_keys($parsed['answers']));
        $this->assertSame('1998', $parsed['answers']['year_of_birth']);
        $this->assertSame('female', $parsed['answers']['gender']);
        $this->assertSame('', $parsed['answers']['playtomic_id']);
    }

    /**
     * @return list<string>
     */
    private function fixtureHeaders(): array
    {
        $path = dirname(__DIR__, 3) . '/Fixtures/player-custom-field-value-import/google_form_minitour_welcome_sample.csv';
        $firstLine = strtok((string) file_get_contents($path), "\n");

        return str_getcsv($firstLine === false ? '' : $firstLine);
    }
}
