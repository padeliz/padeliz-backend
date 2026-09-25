<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Closure;
use Illuminate\Http\Request;

class ManagerResourceReadCache
{
    public function __construct(
        private readonly PadelizCache $cache,
    ) {
    }

    public function rememberShow(string $resource, int $id, Closure $resolver): mixed
    {
        if ($resource === 'editions') {
            return $this->rememberEditionShow($id, $resolver);
        }

        $policy = config('cache_policies.reference_show');
        $baseTags = array_merge($policy['tags'], [
            'reference:' . $resource,
            'reference:' . $resource . ':' . $id,
        ]);
        $key = 'mgr:show:' . $resource . ':' . $id;

        return $this->cache->remember($key, (int) $policy['ttl'], $baseTags, $resolver);
    }

    public function rememberIndex(string $resource, Request $request, array $allowedFilters, Closure $resolver): mixed
    {
        if (! $this->isIndexCacheable($request, $allowedFilters)) {
            $this->cache->setStatus('BYPASS');

            return $resolver();
        }

        $policyKey = $this->indexPolicyKey($request, $allowedFilters);
        $policy = config('cache_policies.' . $policyKey);
        $baseTags = array_merge($policy['tags'], ['mgr-list:' . $resource]);
        $editionId = data_get($request->input('filters'), 'edition_id');

        if ($editionId !== null) {
            $baseTags[] = 'edition:' . $editionId;
        }

        $key = 'mgr:index:' . $resource . ':' . md5($request->getQueryString() ?? '');

        return $this->cache->remember($key, (int) $policy['ttl'], $baseTags, $resolver);
    }

    public function isIndexCacheable(Request $request, array $allowedFilters = [], ?string $defaultSort = null): bool
    {
        if ($request->filled('q')) {
            return false;
        }

        if ((int) $request->input('page', 1) > 1) {
            return false;
        }

        if ((int) $request->input('per_page', 15) > 100) {
            return false;
        }

        $filters = $request->input('filters', []);

        if (is_array($filters)) {
            foreach (array_keys($filters) as $filterKey) {
                if ($allowedFilters !== [] && ! in_array($filterKey, $allowedFilters, true)) {
                    return false;
                }
            }
        }

        if ($request->filled('sort_by') && $defaultSort !== null && $request->input('sort_by') !== $defaultSort) {
            return false;
        }

        return true;
    }

    private function indexPolicyKey(Request $request, array $allowedFilters): string
    {
        $filters = $request->input('filters', []);

        if (is_array($filters) && isset($filters['edition_id']) && $allowedFilters === ['edition_id']) {
            return 'edition_scoped_index';
        }

        return 'reference_index_picker';
    }

    private function rememberEditionShow(int $id, Closure $resolver): mixed
    {
        $policy = config('cache_policies.edition_show');
        $baseTags = array_merge($policy['tags'], ['edition:' . $id]);
        $key = 'mgr:show:editions:' . $id;

        return $this->cache->remember($key, (int) $policy['ttl'], $baseTags, $resolver);
    }
}
