<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\MatchesTeam;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatchesTeamControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    private $player1;

    private $player2;

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

        $this->player1 = Player::factory()->create([
            'name' => 'Player One',
        ]);

        $this->player2 = Player::factory()->create([
            'name' => 'Player Two',
        ]);
    }

    public function test_manager_can_view_matches_teams()
    {
        Sanctum::actingAs($this->manager);

        MatchesTeam::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/matches-teams');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_team',
                        'player_1',
                        'player_2',
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

        $this->assertCount(5, $response->json('data'));
    }

    public function test_manager_can_view_specific_matches_team()
    {
        Sanctum::actingAs($this->manager);

        $team = MatchesTeam::factory()->create([
            'player_1' => $this->player1->name,
            'player_2' => $this->player2->name,
        ]);

        $response = $this->getJson("/api/v1/manager/matches-teams/{$team->id_team}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Matches team details retrieved successfully',
                'data' => [
                    'id_team' => $team->id_team,
                    'player_1' => $this->player1->name,
                    'player_2' => $this->player2->name,
                ],
            ]);
    }

    public function test_manager_can_create_matches_team()
    {
        Sanctum::actingAs($this->manager);

        $teamData = [
            'player_1' => $this->player1->name,
            'player_2' => $this->player2->name,
        ];

        $response = $this->postJson('/api/v1/manager/matches-teams', $teamData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Matches team created successfully',
                'data' => [
                    'player_1' => $this->player1->name,
                    'player_2' => $this->player2->name,
                ],
            ]);

        $this->assertDatabaseHas('matches_teams', [
            'player_1' => $this->player1->name,
            'player_2' => $this->player2->name,
        ]);
    }

    public function test_manager_can_update_matches_team()
    {
        Sanctum::actingAs($this->manager);

        $player3 = Player::factory()->create();
        $team = MatchesTeam::factory()->create([
            'player_1' => $this->player1->name,
            'player_2' => $this->player2->name,
        ]);

        $updateData = [
            'player_1' => $this->player1->name,
            'player_2' => $player3->name,
        ];

        $response = $this->putJson("/api/v1/manager/matches-teams/{$team->id_team}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Matches team updated successfully',
                'data' => [
                    'id_team' => $team->id_team,
                    'player_1' => $this->player1->name,
                    'player_2' => $player3->name,
                ],
            ]);

        $this->assertDatabaseHas('matches_teams', [
            'id_team' => $team->id_team,
            'player_1' => $this->player1->name,
            'player_2' => $player3->name,
        ]);
    }

    public function test_manager_can_partially_update_matches_team()
    {
        Sanctum::actingAs($this->manager);

        $player3 = Player::factory()->create();
        $team = MatchesTeam::factory()->create([
            'player_1' => $this->player1->name,
            'player_2' => $this->player2->name,
        ]);

        // Test partial update with only player_1
        $response = $this->patchJson("/api/v1/manager/matches-teams/{$team->id_team}", [
            'player_1' => $player3->name,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Matches team partially updated successfully',
                'data' => [
                    'player_1' => $player3->name,
                    'player_2' => $this->player2->name, // Should remain unchanged
                ],
            ]);

        // Test partial update with only player_2
        $response = $this->patchJson("/api/v1/manager/matches-teams/{$team->id_team}", [
            'player_2' => $this->player1->name,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Matches team partially updated successfully',
                'data' => [
                    'player_1' => $player3->name, // Should remain from previous update
                    'player_2' => $this->player1->name,
                ],
            ]);
    }

    public function test_manager_cannot_update_matches_team_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $team = MatchesTeam::factory()->create([
            'player_1' => $this->player1->name,
            'player_2' => $this->player2->name,
        ]);

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/matches-teams/{$team->id_team}", [
            'player_1' => $this->player1->name,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_2']);

        // Test PUT with empty required fields
        $response = $this->putJson("/api/v1/manager/matches-teams/{$team->id_team}", [
            'player_1' => '',
            'player_2' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_1', 'player_2']);
    }

    public function test_manager_can_delete_matches_team()
    {
        Sanctum::actingAs($this->manager);

        $team = MatchesTeam::factory()->create();

        $response = $this->deleteJson("/api/v1/manager/matches-teams/{$team->id_team}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Matches team deleted successfully',
            ]);

        $this->assertDatabaseMissing('matches_teams', [
            'id_team' => $team->id_team,
        ]);
    }

    public function test_manager_can_bulk_delete_matches_teams()
    {
        Sanctum::actingAs($this->manager);

        $teams = MatchesTeam::factory()->count(3)->create();

        $response = $this->deleteJson('/api/v1/manager/matches-teams/bulk', [
            'team_ids' => $teams->pluck('id_team')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Match teams deleted successfully',
                'data' => [
                    'total_successful' => 3,
                    'total_failed' => 0,
                    'total_requested' => 3,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'successful' => [
                        '*' => [
                            'id',
                            'name',
                        ],
                    ],
                    'failed',
                    'total_requested',
                    'total_successful',
                    'total_failed',
                ],
            ]);

        // Verify all teams are deleted
        foreach ($teams as $team) {
            $this->assertDatabaseMissing('matches_teams', ['id_team' => $team->id_team]);
        }
    }

    public function test_manager_cannot_bulk_delete_matches_teams_with_invalid_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/matches-teams/bulk', [
            'team_ids' => [99999, 99998],
        ]);

        $response->assertStatus(207)
            ->assertJson([
                'success' => false,
                'message' => 'All resources failed to delete',
                'errors' => [
                    'total_successful' => 0,
                    'total_failed' => 2,
                    'total_requested' => 2,
                ],
            ])
            ->assertJsonStructure([
                'errors' => [
                    'successful',
                    'failed' => [
                        '*' => [
                            'id',
                            'reason',
                        ],
                    ],
                    'total_requested',
                    'total_successful',
                    'total_failed',
                ],
            ]);
    }

    public function test_manager_cannot_bulk_delete_matches_teams_with_empty_array()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/matches-teams/bulk', [
            'team_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_matches_teams_without_team_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/matches-teams/bulk', []);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_matches_teams_with_invalid_team_ids_format()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/matches-teams/bulk', [
            'team_ids' => ['invalid', 'format'],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_create_team_with_same_player()
    {
        Sanctum::actingAs($this->manager);

        $teamData = [
            'player_1' => $this->player1->name,
            'player_2' => $this->player1->name, // Same player
        ];

        $response = $this->postJson('/api/v1/manager/matches-teams', $teamData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_2']);
    }

    public function test_manager_cannot_create_duplicate_team()
    {
        Sanctum::actingAs($this->manager);

        // Create first team
        MatchesTeam::factory()->create([
            'player_1' => $this->player1->name,
            'player_2' => $this->player2->name,
        ]);

        // Try to create duplicate (same order)
        $duplicateData1 = [
            'player_1' => $this->player1->name,
            'player_2' => $this->player2->name,
        ];

        $response = $this->postJson('/api/v1/manager/matches-teams', $duplicateData1);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This team already exists',
            ]);

        // Try to create duplicate (reverse order)
        $duplicateData2 = [
            'player_1' => $this->player2->name,
            'player_2' => $this->player1->name,
        ];

        $response = $this->postJson('/api/v1/manager/matches-teams', $duplicateData2);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This team already exists',
            ]);
    }

    public function test_manager_cannot_update_to_duplicate_team()
    {
        Sanctum::actingAs($this->manager);

        $player3 = Player::factory()->create();
        $player4 = Player::factory()->create();

        // Create two different teams
        $team1 = MatchesTeam::factory()->create([
            'player_1' => $this->player1->name,
            'player_2' => $this->player2->name,
        ]);

        $team2 = MatchesTeam::factory()->create([
            'player_1' => $player3->name,
            'player_2' => $player4->name,
        ]);

        // Try to update second team to match first
        $response = $this->putJson("/api/v1/manager/matches-teams/{$team2->id_team}", [
            'player_1' => $this->player1->name,
            'player_2' => $this->player2->name,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This team already exists',
            ]);
    }

    public function test_matches_team_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/matches-teams', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_1', 'player_2']);
    }

    public function test_matches_team_creation_validation_invalid_player()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/matches-teams', [
            'player_1' => 99999, // Non-existent player
            'player_2' => $this->player2->name,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_1']);
    }

    public function test_matches_team_update_validation()
    {
        Sanctum::actingAs($this->manager);

        $team = MatchesTeam::factory()->create();

        $response = $this->putJson("/api/v1/manager/matches-teams/{$team->id_team}", [
            'player_1' => 99999, // Non-existent player
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_1']);
    }

    public function test_matches_team_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/matches-teams');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_matches_team_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/matches-teams');

        $response->assertStatus(403);
    }

    public function test_matches_team_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/matches-teams/99999');

        $response->assertStatus(404);
    }

    public function test_matches_team_pagination()
    {
        Sanctum::actingAs($this->manager);

        MatchesTeam::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/manager/matches-teams?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_team',
                        'player_1',
                        'player_2',
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

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }

    public function test_matches_team_with_timestamps()
    {
        Sanctum::actingAs($this->manager);

        $teamData = [
            'player_1' => $this->player1->name,
            'player_2' => $this->player2->name,
        ];

        $response = $this->postJson('/api/v1/manager/matches-teams', $teamData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Matches team created successfully',
            ]);

        $this->assertDatabaseHas('matches_teams', [
            'player_1' => $this->player1->name,
            'player_2' => $this->player2->name,
        ]);

        // Check that timestamps are set
        $team = MatchesTeam::where('player_1', $this->player1->name)
            ->where('player_2', $this->player2->name)
            ->first();

        $this->assertNotNull($team->inserted_at);
        $this->assertNotNull($team->updated_at);
    }

    public function test_matches_team_update_timestamps()
    {
        Sanctum::actingAs($this->manager);

        $team = MatchesTeam::factory()->create([
            'player_1' => $this->player1->name,
            'player_2' => $this->player2->name,
        ]);

        $originalUpdatedAt = $team->updated_at;

        // Wait a moment to ensure timestamp difference
        sleep(1);

        $player3 = Player::factory()->create();
        $response = $this->putJson("/api/v1/manager/matches-teams/{$team->id_team}", [
            'player_1' => $this->player1->name,
            'player_2' => $player3->name,
        ]);

        $response->assertStatus(200);

        $team->refresh();
        $this->assertGreaterThan($originalUpdatedAt, $team->updated_at);
    }
}
