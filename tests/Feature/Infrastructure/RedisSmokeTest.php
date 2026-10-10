<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Services\Cache\PadelizCache;
use Illuminate\Support\Facades\Redis;
use Tests\Support\UsesRedisTaggedCache;
use Tests\TestCase;

final class RedisSmokeTest extends TestCase
{
    use UsesRedisTaggedCache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forceRedisTaggedCache();
    }

    public function test_redis_ping_and_tagged_cache(): void
    {
        $this->assertTrue(Redis::connection()->ping() !== false);

        $cache = app(PadelizCache::class);
        $value = $cache->remember('smoke:test', 60, ['smoke'], fn () => 'ok');

        $this->assertSame('ok', $value);
        $this->assertTrue($cache->has('smoke:test', ['smoke']));

        $cache->flushTags(['smoke']);
        $this->assertFalse($cache->has('smoke:test', ['smoke']));
    }
}
