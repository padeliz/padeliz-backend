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

    public function test_manager_can_partially_update_club()
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create([
            'name' => 'Original Club Name',
            'city' => 'Original City',
        ]);

        // Test partial update with only name
        $response = $this->patchJson("/api/v1/manager/clubs/{$club->id_club}", [
            'name' => 'Partially Updated Name',
        ]);

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
            'message' => 'Club partially updated successfully',
            'data' => [
                'id_club' => $club->id_club,
                'name' => 'Partially Updated Name',
                'city' => 'Original City', // Should remain unchanged
            ],
            ]);

        // Test partial update with only city
        $response = $this->patchJson("/api/v1/manager/clubs/{$club->id_club}", [
            'city' => 'Partially Updated City',
        ]);

        $response->assertStatus(200)
            ->assertJson([
            'success' => true,
            'message' => 'Club partially updated successfully',
            'data' => [
                'name' => 'Partially Updated Name', // Should remain from previous update
                'city' => 'Partially Updated City',
            ],
            ]);
    }

    public function test_manager_cannot_update_club_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create();

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/clubs/{$club->id_club}", [
            'name' => 'Only Name',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['city']);

        // Test PUT with empty required fields
        $response = $this->putJson("/api/v1/manager/clubs/{$club->id_club}", [
            'name' => '',
            'city' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'city']);
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

    public function test_manager_cannot_delete_club_with_linked_locations()
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create();
        Location::factory()->count(3)->create(['club_id' => $club->id_club]);

        $response = $this->deleteJson("/api/v1/manager/clubs/{$club->id_club}");

        $response->assertStatus(409)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'linked_data' => [
                        'locations_count',
                        'prizes_count',
                    ],
                ],
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Club cannot be deleted because it has linked data. Please remove all associated locations_count, prizes_count first.',
                'errors' => [
                    'linked_data' => [
                        'locations_count' => 3,
                        'prizes_count' => 0,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('clubs', [
            'id_club' => $club->id_club,
        ]);
    }

    public function test_manager_cannot_delete_club_with_linked_prizes()
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

        $response = $this->deleteJson("/api/v1/manager/clubs/{$club->id_club}");

        $response->assertStatus(409)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'linked_data' => [
                        'locations_count',
                        'prizes_count',
                    ],
                ],
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Club cannot be deleted because it has linked data. Please remove all associated locations_count, prizes_count first.',
                'errors' => [
                    'linked_data' => [
                        'locations_count' => 0,
                        'prizes_count' => 2,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('clubs', [
            'id_club' => $club->id_club,
        ]);
    }

    public function test_manager_cannot_delete_club_with_both_linked_locations_and_prizes()
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create();
        Location::factory()->count(2)->create(['club_id' => $club->id_club]);

        $prizes = Prize::factory()->count(3)->create();
        foreach ($prizes as $prize) {
            \App\Models\PrizeFromClub::factory()->create([
                'club_id' => $club->id_club,
                'prize_id' => $prize->id_prize,
            ]);
        }

        $response = $this->deleteJson("/api/v1/manager/clubs/{$club->id_club}");

        $response->assertStatus(409)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'linked_data' => [
                        'locations_count',
                        'prizes_count',
                    ],
                ],
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Club cannot be deleted because it has linked data. Please remove all associated locations_count, prizes_count first.',
                'errors' => [
                    'linked_data' => [
                        'locations_count' => 2,
                        'prizes_count' => 3,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('clubs', [
            'id_club' => $club->id_club,
        ]);
    }

    public function test_manager_can_bulk_delete_clubs()
    {
        Sanctum::actingAs($this->manager);

        $clubs = Club::factory()->count(3)->create();
        $clubIds = $clubs->pluck('id_club')->toArray();

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', [
            'club_ids' => $clubIds,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'successful',
                    'failed',
                    'total_requested',
                    'total_successful',
                    'total_failed',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Clubs deleted successfully',
                'data' => [
                    'total_requested' => 3,
                    'total_successful' => 3,
                    'total_failed' => 0,
                ],
            ]);

        // Verify all clubs are deleted
        foreach ($clubIds as $clubId) {
            $this->assertDatabaseMissing('clubs', ['id_club' => $clubId]);
        }
    }

    public function test_manager_cannot_bulk_delete_clubs_with_linked_data()
    {
        Sanctum::actingAs($this->manager);

        $clubs = Club::factory()->count(3)->create();
        $clubIds = $clubs->pluck('id_club')->toArray();

        // Add linked data to the first club
        Location::factory()->count(2)->create(['club_id' => $clubs[0]->id_club]);

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', [
            'club_ids' => $clubIds,
        ]);

        $response->assertStatus(207) // Multi-Status
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'successful',
                    'failed',
                    'total_requested',
                    'total_successful',
                    'total_failed',
                ],
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Some resources failed to delete',
                'errors' => [
                    'total_requested' => 3,
                    'total_successful' => 2,
                    'total_failed' => 1,
                ],
            ]);

        // Verify successful deletions
        $this->assertDatabaseMissing('clubs', ['id_club' => $clubs[1]->id_club]);
        $this->assertDatabaseMissing('clubs', ['id_club' => $clubs[2]->id_club]);

        // Verify failed deletion (club with linked data still exists)
        $this->assertDatabaseHas('clubs', ['id_club' => $clubs[0]->id_club]);
    }

    public function test_manager_cannot_bulk_delete_clubs_with_invalid_ids()
    {
        Sanctum::actingAs($this->manager);

        $club = Club::factory()->create();
        $invalidIds = [999, 1000, 1001];

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', [
            'club_ids' => $invalidIds,
        ]);

        $response->assertStatus(207) // Multi-Status
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'successful',
                    'failed',
                    'total_requested',
                    'total_successful',
                    'total_failed',
                ],
            ])
            ->assertJson([
                'success' => false,
                'message' => 'All resources failed to delete',
                'errors' => [
                    'total_requested' => 3,
                    'total_successful' => 0,
                    'total_failed' => 3,
                ],
            ]);

        // Verify the valid club still exists
        $this->assertDatabaseHas('clubs', ['id_club' => $club->id_club]);
    }

    public function test_manager_cannot_bulk_delete_clubs_with_empty_array()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', [
            'club_ids' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_ids']);
    }

    public function test_manager_cannot_bulk_delete_clubs_without_club_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_ids']);
    }

    public function test_manager_cannot_bulk_delete_clubs_with_invalid_club_ids_format()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', [
            'club_ids' => 'not-an-array',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_ids']);
    }

    public function test_manager_cannot_bulk_delete_clubs_with_mixed_valid_and_invalid_scenarios()
    {
        Sanctum::actingAs($this->manager);

        $clubs = Club::factory()->count(4)->create();
        $clubIds = $clubs->pluck('id_club')->toArray();

        // Add linked data to the first club
        Location::factory()->count(2)->create(['club_id' => $clubs[0]->id_club]);

        // Add invalid ID
        $mixedIds = array_merge($clubIds, [999]);

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', [
            'club_ids' => $mixedIds,
        ]);

        $response->assertStatus(207) // Multi-Status
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'successful',
                    'failed',
                    'total_requested',
                    'total_successful',
                    'total_failed',
                ],
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Some resources failed to delete',
                'errors' => [
                    'total_requested' => 5,
                    'total_successful' => 3, // 3 valid clubs without linked data
                    'total_failed' => 2,     // 1 with linked data + 1 invalid ID
                ],
            ]);

        // Verify successful deletions
        $this->assertDatabaseMissing('clubs', ['id_club' => $clubs[1]->id_club]);
        $this->assertDatabaseMissing('clubs', ['id_club' => $clubs[2]->id_club]);
        $this->assertDatabaseMissing('clubs', ['id_club' => $clubs[3]->id_club]);

        // Verify failed deletion (club with linked data still exists)
        $this->assertDatabaseHas('clubs', ['id_club' => $clubs[0]->id_club]);
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
