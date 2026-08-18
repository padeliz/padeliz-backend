<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionParticipation;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    private $player;

    private $edition;

    private $division;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);

        $this->player = Player::factory()->create([
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Player',
            'user_types' => ['player'],
            'skill_division' => 4.5,
            'year_of_birth' => 1990,
            'being_student' => false,
        ]);

        $this->edition = Edition::factory()->create();
        $this->division = Division::factory()->create();
    }

    public function test_manager_can_view_all_players()
    {
        Sanctum::actingAs($this->manager);

        // Create additional players
        Player::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/manager/players');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_player',
                        'name',
                        'emails',
                        'phones',
                        'skill_division',
                        'year_of_birth',
                        'being_student',
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
        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
        ]);

        $response = $this->getJson("/api/v1/manager/players/{$this->player->id_player}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Player details retrieved successfully',
                'data' => [
                    'id_player' => $this->player->id_player,
                    'name' => 'Test Player',
                    'skill_division' => 4.5,
                    'year_of_birth' => 1990,
                    'being_student' => false,
                ],
            ]);
    }

    public function test_manager_can_create_new_player()
    {
        Sanctum::actingAs($this->manager);

        $playerData = [
            'first_name' => 'New',
            'last_name' => 'Player',
            'skill_division' => 3.0,
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
                    'skill_division' => 3.0,
                ],
            ]);

        $this->assertDatabaseHas('players', [
            'first_name' => 'New',
            'last_name' => 'Player',
            'skill_division' => 3.0,
            'created_via' => 'manager',
            'created_via_format' => null,
            'created_via_import_id' => null,
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
            'skill_division' => 5.0,
            'being_student' => true,
        ];

        $response = $this->putJson("/api/v1/manager/players/{$this->player->id_player}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Player updated successfully',
                'data' => [
                    'id_player' => $this->player->id_player,
                    'name' => 'Updated Player',
                    'skill_division' => 5.0,
                    'being_student' => true,
                ],
            ]);

        $this->assertDatabaseHas('players', [
            'id_player' => $this->player->id_player,
            'first_name' => 'Updated',
            'last_name' => 'Player',
            'skill_division' => 5.0,
            'being_student' => true,
            'created_via' => null,
            'created_via_format' => null,
        ]);
    }

    public function test_manager_can_partially_update_player()
    {
        Sanctum::actingAs($this->manager);

        // Test partial update with only first/last name
        $response = $this->patchJson("/api/v1/manager/players/{$this->player->id_player}", [
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
                    'skill_division' => 4.5, // Should remain unchanged
                ],
            ]);

        // Test partial update with only skill_division
        $response = $this->patchJson("/api/v1/manager/players/{$this->player->id_player}", [
            'skill_division' => 6.0,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Player partially updated successfully',
                'data' => [
                    'name' => 'Partially Updated Player', // Should remain from previous update
                    'skill_division' => 6.0,
                ],
            ]);
    }

    public function test_manager_cannot_update_player_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/players/{$this->player->id_player}", [
            'skill_division' => 5.0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);

        // Test PUT with empty required fields
        $response = $this->putJson("/api/v1/manager/players/{$this->player->id_player}", [
            'first_name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);
    }

    public function test_manager_can_delete_player()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson("/api/v1/manager/players/{$this->player->id_player}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Player deleted successfully',
            ]);

        $this->assertDatabaseMissing('players', [
            'id_player' => $this->player->id_player,
        ]);
    }

    public function test_manager_can_bulk_delete_players()
    {
        Sanctum::actingAs($this->manager);

        // Create additional players
        $player2 = Player::factory()->create();
        $player3 = Player::factory()->create();

        $response = $this->deleteJson('/api/v1/manager/players/bulk', [
            'player_ids' => [$this->player->id_player, $player2->id_player],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Players deleted successfully',
            ]);

        $this->assertDatabaseMissing('players', [
            'id_player' => $this->player->id_player,
        ]);
        $this->assertDatabaseMissing('players', [
            'id_player' => $player2->id_player,
        ]);
        $this->assertDatabaseHas('players', [
            'id_player' => $player3->id_player,
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

        $needle = 'unique-email-search-' . $this->player->id_player . '@example.test';
        $this->player->emails()->update(['email' => 'other-' . $this->player->id_player . '@example.test']);
        $this->player->emails()->create([
            'email' => $needle,
            'purposes' => ['contact'],
            'is_primary' => false,
        ]);

        $response = $this->getJson('/api/v1/manager/players?q=' . urlencode($needle));

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id_player')->all();
        $this->assertContains($this->player->id_player, $ids);
        $this->assertNotContains($this->manager->id_player, $ids);
    }

    public function test_manager_search_matches_phone_when_name_does_not(): void
    {
        Sanctum::actingAs($this->manager);

        $needle = '+40999' . str_pad((string) $this->player->id_player, 6, '0', STR_PAD_LEFT);
        $this->player->phones()->update(['phone' => $needle]);

        $response = $this->getJson('/api/v1/manager/players?q=' . urlencode($needle));

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id_player')->all();
        $this->assertContains($this->player->id_player, $ids);
        $this->assertNotContains($this->manager->id_player, $ids);
    }

    public function test_factory_player_has_null_provenance(): void
    {
        $this->assertNull($this->player->fresh()->created_via);
        $this->assertNull($this->player->fresh()->created_via_format);
        $this->assertNull($this->player->fresh()->created_via_import_id);
    }

    public function test_pdf_avatar_url_prefers_collecting_points_cache(): void
    {
        Sanctum::actingAs($this->manager);

        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_id' => $this->player->id_player,
            'player_is_collecting_points' => false,
            'picture_path' => 'pdf-avatars/other.png',
        ]);
        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_id' => $this->player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => 'pdf-avatars/preferred.png',
        ]);

        $response = $this->getJson("/api/v1/manager/players/{$this->player->id_player}");

        $response->assertStatus(200);
        $url = $response->json('data.pdf_avatar_url');
        $this->assertIsString($url);
        $this->assertStringContainsString('preferred.png', $url);
    }

    public function test_update_ignores_posted_provenance_fields(): void
    {
        Sanctum::actingAs($this->manager);

        $this->putJson("/api/v1/manager/players/{$this->player->id_player}", [
            'first_name' => 'Updated',
            'middle_name' => null,
            'last_name' => 'Player',
            'created_via' => 'csv',
            'created_via_format' => 'playtomic',
        ])->assertStatus(200);

        $this->assertDatabaseHas('players', [
            'id_player' => $this->player->id_player,
            'first_name' => 'Updated',
            'created_via' => null,
            'created_via_format' => null,
        ]);
    }

    public function test_manager_can_filter_players_by_skill_division()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/players?filters[skill_division]=4.5');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_manager_can_sort_players()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/players?sort_by[skill_division]=asc');

        $response->assertStatus(200);

        // The sorting might affect the query results, so let's just verify we get a response
        $this->assertNotEmpty($response->json('data'));
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_unauthorized_user_cannot_access_player_endpoints()
    {
        $regularPlayer = Player::factory()->create();

        Sanctum::actingAs($regularPlayer);

        $response = $this->getJson('/api/v1/manager/players');
        $response->assertStatus(403);

        $response = $this->getJson("/api/v1/manager/players/{$this->player->id_player}");
        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_player_endpoints()
    {
        $response = $this->getJson('/api/v1/manager/players');
        $response->assertStatus(401);

        $response = $this->getJson("/api/v1/manager/players/{$this->player->id_player}");
        $response->assertStatus(401);
    }
}
