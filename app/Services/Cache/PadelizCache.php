<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PadelizCache
{
    public const APPLICATION_TAG = 'padeliz';

    public const STATUS_REQUEST_KEY = 'padeliz.cache_status';

    public function remember(string $key, int $ttl, array $tags, Closure $callback): mixed
    {
        try {
            $store = $this->taggedStore($tags);

            if ($store->has($key)) {
                $this->setStatus('HIT');

                return $store->get($key);
            }

            $value = $callback();
            $store->put($key, $value, $ttl);
            $this->setStatus('MISS');

            return $value;
        } catch (\Throwable $e) {
            Log::error('PadelizCache bypass: ' . $e->getMessage(), ['key' => $key]);
            $this->setStatus('BYPASS');

            return $callback();
        }
    }

    public function put(string $key, mixed $value, int $ttl, array $tags): void
    {
        $this->taggedStore($tags)->put($key, $value, $ttl);
    }

    public function has(string $key, array $tags): bool
    {
        return $this->taggedStore($tags)->has($key);
    }

    public function get(string $key, array $tags): mixed
    {
        return $this->taggedStore($tags)->get($key);
    }

    public function forget(string $key, array $tags = []): void
    {
        $this->taggedStore($tags)->forget($key);
    }

    /**
     * Flush each tag's entry set independently.
     *
     * Do not merge {@see APPLICATION_TAG} here: Redis tagged flush deletes every
     * key registered under any tag in the set, so including the app tag would
     * wipe the entire Padeliz cache on every targeted invalidation.
     *
     * @param  list<string>  $tags
     */
    public function flushTags(array $tags): void
    {
        if ($tags === []) {
            return;
        }

        $store = Cache::store(config('cache.default'));
        $repository = $store->getStore();

        if (! method_exists($repository, 'tags')) {
            throw new RuntimeException('Cache store does not support tags. Use Redis.');
        }

        foreach (array_values(array_unique($tags)) as $tag) {
            if ($tag === '') {
                continue;
            }

            $store->tags([$tag])->flush();
        }
    }

    public function flushApplicationTags(): void
    {
        $this->flushTags([self::APPLICATION_TAG]);
    }

    public function setStatus(string $status): void
    {
        if (app()->bound('request')) {
            request()->attributes->set(self::STATUS_REQUEST_KEY, $status);
        }
    }

    public function getStatus(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return request()->attributes->get(self::STATUS_REQUEST_KEY);
    }

    /**
     * @param  list<string>  $tags
     */
    private function taggedStore(array $tags): Repository
    {
        $store = Cache::store(config('cache.default'));
        $repository = $store->getStore();

        if (! method_exists($repository, 'tags')) {
            throw new RuntimeException('Cache store does not support tags. Use Redis.');
        }

        $allTags = array_values(array_unique(array_merge([self::APPLICATION_TAG], $tags)));

        return $store->tags($allTags);
    }
}
