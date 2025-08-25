<?php

namespace Tests\Feature\Api\V1\Player;

use Tests\TestCase;
use App\Models\Player;
use App\Models\MatchesTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class MatchesTeamControllerTest extends TestCase
{
    use RefreshDatabase;

    private $player;

    public function setUp(): void
    {
        parent::setUp();

        $this->player = Player::factory()->playerOnly()->create([
            'name' => 'Test Player',
            'phone' => '1234567890',
            'google_id' => 'google123',
            'google_name' => 'Test Player',
            'google_email' => 'player@example.com',
            'google_avatar' => 'https://example.com/avatar.jpg',
            'user_types' => ['player']
        ]);
    }

    public function test_player_can_view_matches_teams()
    {
        Sanctum::actingAs($this->player);

        // Create some matches teams
        MatchesTeam::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/player/matches/teams');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_team',
                        'player_1',
                        'player_2'
                    ]
                ],
                'current_page',
                'per_page',
                'total'
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_player_can_create_matches_team()
    {
        Sanctum::actingAs($this->player);

        $otherPlayer = Player::factory()->create();

        $teamData = [
            'player_1' => $this->player->id_player,
            'player_2' => $otherPlayer->id_player
        ];

        $response = $this->postJson('/api/v1/player/matches/teams', $teamData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id_team',
                'player_1',
                'player_2'
            ]);

        $this->assertDatabaseHas('matches_teams', [
            'player_1' => $this->player->id_player,
            'player_2' => $otherPlayer->id_player
        ]);
    }

    public function test_player_cannot_create_team_with_self()
    {
        Sanctum::actingAs($this->player);

        $teamData = [
            'player_1' => $this->player->id_player,
            'player_2' => $this->player->id_player
        ];

        $response = $this->postJson('/api/v1/player/matches/teams', $teamData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_2']);
    }

    public function test_matches_teams_require_authentication()
    {
        $response = $this->getJson('/api/v1/player/matches/teams');

        $response->assertStatus(401);
    }

    public function test_matches_team_validation()
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/player/matches/teams', [
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_1', 'player_2']);
    }

    public function test_matches_team_with_invalid_players()
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/player/matches/teams', [
            'player_1' => 99999, // Non-existent player
            'player_2' => 99998  // Non-existent player
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_1', 'player_2']);
    }

    public function test_matches_team_pagination()
    {
        Sanctum::actingAs($this->player);

        // Create many teams to test pagination
        MatchesTeam::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/player/matches/teams?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_team',
                        'player_1',
                        'player_2'
                    ]
                ],
                'current_page',
                'per_page',
                'total'
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(1, $response->json('current_page'));
        $this->assertEquals(25, $response->json('total'));
    }
}
