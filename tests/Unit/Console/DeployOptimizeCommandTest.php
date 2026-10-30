<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Console\Commands\DeployOptimizeCommand;
use ReflectionMethod;
use Tests\TestCase;

class DeployOptimizeCommandTest extends TestCase
{
    public function test_steps_cache_config_routes_events_and_views(): void
    {
        $command = app(DeployOptimizeCommand::class);
        $method = new ReflectionMethod(DeployOptimizeCommand::class, 'steps');
        $method->setAccessible(true);

        /** @var list<array{0: string, 1: array<string, mixed>}> $steps */
        $steps = $method->invoke($command);

        $this->assertSame([
            ['config:cache', []],
            ['route:cache', []],
            ['event:cache', []],
            ['view:cache', []],
        ], $steps);
    }
}
