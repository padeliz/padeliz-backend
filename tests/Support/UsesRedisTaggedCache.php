<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Force the Redis cache store for tests that exercise PadelizCache tag semantics.
 * The PHPUnit default is the in-process array store for suite speed.
 */
trait UsesRedisTaggedCache
{
    protected function forceRedisTaggedCache(): void
    {
        config(['cache.default' => 'redis']);
    }
}
