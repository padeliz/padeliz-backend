<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Cache;

use App\Services\Cache\ManagerResourceReadCache;
use App\Services\Cache\PadelizCache;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ManagerResourceReadCacheTest extends TestCase
{
    private ManagerResourceReadCache $readCache;

    private PadelizCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = app(PadelizCache::class);
        $this->readCache = app(ManagerResourceReadCache::class);
        $this->cache->flushApplicationTags();
    }

    public function test_remember_index_hits_with_nested_payload_tags(): void
    {
        $request = Request::create('/api/v1/manager/clubs', 'GET', ['per_page' => 15, 'page' => 1]);
        $key = $this->indexKey('clubs', $request);
        $tags = $this->indexTags('clubs', $request, []);
        $calls = 0;

        $payload = [
            'data' => [
                ['id_club' => 1, 'locations' => [['id_location' => 10, 'club_id' => 1]]],
                ['id_club' => 2, 'locations' => [['id_location' => 11, 'club_id' => 2]]],
            ],
        ];

        $resolver = function () use (&$calls, $payload) {
            ++$calls;

            return $payload;
        };

        $first = $this->readCache->rememberIndex('clubs', $request, [], $resolver);
        $second = $this->readCache->rememberIndex('clubs', $request, [], $resolver);

        $this->assertSame($payload, $first);
        $this->assertSame($payload, $second);
        $this->assertSame(1, $calls);
        $this->assertTrue($this->cache->has($key, $tags));
    }

    public function test_remember_index_uses_separate_keys_per_query_string(): void
    {
        $requestA = Request::create('/api/v1/manager/clubs', 'GET', ['per_page' => 15, 'page' => 1]);
        $requestB = Request::create('/api/v1/manager/clubs', 'GET', ['per_page' => 30, 'page' => 1]);
        $keyA = $this->indexKey('clubs', $requestA);
        $keyB = $this->indexKey('clubs', $requestB);
        $tagsA = $this->indexTags('clubs', $requestA, []);
        $tagsB = $this->indexTags('clubs', $requestB, []);
        $calls = 0;

        $this->readCache->rememberIndex('clubs', $requestA, [], function () use (&$calls) {
            ++$calls;

            return ['data' => [['id_club' => 1]]];
        });
        $this->readCache->rememberIndex('clubs', $requestB, [], function () use (&$calls) {
            ++$calls;

            return ['data' => [['id_club' => 2]]];
        });

        $this->assertSame(2, $calls);
        $this->assertTrue($this->cache->has($keyA, $tagsA));
        $this->assertTrue($this->cache->has($keyB, $tagsB));
    }

    public function test_remember_index_edition_scoped_policy(): void
    {
        $request = Request::create('/api/v1/manager/players', 'GET', [
            'filters' => ['event_id' => 5],
            'per_page' => 15,
            'page' => 1,
        ]);
        $allowedFilters = ['event_id'];
        $calls = 0;

        $this->readCache->rememberIndex('players', $request, $allowedFilters, function () use (&$calls) {
            ++$calls;

            return ['data' => [['id_player' => 1, 'event_id' => 5]]];
        });
        $this->readCache->rememberIndex('players', $request, $allowedFilters, function () use (&$calls) {
            ++$calls;

            return ['data' => [['id_player' => 99]]];
        });

        $this->assertSame(1, $calls);
    }

    public function test_remember_show_hits_with_nested_locations(): void
    {
        $calls = 0;
        $payload = [
            'id_club' => 1,
            'name' => 'Test Club',
            'locations' => [['id_location' => 10, 'club_id' => 1]],
        ];
        $tags = array_merge(config('cache_policies.reference_show')['tags'], [
            'reference:clubs',
            'reference:clubs:1',
        ]);

        $this->readCache->rememberShow('clubs', 1, function () use (&$calls, $payload) {
            ++$calls;

            return $payload;
        });
        $this->readCache->rememberShow('clubs', 1, function () use (&$calls, $payload) {
            ++$calls;

            return $payload;
        });

        $this->assertSame(1, $calls);
        $this->assertTrue($this->cache->has('mgr:show:clubs:1', $tags));
    }

    public function test_remember_show_edition_path(): void
    {
        $calls = 0;
        $tags = array_merge(config('cache_policies.event_show')['tags'], ['event:7']);

        $this->readCache->rememberShow('events', 7, function () use (&$calls) {
            ++$calls;

            return ['id_event' => 7, 'name' => 'PadelEvent'];
        });
        $this->readCache->rememberShow('events', 7, function () use (&$calls) {
            ++$calls;

            return ['id_event' => 7, 'name' => 'Other'];
        });

        $this->assertSame(1, $calls);
        $this->assertTrue($this->cache->has('mgr:show:events:7', $tags));
    }

    public function test_index_bypasses_when_search_query_present(): void
    {
        $request = Request::create('/api/v1/manager/clubs', 'GET', ['q' => 'term']);
        $calls = 0;

        $this->readCache->rememberIndex('clubs', $request, [], function () use (&$calls) {
            ++$calls;

            return ['data' => []];
        });
        $this->readCache->rememberIndex('clubs', $request, [], function () use (&$calls) {
            ++$calls;

            return ['data' => []];
        });

        $this->assertSame(2, $calls);
        $this->assertFalse($this->cache->has($this->indexKey('clubs', $request), $this->indexTags('clubs', $request, [])));
    }

    public function test_index_bypasses_when_page_greater_than_one(): void
    {
        $request = Request::create('/api/v1/manager/clubs', 'GET', ['page' => 2]);
        $calls = 0;

        $this->readCache->rememberIndex('clubs', $request, [], function () use (&$calls) {
            ++$calls;

            return ['data' => []];
        });
        $this->readCache->rememberIndex('clubs', $request, [], function () use (&$calls) {
            ++$calls;

            return ['data' => []];
        });

        $this->assertSame(2, $calls);
    }

    public function test_index_bypasses_when_per_page_exceeds_limit(): void
    {
        $request = Request::create('/api/v1/manager/clubs', 'GET', ['per_page' => 101]);
        $calls = 0;

        $this->readCache->rememberIndex('clubs', $request, [], function () use (&$calls) {
            ++$calls;

            return ['data' => []];
        });
        $this->readCache->rememberIndex('clubs', $request, [], function () use (&$calls) {
            ++$calls;

            return ['data' => []];
        });

        $this->assertSame(2, $calls);
    }

    public function test_is_index_cacheable_rejects_disallowed_filter_keys(): void
    {
        $request = Request::create('/api/v1/manager/clubs', 'GET', [
            'filters' => ['city' => 'Bucharest'],
        ]);

        $this->assertFalse($this->readCache->isIndexCacheable($request, ['event_id']));
    }

    public function test_is_index_cacheable_rejects_non_default_sort(): void
    {
        $request = Request::create('/api/v1/manager/clubs', 'GET', [
            'sort_by' => ['name' => 'asc'],
        ]);

        $this->assertFalse($this->readCache->isIndexCacheable($request, [], 'id_club'));
    }

    public function test_remember_index_cacheable_request_hits(): void
    {
        $request = Request::create('/api/v1/manager/clubs', 'GET', ['per_page' => 15, 'page' => 1]);
        $calls = 0;

        $this->readCache->rememberIndex('clubs', $request, [], function () use (&$calls) {
            ++$calls;

            return ['data' => [['id_club' => 1]]];
        });
        $this->readCache->rememberIndex('clubs', $request, [], function () use (&$calls) {
            ++$calls;

            return ['data' => [['id_club' => 99]]];
        });

        $this->assertSame(1, $calls);
    }

    public function test_flush_mgr_list_tag_invalidates_index(): void
    {
        $request = Request::create('/api/v1/manager/clubs', 'GET', ['per_page' => 15, 'page' => 1]);
        $key = $this->indexKey('clubs', $request);
        $tags = $this->indexTags('clubs', $request, []);
        $calls = 0;

        $this->readCache->rememberIndex('clubs', $request, [], function () use (&$calls) {
            ++$calls;

            return ['data' => [['id_club' => 1]]];
        });
        $this->assertTrue($this->cache->has($key, $tags));

        $this->cache->flushTags(['mgr-list:clubs']);

        $this->readCache->rememberIndex('clubs', $request, [], function () use (&$calls) {
            ++$calls;

            return ['data' => [['id_club' => 2]]];
        });

        $this->assertSame(2, $calls);
    }

    public function test_flush_reference_show_tag_invalidates_show(): void
    {
        $tags = array_merge(config('cache_policies.reference_show')['tags'], [
            'reference:clubs',
            'reference:clubs:1',
        ]);
        $calls = 0;

        $this->readCache->rememberShow('clubs', 1, function () use (&$calls) {
            ++$calls;

            return ['id_club' => 1];
        });
        $this->assertTrue($this->cache->has('mgr:show:clubs:1', $tags));

        $this->cache->flushTags(['reference:clubs:1']);

        $this->readCache->rememberShow('clubs', 1, function () use (&$calls) {
            ++$calls;

            return ['id_club' => 1, 'name' => 'Updated'];
        });

        $this->assertSame(2, $calls);
    }

    public function test_flush_edition_tag_invalidates_event_scoped_index(): void
    {
        $request = Request::create('/api/v1/manager/players', 'GET', [
            'filters' => ['event_id' => 5],
            'per_page' => 15,
            'page' => 1,
        ]);
        $allowedFilters = ['event_id'];
        $key = $this->indexKey('players', $request);
        $tags = $this->indexTags('players', $request, $allowedFilters);
        $calls = 0;

        $this->readCache->rememberIndex('players', $request, $allowedFilters, function () use (&$calls) {
            ++$calls;

            return ['data' => [['id_player' => 1]]];
        });
        $this->assertTrue($this->cache->has($key, $tags));

        $this->cache->flushTags(['event:5']);

        $this->readCache->rememberIndex('players', $request, $allowedFilters, function () use (&$calls) {
            ++$calls;

            return ['data' => [['id_player' => 2]]];
        });

        $this->assertSame(2, $calls);
    }

    /**
     * @param  list<string>  $allowedFilters
     * @return list<string>
     */
    private function indexTags(string $resource, Request $request, array $allowedFilters): array
    {
        $filters = $request->input('filters', []);
        $policyKey = is_array($filters) && isset($filters['event_id']) && $allowedFilters === ['event_id']
            ? 'event_scoped_index'
            : 'reference_index_picker';
        $policy = config('cache_policies.' . $policyKey);
        $tags = array_merge($policy['tags'], ['mgr-list:' . $resource]);
        $editionId = data_get($request->input('filters'), 'event_id');

        if ($editionId !== null) {
            $tags[] = 'event:' . $editionId;
        }

        return $tags;
    }

    private function indexKey(string $resource, Request $request): string
    {
        return 'mgr:index:' . $resource . ':' . md5($request->getQueryString() ?? '');
    }
}
