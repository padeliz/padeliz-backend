<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Integrations\RankedinBpt\RankedinBptClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

final class RankedinBptTestHttp
{
    /**
     * @param  list<array<string, mixed>|list<mixed>|string>  $jsonBodies  JSON object/array or pre-encoded JSON string
     */
    public static function clientFromJsonQueue(array $jsonBodies, string $base = 'http://rankedin.test'): RankedinBptClient
    {
        $responses = [];
        foreach ($jsonBodies as $body) {
            $json = is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR);
            $responses[] = new Response(200, ['Content-Type' => 'application/json'], $json);
        }
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $http = new Client([
            'handler' => $stack,
            'base_uri' => rtrim($base, '/') . '/',
        ]);

        return new RankedinBptClient($base, 30, $http);
    }
}
