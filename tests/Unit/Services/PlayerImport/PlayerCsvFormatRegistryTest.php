<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerImport;

use App\Services\PlayerImport\Contracts\PlayerCsvFormat;
use App\Services\PlayerImport\Formats\PlaytomicPlayerCsvFormat;
use App\Services\PlayerImport\PlayerCsvFormatRegistry;
use PHPUnit\Framework\TestCase;

final class PlayerCsvFormatRegistryTest extends TestCase
{
    public function test_detects_first_matching_format_and_unknown_headers(): void
    {
        $first = new class () implements PlayerCsvFormat {
            public function formatKey(): string
            {
                return 'other';
            }

            public function supports(array $headers): bool
            {
                return in_array('Other', $headers, true);
            }

            public function parseRow(array $assoc): array
            {
                return ['name' => '', 'email' => '', 'phone' => ''];
            }
        };

        $registry = new PlayerCsvFormatRegistry([$first, new PlaytomicPlayerCsvFormat()]);

        $this->assertSame('playtomic', $registry->detect(['Name', 'Email', 'Phone Number'])?->formatKey());
        $this->assertNull($registry->detect(['Nope', 'Email']));
        $this->assertSame(['other', 'playtomic'], $registry->formatKeys());
    }
}
