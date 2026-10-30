<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Console\Commands\BackendRefreshCommand;
use ReflectionMethod;
use Tests\TestCase;

class BackendRefreshCommandTest extends TestCase
{
    public function test_steps_clear_stale_state_and_regenerate_docs(): void
    {
        $command = app(BackendRefreshCommand::class);
        $method = new ReflectionMethod(BackendRefreshCommand::class, 'steps');
        $method->setAccessible(true);

        /** @var list<array{0: string, 1: array<string, mixed>}> $steps */
        $steps = $method->invoke($command);

        $this->assertSame([
            ['optimize:clear', []],
            ['api:docs:generate-all', []],
        ], $steps);
    }
}
