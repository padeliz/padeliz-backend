<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserImport;

use App\Services\UserImport\Contracts\UserCsvFormat;
use App\Services\UserImport\Formats\PlaytomicUserCsvFormat;
use App\Services\UserImport\UserCsvFormatRegistry;
use PHPUnit\Framework\TestCase;

final class UserCsvFormatRegistryTest extends TestCase
{
    public function test_detects_first_matching_format_and_unknown_headers(): void
    {
        $first = new class () implements UserCsvFormat {
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

        $registry = new UserCsvFormatRegistry([$first, new PlaytomicUserCsvFormat()]);

        $this->assertSame('playtomic', $registry->detect(['Name', 'Email', 'Phone Number'])?->formatKey());
        $this->assertNull($registry->detect(['Nope', 'Email']));
        $this->assertSame(['other', 'playtomic'], $registry->formatKeys());
    }
}
