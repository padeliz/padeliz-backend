<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Location;
use App\Models\Player;
use App\Models\Prize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

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
    }

    public function test_manager_can_view_clubs()
    {
        Sanctum::actingAs($this->manager);

        Club::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/clubs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_club',
                        'name',
                        'city',
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

    public function test_manager_can_view_specific_club()
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create([
            'name' => 'Test Club',
            'city' => 'Test City',
        ]);

        $response = $this->getJson("/api/v1/manager/clubs/{$club->id_club}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_club',
                    'name',
                    'city',
                    'inserted_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
            'success' => true,
            'message' => 'Club details retrieved successfully',
            'data' => [
                'id_club' => $club->id_club,
                'name' => 'Test Club',
                'city' => 'Test City',
            ],
            ]);
    }

    public function test_manager_can_create_club()
    {
        Sanctum::actingAs($this->manager);

        $clubData = [
            'name' => 'New Club',
            'city' => 'New City',
        ];

        $response = $this->postJson('/api/v1/manager/clubs', $clubData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_club',
                    'name',
                    'city',
                    'inserted_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Club created successfully',
            ]);

        $this->assertDatabaseHas('clubs', [
            'name' => 'New Club',
            'city' => 'New City',
        ]);
    }

    public function test_manager_can_update_club()
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create();

        $updateData = [
            'name' => 'Updated Club Name',
            'city' => 'Updated City',
        ];

        $response = $this->putJson("/api/v1/manager/clubs/{$club->id_club}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_club',
                    'name',
                    'city',
                    'inserted_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
            'success' => true,
            'message' => 'Club updated successfully',
            'data' => [
                'id_club' => $club->id_club,
                'name' => 'Updated Club Name',
                'city' => 'Updated City',
            ],
            ]);
    }

    public function test_manager_can_delete_club()
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create();

        $response = $this->deleteJson("/api/v1/manager/clubs/{$club->id_club}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Club deleted successfully',
            ]);

        $this->assertDatabaseMissing('clubs', [
            'id_club' => $club->id_club,
        ]);
    }

    public function test_manager_can_view_club_locations()
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create();
        Location::factory()->count(3)->create(['club_id' => $club->id_club]);

        $response = $this->getJson("/api/v1/manager/clubs/{$club->id_club}/locations");

        $response->assertStatus(200)
            ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id_location',
                    'club_id',
                    'name',
                    'address',
                ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Club locations retrieved successfully',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_manager_can_view_club_prizes()
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create();
        $prizes = Prize::factory()->count(2)->create();

        // Create prize from club relationships
        foreach ($prizes as $prize) {
            \App\Models\PrizeFromClub::factory()->create([
                'club_id' => $club->id_club,
                'prize_id' => $prize->id_prize,
            ]);
        }

        $response = $this->getJson("/api/v1/manager/clubs/{$club->id_club}/prizes");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_club_prize',
                        'club_id',
                        'prize_id',
                        'image',
                        'prize' => [
                            'id_prize',
                            'name',
                            'image',
                        ],
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

        $this->assertCount(2, $response->json('data'));
    }

    public function test_club_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/clubs');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_club_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/clubs');

        $response->assertStatus(403);
    }

    public function test_club_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/clubs', [
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_club_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/clubs/99999');

        $response->assertStatus(404);
    }
}
