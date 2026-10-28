<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class RedisConnectionConfigTest extends TestCase
{
    public function test_redis_connections_define_scheme_and_username_with_tcp_default(): void
    {
        foreach (['default', 'cache'] as $connection) {
            $config = config("database.redis.{$connection}");

            $this->assertArrayHasKey('scheme', $config);
            $this->assertArrayHasKey('username', $config);
            $this->assertSame('tcp', $config['scheme']);
        }
    }
}
