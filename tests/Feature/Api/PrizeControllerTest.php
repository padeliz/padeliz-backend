<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Prize;
use App\Models\Club;
use App\Models\PrizeFromClub;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PrizeControllerTest extends TestCase
{
    use RefreshDatabase;

    private $prize;
    private $clubs;

    public function setUp(): void
    {
        parent::setUp();

        // Create test prize with consistent casing
        $this->prize = Prize::factory()->create([
            'name' => 'test prize'  // lowercase for consistent sorting
        ]);

        // Create other prizes
        Prize::factory()->count(3)->create();

        // Create clubs and associate them with the main prize
        $this->clubs = Club::factory()->count(2)->create();
        foreach ($this->clubs as $club) {
            PrizeFromClub::factory()->create([
                'prize_id' => $this->prize->id_prize,
                'club_id' => $club->id_club,
            ]);
        }
    }

    public function test_index_returns_paginated_prizes()
    {
        $response = $this->getJson('/api/prizes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id_prize',
                        'name',
                    ]
                ],
                'first_page_url',
                'last_page'
            ])
            ->assertJsonCount(4, 'data'); // Total prizes created
    }

    public function test_show_returns_single_prize_with_club_prizes()
    {
        $response = $this->getJson("/api/prizes/{$this->prize->id_prize}");

        $response->assertStatus(200)
            ->assertJson([
                'id_prize' => $this->prize->id_prize,
                'name' => 'test prize',
            ])
            ->assertJsonStructure([
                'club_prizes' => [
                    '*' => [
                        'id_club_prize',
                        'prize_id',
                        'club_id',
                        'club' => [
                            'id_club',
                            'name',
                            'city'
                        ]
                    ]
                ]
            ])
            ->assertJsonCount(2, 'club_prizes'); // Number of clubs associated
    }

    public function test_show_returns_404_for_nonexistent_prize()
    {
        $response = $this->getJson('/api/prizes/99999');
        $response->assertStatus(404);
    }

    public function test_prizes_are_ordered_by_name()
    {
        $response = $this->getJson('/api/prizes');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $sortedNames = collect($names)->sort()->values()->toArray();

        $this->assertEquals($sortedNames, $names, 'Prizes are not properly sorted by name');
    }

    public function test_show_includes_correct_club_quantities()
    {
        $response = $this->getJson("/api/prizes/{$this->prize->id_prize}");

        $clubPrizes = collect($response->json('club_prizes'));

        foreach ($this->clubs as $club) {
            $clubPrize = $clubPrizes->firstWhere('club_id', $club->id_club);
            $this->assertNotNull($clubPrize, 'Club prize relationship not found');
        }
    }
}
