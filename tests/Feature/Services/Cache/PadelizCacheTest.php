<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Cache;

use App\Services\Cache\MetadataCacheInvalidator;
use App\Services\Cache\PadelizCache;
use Tests\TestCase;

final class PadelizCacheTest extends TestCase
{
    public function test_remember_hit_and_tag_flush(): void
    {
        $cache = app(PadelizCache::class);
        $calls = 0;

        $first = $cache->remember('padeliz:test:1', 60, ['test-a'], function () use (&$calls) {
            ++$calls;

            return ['n' => 1];
        });

        $second = $cache->remember('padeliz:test:1', 60, ['test-a'], function () use (&$calls) {
            ++$calls;

            return ['n' => 2];
        });

        $this->assertSame(['n' => 1], $first);
        $this->assertSame(['n' => 1], $second);
        $this->assertSame(1, $calls);

        $cache->flushTags(['test-a']);
        $third = $cache->remember('padeliz:test:1', 60, ['test-a'], function () use (&$calls) {
            ++$calls;

            return ['n' => 3];
        });

        $this->assertSame(['n' => 3], $third);
        $this->assertSame(2, $calls);
    }

    public function test_flush_application_tags(): void
    {
        $cache = app(PadelizCache::class);
        $cache->remember('padeliz:app:1', 60, ['app-only'], fn () => 'x');
        $cache->flushApplicationTags();
        $this->assertFalse($cache->has('padeliz:app:1', ['app-only']));
    }

    public function test_flush_tags_does_not_wipe_unrelated_application_entries(): void
    {
        $cache = app(PadelizCache::class);
        $cache->flushApplicationTags();

        $cache->put('keep-me', 'A', 60, ['alpha']);
        $cache->put('wipe-me', 'B', 60, ['beta']);

        $this->assertTrue($cache->has('keep-me', ['alpha']));
        $this->assertTrue($cache->has('wipe-me', ['beta']));

        $cache->flushTags(['beta']);

        $this->assertTrue($cache->has('keep-me', ['alpha']));
        $this->assertFalse($cache->has('wipe-me', ['beta']));
    }

    public function test_metadata_invalidator_flushes_schema(): void
    {
        $cache = app(PadelizCache::class);
        $invalidator = app(MetadataCacheInvalidator::class);

        $cache->remember('metadata:v1:Club', 60, ['metadata', 'metadata:Club'], fn () => ['schema' => 'Club']);
        $this->assertTrue($cache->has('metadata:v1:Club', ['metadata', 'metadata:Club']));

        $invalidator->invalidateSchema('Club');
        $this->assertFalse($cache->has('metadata:v1:Club', ['metadata', 'metadata:Club']));
    }
}
