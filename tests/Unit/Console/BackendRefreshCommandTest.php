<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Console\Commands\BackendRefreshCommand;
use ReflectionMethod;
use Tests\TestCase;

class BackendRefreshCommandTest extends TestCase
{
    public function test_dev_steps_clear_stale_state_and_regenerate_docs(): void
    {
        $steps = $this->stepsFor(deploy: false);

        $this->assertSame([
            ['optimize:clear', []],
            ['api:docs:generate-all', []],
        ], $steps);
    }

    public function test_deploy_steps_migrate_clear_rebuild_caches_and_regenerate_docs(): void
    {
        $steps = $this->stepsFor(deploy: true);

        $this->assertSame([
            ['migrate', ['--force' => true]],
            ['optimize:clear', []],
            ['config:cache', []],
            ['route:cache', []],
            ['event:cache', []],
            ['queue:restart', []],
            ['api:docs:generate-all', []],
        ], $steps);
    }

    /**
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    private function stepsFor(bool $deploy): array
    {
        $command = app(BackendRefreshCommand::class);
        $method = new ReflectionMethod(BackendRefreshCommand::class, 'steps');
        $method->setAccessible(true);

        /** @var list<array{0: string, 1: array<string, mixed>}> $steps */
        $steps = $method->invoke($command, $deploy);

        return $steps;
    }
}
