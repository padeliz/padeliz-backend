<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Prize;
use App\Models\Club;
use App\Models\PrizeFromClub;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PrizeFromClubControllerTest extends TestCase
{
    use RefreshDatabase;

    private $prizeFromClub;
    private $prize;
    private $club;

    public function setUp(): void
    {
        parent::setUp();

        // Create base data
        $this->prize = Prize::factory()->create([
            'name' => 'Test Prize',
        ]);

        $this->club = Club::factory()->create([
            'name' => 'Test Club',
            'city' => 'Test City'
        ]);

        // Create main test prize from club
        $this->prizeFromClub = PrizeFromClub::factory()->create([
            'club_id' => $this->club->id_club,
            'prize_id' => $this->prize->id_prize,
        ]);

        // Create other prizes from clubs
        PrizeFromClub::factory()->count(3)->create();
    }

    public function test_index_returns_paginated_prizes_from_clubs()
    {
        $response = $this->getJson('/api/prizes-from-clubs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id_club_prize',
                        'prize_id',
                        'club_id',
                        'prize' => [
                            'id_prize',
                            'name',
                        ],
                        'club' => [
                            'id_club',
                            'name',
                            'city'
                        ]
                    ]
                ],
                'first_page_url',
                'last_page'
            ])
            ->assertJsonCount(4, 'data'); // Total prizes from clubs created
    }

    public function test_show_returns_single_prize_from_club()
    {
        $response = $this->getJson("/api/prizes-from-clubs/{$this->prizeFromClub->id_club_prize}");

        $response->assertStatus(200)
            ->assertJson([
                'id_club_prize' => $this->prizeFromClub->id_club_prize,
                'prize_id' => $this->prize->id_prize,
                'club_id' => $this->club->id_club,
                'prize' => [
                    'name' => 'Test Prize',
                ],
                'club' => [
                    'name' => 'Test Club',
                    'city' => 'Test City'
                ]
            ]);
    }

    public function test_show_returns_404_for_nonexistent_prize_from_club()
    {
        $response = $this->getJson('/api/prizes-from-clubs/99999');
        $response->assertStatus(404);
    }

    public function test_prizes_from_clubs_are_ordered_by_id_desc()
    {
        $response = $this->getJson('/api/prizes-from-clubs');

        $ids = collect($response->json('data'))->pluck('id_club_prize')->toArray();
        $sortedIds = collect($ids)->sort()->reverse()->values()->toArray();

        $this->assertEquals($sortedIds, $ids, 'Prizes from clubs are not properly sorted by ID');
    }

    public function test_relationships_are_properly_loaded()
    {
        $response = $this->getJson("/api/prizes-from-clubs/{$this->prizeFromClub->id_club_prize}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'prize' => [
                    'id_prize',
                    'name',
                ],
                'club' => [
                    'id_club',
                    'name',
                    'city'
                ]
            ]);
    }
}
