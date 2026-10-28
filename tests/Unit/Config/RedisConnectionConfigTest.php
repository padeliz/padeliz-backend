<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class RedisConnectionConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('REDIS_SCHEME');
        putenv('REDIS_USERNAME');
        unset($_ENV['REDIS_SCHEME'], $_ENV['REDIS_USERNAME'], $_SERVER['REDIS_SCHEME'], $_SERVER['REDIS_USERNAME']);

        parent::tearDown();
    }

    public function test_redis_connections_define_scheme_and_username_with_tcp_default(): void
    {
        foreach (['default', 'cache'] as $connection) {
            $config = config("database.redis.{$connection}");

            $this->assertArrayHasKey('scheme', $config);
            $this->assertArrayHasKey('username', $config);
            $this->assertSame('tcp', $config['scheme']);
        }
    }

    public function test_redis_scheme_and_username_come_from_environment(): void
    {
        putenv('REDIS_SCHEME=tls');
        putenv('REDIS_USERNAME=default');
        $_ENV['REDIS_SCHEME'] = 'tls';
        $_ENV['REDIS_USERNAME'] = 'default';
        $_SERVER['REDIS_SCHEME'] = 'tls';
        $_SERVER['REDIS_USERNAME'] = 'default';

        $this->refreshApplication();

        foreach (['default', 'cache'] as $connection) {
            $this->assertSame('tls', config("database.redis.{$connection}.scheme"));
            $this->assertSame('default', config("database.redis.{$connection}.username"));
        }
    }
}
