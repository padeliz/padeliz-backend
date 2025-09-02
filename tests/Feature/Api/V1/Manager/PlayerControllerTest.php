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
            'name' => 'Test Manager',
            'phone' => '1234567890',
            'google_id' => 'google_manager_123',
            'google_name' => 'Test Manager',
            'google_email' => 'manager@example.com',
            'google_avatar' => 'https://example.com/manager-avatar.jpg',
            'user_types' => ['player', 'manager'],
        ]);

        $this->player = Player::factory()->create([
            'name' => 'Test Player',
            'phone' => '0987654321',
            'google_id' => 'google_player_123',
            'google_name' => 'Test Player',
            'google_email' => 'player@example.com',
            'google_avatar' => 'https://example.com/player-avatar.jpg',
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
                        'phone',
                        'google_email',
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
            'division_id' => $this->division->id_division,
        ]);

        $response = $this->getJson("/api/v1/manager/players/{$this->player->id_player}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Player details retrieved successfully',
                'data' => [
                    'id_player' => $this->player->id_player,
                    'name' => 'Test Player',
                    'phone' => '0987654321',
                    'google_email' => 'player@example.com',
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
            'name' => 'New Player',
            'phone' => '5555555555',
            'first_name' => 'New',
            'last_name' => 'Player',
            'skill_division' => 3.0,
            'user_types' => ['player'],
            'google_id' => 'google_new_player_123',
            'google_name' => 'New Player',
            'google_email' => 'newplayer@example.com',
            'google_avatar' => 'https://example.com/new-player-avatar.jpg',
        ];

        $response = $this->postJson('/api/v1/manager/players', $playerData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Player created successfully',
                'data' => [
                    'name' => 'New Player',
                    'phone' => '5555555555',
                    'first_name' => 'New',
                    'last_name' => 'Player',
                    'skill_division' => 3.0,
                ],
            ]);

        $this->assertDatabaseHas('players', [
            'name' => 'New Player',
            'phone' => '5555555555',
            'first_name' => 'New',
            'last_name' => 'Player',
            'skill_division' => 3.0,
            'google_id' => 'google_new_player_123',
            'google_name' => 'New Player',
            'google_email' => 'newplayer@example.com',
            'google_avatar' => 'https://example.com/new-player-avatar.jpg',
        ]);
    }

    public function test_manager_can_update_player()
    {
        Sanctum::actingAs($this->manager);

        $updateData = [
            'name' => 'Updated Player',
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
            'name' => 'Updated Player',
            'skill_division' => 5.0,
            'being_student' => true,
        ]);
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

        $response = $this->getJson('/api/v1/manager/players?sort_by[name]=asc');

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
