<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerCustomField;

use App\Services\PlayerCustomField\PlayerCustomFieldValueWriter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RacketExperienceValidationTest extends TestCase
{
    /** @var list<string> */
    private const SPORTS = ['padel', 'tennis', 'badminton', 'squash', 'ping-pong', 'pickleball'];

    /** @var list<string> */
    private const LEVELS = ['amateur', 'intermediate', 'advanced', 'professional'];

    public function test_entry_with_sport_level_and_one_measure_is_accepted(): void
    {
        $normalized = $this->normalize([
            ['sport' => 'padel', 'level' => 'intermediate', 'matches_played' => 30],
        ]);

        $this->assertSame(
            [['sport' => 'padel', 'level' => 'intermediate', 'matches_played' => 30]],
            $normalized
        );
    }

    public function test_all_three_measures_are_kept_and_typed(): void
    {
        $normalized = $this->normalize([
            [
                'sport' => 'tennis',
                'level' => 'advanced',
                'years_of_playing' => '2.5',
                'matches_played' => '40',
                'lessons_had' => '6',
            ],
        ]);

        $this->assertSame([[
            'sport' => 'tennis',
            'level' => 'advanced',
            'years_of_playing' => 2.5,
            'matches_played' => 40,
            'lessons_had' => 6,
        ]], $normalized);
    }

    public function test_json_string_input_is_decoded(): void
    {
        $normalized = $this->normalize('[{"sport":"squash","level":"amateur","lessons_had":3}]');

        $this->assertSame(
            [['sport' => 'squash', 'level' => 'amateur', 'lessons_had' => 3]],
            $normalized
        );
    }

    public function test_sport_is_required(): void
    {
        $this->expectValidationMessage(
            [['level' => 'amateur', 'matches_played' => 1]],
            'A sport is required.'
        );
    }

    public function test_level_is_required(): void
    {
        $this->expectValidationMessage(
            [['sport' => 'padel', 'matches_played' => 1]],
            'A level is required.'
        );
    }

    public function test_at_least_one_measure_is_required(): void
    {
        $this->expectValidationMessage(
            [['sport' => 'padel', 'level' => 'amateur']],
            'At least one of years_of_playing, matches_played or lessons_had is required.'
        );
    }

    public function test_blank_measures_do_not_count_as_provided(): void
    {
        $this->expectValidationMessage(
            [[
                'sport' => 'padel',
                'level' => 'amateur',
                'years_of_playing' => null,
                'matches_played' => '',
                'lessons_had' => null,
            ]],
            'At least one of years_of_playing, matches_played or lessons_had is required.'
        );
    }

    public function test_unknown_sport_is_rejected(): void
    {
        $this->expectValidationMessage(
            [['sport' => 'chess', 'level' => 'amateur', 'matches_played' => 1]],
            'The selected sport is invalid.'
        );
    }

    public function test_unknown_level_is_rejected(): void
    {
        $this->expectValidationMessage(
            [['sport' => 'padel', 'level' => 'godlike', 'matches_played' => 1]],
            'The selected level is invalid.'
        );
    }

    public function test_duplicate_sports_are_rejected(): void
    {
        $this->expectValidationMessage(
            [
                ['sport' => 'padel', 'level' => 'amateur', 'matches_played' => 1],
                ['sport' => 'padel', 'level' => 'advanced', 'matches_played' => 2],
            ],
            'Each sport may only be reported once.'
        );
    }

    public function test_negative_and_non_numeric_measures_are_rejected(): void
    {
        $this->expectValidationMessage(
            [['sport' => 'padel', 'level' => 'amateur', 'matches_played' => -1]],
            'The value must be at least 0.'
        );

        $this->expectValidationMessage(
            [['sport' => 'padel', 'level' => 'amateur', 'matches_played' => 'many']],
            'The value must be a number.'
        );
    }

    public function test_empty_payloads_are_rejected(): void
    {
        $this->expectValidationMessage([], 'At least one racket experience entry is required.');
        $this->expectValidationMessage('not json', 'At least one racket experience entry is required.');
        $this->expectValidationMessage(
            ['padel' => ['sport' => 'padel']],
            'At least one racket experience entry is required.'
        );
    }

    public function test_entries_must_be_objects(): void
    {
        $this->expectValidationMessage(['padel'], 'Each racket experience entry must be an object.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalize(mixed $raw): array
    {
        return PlayerCustomFieldValueWriter::normalizeRacketExperience($raw, self::SPORTS, self::LEVELS);
    }

    private function expectValidationMessage(mixed $raw, string $expected): void
    {
        try {
            $this->normalize($raw);
            $this->fail("Expected a validation error: {$expected}");
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->errors() as $fieldMessages) {
                $messages = array_merge($messages, $fieldMessages);
            }

            $this->assertContains($expected, $messages);
        }
    }
}
