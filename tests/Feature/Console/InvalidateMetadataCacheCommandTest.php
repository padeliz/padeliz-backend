<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\Cache\PadelizCache;
use Tests\Support\UsesRedisTaggedCache;
use Tests\TestCase;

final class InvalidateMetadataCacheCommandTest extends TestCase
{
    use UsesRedisTaggedCache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forceRedisTaggedCache();
        app(PadelizCache::class)->flushApplicationTags();
    }

    public function test_invalidates_all_metadata(): void
    {
        $cache = app(PadelizCache::class);
        $cache->remember('metadata:v1:Club', 60, ['metadata', 'metadata:Club'], fn () => ['x' => 1]);

        $this->artisan('padeliz:cache:invalidate-metadata')
            ->assertSuccessful();

        $this->assertFalse($cache->has('metadata:v1:Club', ['metadata', 'metadata:Club']));
    }

    public function test_invalidates_single_schema(): void
    {
        $cache = app(PadelizCache::class);
        $cache->remember('metadata:v1:Club', 60, ['metadata', 'metadata:Club'], fn () => ['x' => 1]);
        $cache->remember('metadata:v1:Player', 60, ['metadata', 'metadata:Player'], fn () => ['x' => 2]);

        $this->artisan('padeliz:cache:invalidate-metadata', ['--schema' => 'Club'])
            ->assertSuccessful();

        $this->assertFalse($cache->has('metadata:v1:Club', ['metadata', 'metadata:Club']));
    }

    public function test_scope_application_flushes_app_tags(): void
    {
        $cache = app(PadelizCache::class);
        $cache->remember('any:key', 60, ['misc'], fn () => 'v');

        $this->artisan('padeliz:cache:invalidate-metadata', ['--scope' => 'application'])
            ->assertSuccessful();

        $this->assertFalse($cache->has('any:key', ['misc']));
    }
}
