<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PlayerNameSplitter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlayerNameSplitterTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: array{first_name: string, middle_name: ?string, last_name: ?string}}>
     */
    public static function splitCases(): array
    {
        return [
            'two tokens' => [
                'Vlad Mitu',
                [
                    'first_name' => 'Vlad',
                    'middle_name' => null,
                    'last_name' => 'Mitu',
                ],
            ],
            'initial middle' => [
                'Bogdan I. Chiagă',
                [
                    'first_name' => 'Bogdan',
                    'middle_name' => 'I.',
                    'last_name' => 'Chiagă',
                ],
            ],
            'single token' => [
                'Arșavin',
                [
                    'first_name' => 'Arșavin',
                    'middle_name' => null,
                    'last_name' => null,
                ],
            ],
            'keeps captain suffix in last name' => [
                'Ion Popescu (Captain)',
                [
                    'first_name' => 'Ion',
                    'middle_name' => 'Popescu',
                    'last_name' => '(Captain)',
                ],
            ],
            'multiple middle initials' => [
                'Costin H. P. Pintilie',
                [
                    'first_name' => 'Costin',
                    'middle_name' => 'H. P.',
                    'last_name' => 'Pintilie',
                ],
            ],
            'unicode accents' => [
                'Ștefan Țiriac',
                [
                    'first_name' => 'Ștefan',
                    'middle_name' => null,
                    'last_name' => 'Țiriac',
                ],
            ],
            'trims and collapses whitespace' => [
                '  Ana   Maria  Popescu  ',
                [
                    'first_name' => 'Ana',
                    'middle_name' => 'Maria',
                    'last_name' => 'Popescu',
                ],
            ],
        ];
    }

    /**
     * @param  array{first_name: string, middle_name: ?string, last_name: ?string}  $expected
     */
    #[DataProvider('splitCases')]
    public function test_split_table(string $raw, array $expected): void
    {
        $this->assertSame($expected, PlayerNameSplitter::split($raw));
    }

    public function test_throws_when_part_exceeds_max_length(): void
    {
        $tooLong = str_repeat('A', PlayerNameSplitter::MAX_PART_LENGTH + 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('first_name exceeds');

        PlayerNameSplitter::split($tooLong);
    }

    public function test_throws_when_middle_name_exceeds_max_length(): void
    {
        $tooLongMiddle = str_repeat('M', PlayerNameSplitter::MAX_PART_LENGTH + 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('middle_name exceeds');

        PlayerNameSplitter::split("First {$tooLongMiddle} Last");
    }

    public function test_split_for_preview_empty_returns_empty_first_name(): void
    {
        $this->assertSame(
            [
                'first_name' => '',
                'middle_name' => null,
                'last_name' => null,
            ],
            PlayerNameSplitter::splitForPreview('   ')
        );
    }

    public function test_split_for_preview_returns_overlong_tokens(): void
    {
        $tooLong = str_repeat('A', PlayerNameSplitter::MAX_PART_LENGTH + 1);
        $parts = PlayerNameSplitter::splitForPreview($tooLong);

        $this->assertSame($tooLong, $parts['first_name']);
        $this->assertNull($parts['middle_name']);
        $this->assertNull($parts['last_name']);
    }

    public function test_split_for_preview_token_shapes(): void
    {
        $this->assertSame(
            ['first_name' => 'Mara', 'middle_name' => null, 'last_name' => null],
            PlayerNameSplitter::splitForPreview('Mara')
        );
        $this->assertSame(
            ['first_name' => 'Mara', 'middle_name' => null, 'last_name' => 'Popescu'],
            PlayerNameSplitter::splitForPreview('Mara Popescu')
        );
        $this->assertSame(
            ['first_name' => 'Ana', 'middle_name' => 'Maria', 'last_name' => 'Popescu'],
            PlayerNameSplitter::splitForPreview('Ana Maria Popescu')
        );
    }
}
