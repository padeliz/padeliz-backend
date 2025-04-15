<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Player;
use App\Models\MatchesTeam;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MatchesTeamControllerTest extends TestCase
{
    use RefreshDatabase;

    private $team;
    private $player1;
    private $player2;
    private $authenticatedPlayer;

    public function setUp(): void
    {
        parent::setUp();

        // Create test authenticated player
        $this->authenticatedPlayer = Player::factory()->create([
            'google_email' => 'test@example.com',
            'google_id' => 'test123'
        ]);

        // Create players for the team
        $this->player1 = Player::factory()->create();
        $this->player2 = Player::factory()->create();

        // Create main test team
        $this->team = MatchesTeam::factory()->create([
            'player_1' => $this->player1->id_player,
            'player_2' => $this->player2->id_player
        ]);

        // Create other teams
        MatchesTeam::factory()->count(3)->create();
    }

    public function test_index_requires_authentication()
    {
        $response = $this->getJson('/api/matches/teams');
        $response->assertStatus(401);
    }

    public function test_index_returns_paginated_teams()
    {
        Sanctum::actingAs($this->authenticatedPlayer);

        $response = $this->getJson('/api/matches/teams');

        // dd($response);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id_team',
                        'player_1',
                        'player_2',
                        'player1' => [
                            'id_player',
                            'name',
                            'google_avatar'
                        ],
                        'player2' => [
                            'id_player',
                            'name',
                            'google_avatar'
                        ]
                    ]
                ],
                'first_page_url',
                'last_page'
            ]);
    }

    public function test_store_requires_authentication()
    {
        $response = $this->postJson('/api/matches/teams', [
            'player_1' => $this->player1->id_player,
            'player_2' => $this->player2->id_player
        ]);

        $response->assertStatus(401);
    }

    public function test_store_creates_new_team()
    {
        Sanctum::actingAs($this->authenticatedPlayer);

        $newPlayer1 = Player::factory()->create();
        $newPlayer2 = Player::factory()->create();

        $response = $this->postJson('/api/matches/teams', [
            'player_1' => $newPlayer1->id_player,
            'player_2' => $newPlayer2->id_player
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'player_1' => $newPlayer1->id_player,
                'player_2' => $newPlayer2->id_player
            ]);
    }

    public function test_store_prevents_duplicate_teams()
    {
        Sanctum::actingAs($this->authenticatedPlayer);

        $response = $this->postJson('/api/matches/teams', [
            'player_1' => $this->player1->id_player,
            'player_2' => $this->player2->id_player
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Team already exists'
            ]);
    }

    public function test_store_prevents_same_player_twice()
    {
        Sanctum::actingAs($this->authenticatedPlayer);

        $response = $this->postJson('/api/matches/teams', [
            'player_1' => $this->player1->id_player,
            'player_2' => $this->player1->id_player
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_2']);
    }

    public function test_store_validates_player_existence()
    {
        Sanctum::actingAs($this->authenticatedPlayer);

        $response = $this->postJson('/api/matches/teams', [
            'player_1' => 99999,
            'player_2' => $this->player2->id_player
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_1']);
    }

    public function test_teams_are_ordered_by_id_desc()
    {
        Sanctum::actingAs($this->authenticatedPlayer);

        $response = $this->getJson('/api/matches/teams');

        $ids = collect($response->json('data'))->pluck('id_team')->toArray();
        $sortedIds = collect($ids)->sort()->reverse()->values()->toArray();

        $this->assertEquals($sortedIds, $ids, 'Teams are not properly sorted by ID');
    }
}
