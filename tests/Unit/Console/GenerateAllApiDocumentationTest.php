<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Console\Commands\GenerateAllApiDocumentation;
use ReflectionMethod;
use Tests\TestCase;

final class GenerateAllApiDocumentationTest extends TestCase
{
    public function test_get_api_versions_discovers_route_files_sorted_numerically(): void
    {
        $command = app(GenerateAllApiDocumentation::class);
        $method = new ReflectionMethod(GenerateAllApiDocumentation::class, 'getApiVersions');
        $method->setAccessible(true);

        /** @var list<string> $versions */
        $versions = $method->invoke($command);

        $this->assertContains('v1', $versions);
        $this->assertSame($versions, array_values(array_unique($versions)));

        $numbers = array_map(static fn (string $v): int => (int) substr($v, 1), $versions);
        $sorted = $numbers;
        sort($sorted, SORT_NUMERIC);
        $this->assertSame($sorted, $numbers);
    }
}
