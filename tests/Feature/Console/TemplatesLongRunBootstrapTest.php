<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\TemplatesLongRunBootstrap;
use Tests\TestCase;

final class TemplatesLongRunBootstrapTest extends TestCase
{
    private ?string $previousMemoryLimit = null;

    protected function tearDown(): void
    {
        if ($this->previousMemoryLimit !== null) {
            ini_set('memory_limit', $this->previousMemoryLimit);
        }

        parent::tearDown();
    }

    public function test_apply_sets_memory_limit_to_512m(): void
    {
        $this->previousMemoryLimit = ini_get('memory_limit');

        TemplatesLongRunBootstrap::apply();

        $this->assertSame('512M', ini_get('memory_limit'));
    }
}
