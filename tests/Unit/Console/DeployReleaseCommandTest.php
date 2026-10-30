<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Console\Commands\DeployReleaseCommand;
use ReflectionMethod;
use Tests\TestCase;

class DeployReleaseCommandTest extends TestCase
{
    public function test_steps_migrate_and_restart_queues(): void
    {
        $command = app(DeployReleaseCommand::class);
        $method = new ReflectionMethod(DeployReleaseCommand::class, 'steps');
        $method->setAccessible(true);

        /** @var list<array{0: string, 1: array<string, mixed>}> $steps */
        $steps = $method->invoke($command);

        $this->assertSame([
            ['migrate', ['--force' => true]],
            ['queue:restart', []],
        ], $steps);
    }
}
