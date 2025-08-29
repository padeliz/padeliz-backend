<?php

namespace Tests\Feature\Api\V1\App;

use Tests\TestCase;
use App\Models\Player;
use App\Models\MatchesTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class MatchesTeamControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->player = Player::factory()->playerOnly()->create();
    }

    public function test_player_can_view_matches_teams()
    {
        Sanctum::actingAs($this->player);

        MatchesTeam::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/app/matches/teams');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_team',
                        'player_1',
                        'player_2'
                    ]
                ],
            'links' => [
                'first',
                'last',
                'prev',
                'next'
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'path',
                'per_page',
                'to',
                'total'
            ]
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

        $response = $this->postJson('/api/v1/app/matches/teams', $teamData);

        $response->assertStatus(201)
            ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id_team',
                'player_1',
                'player_2'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Team created successfully'
            ]);
    }

    public function test_player_cannot_create_team_with_self()
    {
        Sanctum::actingAs($this->player);

        $teamData = [
            'player_1' => $this->player->id_player,
            'player_2' => $this->player->id_player
        ];

        $response = $this->postJson('/api/v1/app/matches/teams', $teamData);

        $response->assertStatus(422);
    }

    public function test_matches_teams_require_authentication()
    {
        $response = $this->getJson('/api/v1/app/matches/teams');

        $response->assertStatus(401);
    }

    public function test_matches_team_validation()
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/matches/teams', []);

        $response->assertStatus(422);
    }

    public function test_matches_team_with_invalid_players()
    {
        Sanctum::actingAs($this->player);

        $teamData = [
            'player_1' => 99999,
            'player_2' => 99998
        ];

        $response = $this->postJson('/api/v1/app/matches/teams', $teamData);

        $response->assertStatus(422);
    }

    public function test_matches_team_pagination()
    {
        Sanctum::actingAs($this->player);

        MatchesTeam::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/app/matches/teams?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_team',
                        'player_1',
                        'player_2'
                    ]
                ],
            'links' => [
                'first',
                'last',
                'prev',
                'next'
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'path',
                'per_page',
                'to',
                'total'
            ]
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }
}
