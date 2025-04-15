<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Club;
use App\Models\Location;
use App\Models\Prize;
use App\Models\PrizeFromClub;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ClubControllerTest extends TestCase
{
    use RefreshDatabase;

    private $club;

    public function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->club = Club::factory()->create();

        // Create locations for the club
        Location::factory()->count(3)->create([
            'club_id' => $this->club->id_club
        ]);

        // Create other clubs with locations
        Club::factory()->count(2)->create()->each(function ($club) {
            Location::factory()->count(2)->create([
                'club_id' => $club->id_club
            ]);
        });

        // Create prizes and associate them with clubs
        $prizes = Prize::factory()->count(3)->create();
        foreach ($prizes as $prize) {
            PrizeFromClub::factory()->create([
                'club_id' => $this->club->id_club,
                'prize_id' => $prize->id_prize
            ]);
        }
    }

    public function test_index_returns_paginated_clubs()
    {
        $response = $this->getJson('/api/clubs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id_club',
                        'name',
                        'city',
                        'locations' => [
                            '*' => [
                                'id_location',
                                'name'
                            ]
                        ]
                    ]
                ],
                'first_page_url',
                'last_page'
            ])
            ->assertJsonCount(3, 'data'); // Total clubs created
    }

    public function test_show_returns_single_club()
    {
        $response = $this->getJson("/api/clubs/{$this->club->id_club}");

        $response->assertStatus(200)
            ->assertJson([
                'id_club' => $this->club->id_club,
                'name' => $this->club->name,
                'city' => $this->club->city
            ])
            ->assertJsonCount(3, 'locations'); // Number of locations created for this club
    }

    public function test_show_returns_404_for_nonexistent_club()
    {
        $response = $this->getJson('/api/clubs/99999');
        $response->assertStatus(404);
    }

    public function test_locations_returns_club_locations()
    {
        $response = $this->getJson("/api/clubs/{$this->club->id_club}/locations");

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id_location',
                    'name',
                    'club_id'
                ]
            ])
            ->assertJsonCount(3); // Number of locations created for this club
    }

    public function test_prizes_returns_paginated_club_prizes()
    {
        $response = $this->getJson("/api/clubs/{$this->club->id_club}/prizes");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id_club_prize',
                        'club_id',
                        'prize_id',
                        'prize' => [
                            'id_prize',
                            'name'
                        ]
                    ]
                ],
                'first_page_url',
                'last_page'
            ])
            ->assertJsonCount(3, 'data'); // Number of prizes created for this club
    }

    public function test_prizes_returns_404_for_nonexistent_club()
    {
        $response = $this->getJson('/api/clubs/99999/prizes');
        $response->assertStatus(404);
    }
}
