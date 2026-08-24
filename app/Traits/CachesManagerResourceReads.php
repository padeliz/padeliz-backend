<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\Cache\ManagerResourceReadCache;
use Closure;
use Illuminate\Http\Request;

trait CachesManagerResourceReads
{
    protected function cachedManagerShow(string $resource, int $id, Closure $resolver): mixed
    {
        return app(ManagerResourceReadCache::class)->rememberShow($resource, $id, $resolver);
    }

    protected function cachedManagerIndex(string $resource, Request $request, array $allowedFilters, Closure $resolver): mixed
    {
        return app(ManagerResourceReadCache::class)->rememberIndex($resource, $request, $allowedFilters, $resolver);
    }
}
