<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Location;
use App\Models\Player;
use App\Services\Cache\PadelizCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\UsesRedisTaggedCache;
use Tests\TestCase;

final class ClubControllerCacheTest extends TestCase
{
    use RefreshDatabase;
    use UsesRedisTaggedCache;

    private Player $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forceRedisTaggedCache();

        app(PadelizCache::class)->flushApplicationTags();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Cache',
            'middle_name' => null,
            'last_name' => 'Tester',
            'user_types' => ['player', 'manager'],
        ]);
    }

    public function test_clubs_index_returns_miss_then_hit(): void
    {
        Sanctum::actingAs($this->manager);

        Club::factory()->count(3)->create();

        $first = $this->getJson('/api/v1/manager/clubs?per_page=15&page=1');
        $second = $this->getJson('/api/v1/manager/clubs?per_page=15&page=1');

        $first->assertOk()
            ->assertHeader('X-Cache', 'MISS')
            ->assertHeader('Cache-Control', 'no-store, private');
        $second->assertOk()
            ->assertHeader('X-Cache', 'HIT')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_club_show_with_locations_returns_miss_then_hit(): void
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create(['name' => 'Cached Club']);
        Location::factory()->count(2)->create(['club_id' => $club->id_club]);

        $first = $this->getJson('/api/v1/manager/clubs/' . $club->id_club);
        $second = $this->getJson('/api/v1/manager/clubs/' . $club->id_club);

        $first->assertOk()->assertHeader('X-Cache', 'MISS');
        $second->assertOk()->assertHeader('X-Cache', 'HIT');
    }

    public function test_club_patch_invalidates_show_cache(): void
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create(['name' => 'Before Patch']);

        $this->getJson('/api/v1/manager/clubs/' . $club->id_club)->assertHeader('X-Cache', 'MISS');

        $this->patchJson('/api/v1/manager/clubs/' . $club->id_club, [
            'name' => 'After Patch',
        ])->assertOk();

        $response = $this->getJson('/api/v1/manager/clubs/' . $club->id_club);

        $response->assertOk()
            ->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('data.name', 'After Patch');

        $this->assertSame('After Patch', Club::query()->find($club->id_club)?->name);
    }

    public function test_clubs_search_bypasses_cache(): void
    {
        Sanctum::actingAs($this->manager);

        Club::factory()->create([
            'name' => 'Club București',
            'city' => 'Bucharest',
        ]);

        $first = $this->getJson('/api/v1/manager/clubs?q=Bucuresti');
        $second = $this->getJson('/api/v1/manager/clubs?q=Bucuresti');

        $first->assertOk()->assertHeader('X-Cache', 'BYPASS');
        $second->assertOk()->assertHeader('X-Cache', 'BYPASS');
    }
}
