<?php

declare(strict_types=1);

namespace App\Integrations\RankedinBpt;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

final class RankedinBptClient
{
    private Client $http;

    /** @var array<string, list<mixed>|mixed> */
    private array $memo = [];

    private int $callCount = 0;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds,
        ?Client $httpClient = null,
    ) {
        $this->http = $httpClient ?? new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeoutSeconds,
            'http_errors' => true,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function callCount(): int
    {
        return $this->callCount;
    }

    private function cacheKey(string $path, array $query): string
    {
        $path = '/' . ltrim($path, '/');
        ksort($query);

        return $path . '?' . http_build_query($query);
    }

    /**
     * @param  array<string, null|bool|float|int|string>  $query
     * @return array<string, mixed>|list<mixed>
     */
    public function getJson(string $path, array $query = []): array
    {
        $path = '/' . ltrim($path, '/');
        $cacheKey = $this->cacheKey($path, $query);
        if (array_key_exists($cacheKey, $this->memo)) {
            /** @var array<string, mixed>|list<mixed> */
            return $this->memo[$cacheKey];
        }

        ++$this->callCount;
        $response = $this->http->get($path, ['query' => $query]);
        $decoded = $this->decodeResponse($response);
        $this->memo[$cacheKey] = $decoded;

        return $decoded;
    }

    /**
     * Concurrent GETs with memoization and bounded concurrency.
     *
     * @param  list<array{key: string, path: string, query: array<string, null|bool|float|int|string>}>  $items
     * @param  callable(string, array<string, mixed>|list<mixed>): void  $onSuccess
     * @param  callable(string, \Throwable): void  $onFailure
     */
    public function getJsonPool(array $items, int $concurrency, callable $onSuccess, callable $onFailure): void
    {
        $pending = [];
        foreach ($items as $item) {
            $path = '/' . ltrim($item['path'], '/');
            $ck = $this->cacheKey($path, $item['query']);
            if (array_key_exists($ck, $this->memo)) {
                /** @var array<string, mixed>|list<mixed> $cached */
                $cached = $this->memo[$ck];
                $onSuccess($item['key'], $cached);
            } else {
                $pending[] = $item;
            }
        }

        if ($pending === []) {
            return;
        }

        $pool = new Pool($this->http, (function () use ($pending) {
            foreach ($pending as $item) {
                $path = '/' . ltrim($item['path'], '/');
                $uri = $path . '?' . http_build_query($item['query']);
                yield $item['key'] => new Request('GET', $uri);
            }
        })(), [
            'concurrency' => max(1, $concurrency),
            'fulfilled' => function (ResponseInterface $response, string|int $key) use ($onSuccess, $pending): void {
                try {
                    $decoded = $this->decodeResponse($response);
                    $item = $this->findItemByKey($pending, (string) $key);
                    if ($item !== null) {
                        $path = '/' . ltrim($item['path'], '/');
                        $this->memo[$this->cacheKey($path, $item['query'])] = $decoded;
                    }
                    ++$this->callCount;
                    $onSuccess((string) $key, $decoded);
                } catch (\Throwable $e) {
                    Log::warning('RankedIn BPT pool decode failed', ['key' => (string) $key, 'error' => $e->getMessage()]);
                }
            },
            'rejected' => function ($reason, string|int $key) use ($onFailure): void {
                $e = $reason instanceof \Throwable ? $reason : new \RuntimeException((string) $reason);
                $onFailure((string) $key, $e);
            },
        ]);

        $pool->promise()->wait();
    }

    /**
     * @param  list<array{key: string, path: string, query: array<string, null|bool|float|int|string>}>  $items
     * @return null|array{key: string, path: string, query: array<string, null|bool|float|int|string>}
     */
    private function findItemByKey(array $items, string $key): ?array
    {
        foreach ($items as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        return null;
    }

    private function decodeResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \RuntimeException('RankedIn JSON root is not an object/array');
        }

        return $decoded;
    }
}
