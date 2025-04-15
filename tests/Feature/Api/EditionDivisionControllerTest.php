<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Edition;
use App\Models\Division;
use App\Models\EditionDivision;
use App\Models\Player;
use App\Models\EditionParticipation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EditionDivisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private $editionDivision;
    private $edition;
    private $division;
    private $players;

    public function setUp(): void
    {
        parent::setUp();

        // Create base data
        $this->edition = Edition::factory()->create([
            'date' => Carbon::now()->addDays(5)
        ]);

        $this->division = Division::factory()->create([
            'name' => 'Test Division',
            'is_students_only' => true
        ]);

        // Create main test edition division
        $this->editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'court' => 'Court 1',
            'playing_start_time' => '14:00:00',
            'playing_end_time' => '18:00:00',
            'include_scores' => true
        ]);

        // Create other edition divisions
        EditionDivision::factory()->count(3)->create();

        // Create players and participations
        $this->players = Player::factory()->count(5)->create();
        foreach ($this->players as $player) {
            EditionParticipation::factory()->create([
                'edition_id' => $this->edition->id_edition,
                'division_id' => $this->division->id_division,
                'player_id' => $player->id_player
            ]);
        }
    }

    public function test_index_returns_paginated_edition_divisions()
    {
        $response = $this->getJson('/api/edition-divisions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id_edition_division',
                        'edition_id',
                        'division_id',
                        'players_limit',
                        'court',
                        'playing_start_time',
                        'playing_end_time',
                        'include_scores',
                        'edition' => [
                            'id_edition',
                            'name'
                        ],
                        'division' => [
                            'id_division',
                            'name'
                        ]
                    ]
                ],
                'first_page_url',
                'last_page'
            ])
            ->assertJsonCount(4, 'data'); // Total edition divisions created
    }

    public function test_show_returns_single_edition_division()
    {
        $response = $this->getJson("/api/edition-divisions/{$this->editionDivision->id_edition_division}");

        $response->assertStatus(200)
            ->assertJson([
                'id_edition_division' => $this->editionDivision->id_edition_division,
                'edition_id' => $this->edition->id_edition,
                'division_id' => $this->division->id_division,
                'players_limit' => 16,
                'court' => 'Court 1'
            ]);
    }

    public function test_show_returns_404_for_nonexistent_edition_division()
    {
        $response = $this->getJson('/api/edition-divisions/99999');
        $response->assertStatus(404);
    }

    public function test_participants_returns_division_participants()
    {
        $response = $this->getJson("/api/edition-divisions/{$this->editionDivision->id_edition_division}/participants");

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id_participation',
                    'edition_id',
                    'division_id',
                    'player_id',
                    'player' => [
                        'id_player',
                        'name',
                    ]
                ]
            ])
            ->assertJsonCount(5); // Number of players created
    }

    public function test_participants_returns_404_for_nonexistent_edition_division()
    {
        $response = $this->getJson('/api/edition-divisions/99999/participants');
        $response->assertStatus(404);
    }

    public function test_edition_divisions_are_ordered_by_id_desc()
    {
        $response = $this->getJson('/api/edition-divisions');

        $ids = collect($response->json('data'))->pluck('id_edition_division')->toArray();
        $sortedIds = collect($ids)->sort()->reverse()->values()->toArray();

        $this->assertEquals($sortedIds, $ids, 'Edition divisions are not properly sorted by ID');
    }
}
