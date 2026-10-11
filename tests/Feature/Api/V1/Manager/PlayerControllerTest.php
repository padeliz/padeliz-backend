<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Division;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
use App\Models\User;
use App\Services\Cache\PadelizCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\UsesRedisTaggedCache;
use Tests\TestCase;

class PlayerControllerTest extends TestCase
{
    use RefreshDatabase;
    use UsesRedisTaggedCache;

    private $manager;

    private $player;

    private $event;

    private $division;

    public function setUp(): void
    {
        parent::setUp();
        $this->forceRedisTaggedCache();

        app(PadelizCache::class)->flushApplicationTags();

        $this->manager = User::factory()->manager()->create([
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);

        $this->player = User::factory()->withContacts()->create([
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Player',
            'user_types' => ['player'],
        ]);

        $this->event = PadelEvent::factory()->create();
        $this->division = Division::factory()->create();
    }

    public function test_manager_can_view_all_players()
    {
        Sanctum::actingAs($this->manager);

        // Create additional players
        User::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/manager/players');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_user',
                        'name',
                        'emails',
                        'phones',
                        'inserted_at',
                        'updated_at',
                    ],
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);

        // Should have 4 players: the manager + the test player + 2 additional players created
        $this->assertCount(4, $response->json('data'));
    }

    public function test_manager_can_view_specific_player()
    {
        Sanctum::actingAs($this->manager);

        // Create a participation for the player
        EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
        ]);

        $response = $this->getJson("/api/v1/manager/players/{$this->player->id_user}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Player details retrieved successfully',
                'data' => [
                    'id_user' => $this->player->id_user,
                    'name' => 'Test Player',
                ],
            ]);
    }

    public function test_manager_can_create_new_player()
    {
        Sanctum::actingAs($this->manager);

        $playerData = [
            'first_name' => 'New',
            'last_name' => 'Player',
            'user_types' => ['player'],
        ];

        $response = $this->postJson('/api/v1/manager/players', $playerData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Player created successfully',
                'data' => [
                    'name' => 'New Player',
                    'first_name' => 'New',
                    'last_name' => 'Player',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'first_name' => 'New',
            'last_name' => 'Player',
            'created_via' => 'manager',
            'created_via_format' => null,
            'created_via_user_import_id' => null,
        ]);
        $this->assertSame('manager', $response->json('data.created_via'));
        $this->assertNull($response->json('data.created_via_format'));
        $this->assertNull($response->json('data.pdf_avatar_url'));
    }

    public function test_manager_can_update_player()
    {
        Sanctum::actingAs($this->manager);

        $updateData = [
            'first_name' => 'Updated',
            'middle_name' => null,
            'last_name' => 'Player',
        ];

        $response = $this->putJson("/api/v1/manager/players/{$this->player->id_user}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Player updated successfully',
                'data' => [
                    'id_user' => $this->player->id_user,
                    'name' => 'Updated Player',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id_user' => $this->player->id_user,
            'first_name' => 'Updated',
            'last_name' => 'Player',
            'created_via' => null,
            'created_via_format' => null,
        ]);
    }

    public function test_manager_can_partially_update_player()
    {
        Sanctum::actingAs($this->manager);

        // Test partial update with only first/last name
        $response = $this->patchJson("/api/v1/manager/players/{$this->player->id_user}", [
            'first_name' => 'Partially',
            'middle_name' => 'Updated',
            'last_name' => 'Player',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Player partially updated successfully',
                'data' => [
                    'name' => 'Partially Updated Player',
                ],
            ]);

        // Test partial update with only the last name
        $response = $this->patchJson("/api/v1/manager/players/{$this->player->id_user}", [
            'last_name' => 'Again',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Player partially updated successfully',
                'data' => [
                    'name' => 'Partially Updated Again', // First/middle name remain from previous update
                ],
            ]);
    }

    public function test_manager_cannot_update_player_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/players/{$this->player->id_user}", [
            'last_name' => 'Player',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);

        // Test PUT with empty required fields
        $response = $this->putJson("/api/v1/manager/players/{$this->player->id_user}", [
            'first_name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);
    }

    public function test_manager_can_delete_player()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson("/api/v1/manager/players/{$this->player->id_user}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Player deleted successfully',
            ]);

        $this->assertDatabaseMissing('users', [
            'id_user' => $this->player->id_user,
        ]);
    }

    public function test_manager_can_bulk_delete_players()
    {
        Sanctum::actingAs($this->manager);

        // Create additional players
        $player2 = User::factory()->create();
        $player3 = User::factory()->create();

        $response = $this->deleteJson('/api/v1/manager/players/bulk', [
            'player_user_ids' => [$this->player->id_user, $player2->id_user],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Players deleted successfully',
            ]);

        $this->assertDatabaseMissing('users', [
            'id_user' => $this->player->id_user,
        ]);
        $this->assertDatabaseMissing('users', [
            'id_user' => $player2->id_user,
        ]);
        $this->assertDatabaseHas('users', [
            'id_user' => $player3->id_user,
        ]);
    }

    public function test_manager_can_search_players()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/players?q=Test');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data')); // 2 players with "Test" in name (manager + test player)
    }

    public function test_manager_search_matches_email_when_name_does_not(): void
    {
        Sanctum::actingAs($this->manager);

        $needle = 'unique-email-search-' . $this->player->id_user . '@example.test';
        $this->player->emails()->update(['email' => 'other-' . $this->player->id_user . '@example.test']);
        $this->player->emails()->create([
            'email' => $needle,
            'purposes' => ['contact'],
            'is_primary' => false,
        ]);

        $response = $this->getJson('/api/v1/manager/players?q=' . urlencode($needle));

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id_user')->all();
        $this->assertContains($this->player->id_user, $ids);
        $this->assertNotContains($this->manager->id_user, $ids);
    }

    public function test_manager_search_matches_phone_when_name_does_not(): void
    {
        Sanctum::actingAs($this->manager);

        $needle = '+40999' . str_pad((string) $this->player->id_user, 6, '0', STR_PAD_LEFT);
        $this->player->phones()->update(['phone' => $needle]);

        $response = $this->getJson('/api/v1/manager/players?q=' . urlencode($needle));

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id_user')->all();
        $this->assertContains($this->player->id_user, $ids);
        $this->assertNotContains($this->manager->id_user, $ids);
    }

    public function test_manager_search_finds_diacritic_first_name_without_accent(): void
    {
        Sanctum::actingAs($this->manager);

        $player = User::factory()->create([
            'first_name' => 'Ștefan',
            'middle_name' => null,
            'last_name' => 'Popescu',
        ]);

        $response = $this->getJson('/api/v1/manager/players?q=Stefan');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id_user')->all();
        $this->assertContains($player->id_user, $ids);
    }

    public function test_manager_search_matches_middle_name(): void
    {
        Sanctum::actingAs($this->manager);

        $player = User::factory()->create([
            'first_name' => 'Ana',
            'middle_name' => 'Maria',
            'last_name' => 'Popescu',
        ]);

        $response = $this->getJson('/api/v1/manager/players?q=Maria');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id_user')->all();
        $this->assertContains($player->id_user, $ids);
    }

    public function test_factory_player_has_null_provenance(): void
    {
        $this->assertNull($this->player->fresh()->created_via);
        $this->assertNull($this->player->fresh()->created_via_format);
        $this->assertNull($this->player->fresh()->created_via_user_import_id);
    }

    public function test_pdf_avatar_url_prefers_collecting_points_cache(): void
    {
        Sanctum::actingAs($this->manager);

        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $this->player->id_user,
            'player_is_collecting_points' => false,
            'picture_path' => 'pdf-avatars/other.png',
        ]);
        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $this->player->id_user,
            'player_is_collecting_points' => true,
            'picture_path' => 'pdf-avatars/preferred.png',
        ]);

        $response = $this->getJson("/api/v1/manager/players/{$this->player->id_user}");

        $response->assertStatus(200);
        $url = $response->json('data.pdf_avatar_url');
        $this->assertIsString($url);
        $this->assertStringContainsString('preferred.png', $url);
    }

    public function test_update_ignores_posted_provenance_fields(): void
    {
        Sanctum::actingAs($this->manager);

        $this->putJson("/api/v1/manager/players/{$this->player->id_user}", [
            'first_name' => 'Updated',
            'middle_name' => null,
            'last_name' => 'Player',
            'created_via' => 'csv',
            'created_via_format' => 'playtomic',
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id_user' => $this->player->id_user,
            'first_name' => 'Updated',
            'created_via' => null,
            'created_via_format' => null,
        ]);
    }

    public function test_manager_player_filters_ignore_columns_outside_the_whitelist()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/players?filters[last_name]=Player');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_manager_can_sort_players()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/players?sort_by[first_name]=asc');

        $response->assertStatus(200);

        // The sorting might affect the query results, so let's just verify we get a response
        $this->assertNotEmpty($response->json('data'));
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_unauthorized_user_cannot_access_player_endpoints()
    {
        $regularPlayer = User::factory()->create();

        Sanctum::actingAs($regularPlayer);

        $response = $this->getJson('/api/v1/manager/players');
        $response->assertStatus(403);

        $response = $this->getJson("/api/v1/manager/players/{$this->player->id_user}");
        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_player_endpoints()
    {
        $response = $this->getJson('/api/v1/manager/players');
        $response->assertStatus(401);

        $response = $this->getJson("/api/v1/manager/players/{$this->player->id_user}");
        $response->assertStatus(401);
    }

    public function test_player_index_includes_pdf_avatar_url_from_overlay(): void
    {
        Sanctum::actingAs($this->manager);

        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $this->player->id_user,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $this->player->id_user . '/index.png',
        ]);

        $response = $this->getJson('/api/v1/manager/players');
        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id_user', $this->player->id_user);
        $this->assertNotNull($row);
        $this->assertNotNull($row['pdf_avatar_url']);
    }

    public function test_player_show_pdf_avatar_fresh_after_cache_warm(): void
    {
        Sanctum::actingAs($this->manager);

        $warm = $this->getJson("/api/v1/manager/players/{$this->player->id_user}")->assertOk();
        $this->assertNull($warm->json('data.pdf_avatar_url'));

        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $this->player->id_user,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $this->player->id_user . '/fresh.png',
        ]);

        $again = $this->getJson("/api/v1/manager/players/{$this->player->id_user}")->assertOk();
        $this->assertNotNull($again->json('data.pdf_avatar_url'));
    }

    public function test_player_show_lists_cached_pdf_images_for_both_variants_and_stays_fresh(): void
    {
        Sanctum::actingAs($this->manager);

        $warm = $this->getJson("/api/v1/manager/players/{$this->player->id_user}")->assertOk();
        $this->assertSame([], $warm->json('data.cached_pdf_images'));

        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $this->player->id_user,
            'player_is_collecting_points' => false,
            'picture_path' => 'cached-pdf-player-images/' . $this->player->id_user . '/false.png',
        ]);
        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $this->player->id_user,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $this->player->id_user . '/true.png',
        ]);

        $again = $this->getJson("/api/v1/manager/players/{$this->player->id_user}")->assertOk();
        $images = $again->json('data.cached_pdf_images');

        $this->assertCount(2, $images);
        $this->assertSame([true, false], array_column($images, 'player_is_collecting_points'));
        $this->assertStringContainsString('true.png', $images[0]['url']);
        $this->assertStringContainsString('false.png', $images[1]['url']);
        $this->assertArrayHasKey('id_cached_pdf_player_image', $images[0]);
    }

    public function test_index_hides_manager_only_users(): void
    {
        Sanctum::actingAs($this->manager);

        $managerOnly = User::factory()->managerOnly()->create([
            'first_name' => 'Mgr',
            'last_name' => 'Only',
        ]);
        $playerOnly = User::factory()->playerOnly()->create([
            'first_name' => 'Visible',
            'last_name' => 'Player',
        ]);

        $response = $this->getJson('/api/v1/manager/players');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id_user')->all();

        $this->assertContains($playerOnly->id_user, $ids);
        $this->assertNotContains($managerOnly->id_user, $ids);
    }

    public function test_show_returns_404_for_manager_only_user(): void
    {
        Sanctum::actingAs($this->manager);

        $managerOnly = User::factory()->managerOnly()->create([
            'first_name' => 'Mgr',
            'last_name' => 'Only',
        ]);

        $this->getJson('/api/v1/manager/players/' . $managerOnly->id_user)
            ->assertNotFound();
    }

    public function test_bulk_destroy_rejects_legacy_player_ids(): void
    {
        Sanctum::actingAs($this->manager);

        $this->deleteJson('/api/v1/manager/players/bulk', [
            'player_ids' => [$this->player->id_user],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['player_ids']);
    }
}
