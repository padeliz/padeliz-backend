<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Location;
use App\Models\PadelEvent;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    private $club;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);

        $this->club = Club::factory()->create([
            'name' => 'Test Club',
            'city' => 'Test City',
        ]);
    }

    public function test_manager_can_view_locations()
    {
        Sanctum::actingAs($this->manager);

        Location::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/locations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_location',
                        'club_id',
                        'name',
                        'address',
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

    public function test_manager_can_view_specific_location()
    {
        Sanctum::actingAs($this->manager);

        $location = Location::factory()->create([
            'name' => 'Test Location',
        ]);

        $response = $this->getJson("/api/v1/manager/locations/{$location->id_location}");

        $response->assertStatus(200)
            ->assertJson([
            'success' => true,
            'message' => 'Location details retrieved successfully',
            'data' => [
                'id_location' => $location->id_location,
                'name' => 'Test Location',
            ],
            ]);
    }

    public function test_manager_can_create_location()
    {
        Sanctum::actingAs($this->manager);

        $locationData = [
            'club_id' => $this->club->id_club,
            'name' => 'New Location',
            'address' => '123 Test Street',
            'total_indoors_courts' => 4,
            'total_outdoor_courts' => 2,
        ];

        $response = $this->postJson('/api/v1/manager/locations', $locationData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Location created successfully',
                'data' => [
                    'name' => 'New Location',
                    'address' => '123 Test Street',
                    'total_indoors_courts' => 4,
                    'total_outdoor_courts' => 2,
                ],
            ]);

        $this->assertDatabaseHas('locations', [
            'name' => 'New Location',
            'club_id' => $this->club->id_club,
        ]);
    }

    public function test_manager_can_update_location()
    {
        Sanctum::actingAs($this->manager);

        $location = Location::factory()->create([
            'name' => 'Old Name',
            'address' => 'Old Address',
        ]);

        $updateData = [
            'club_id' => $this->club->id_club,
            'name' => 'Updated Name',
            'address' => 'Updated Address',
            'total_indoors_courts' => 6,
        ];

        $response = $this->putJson("/api/v1/manager/locations/{$location->id_location}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Location updated successfully',
                'data' => [
                    'id_location' => $location->id_location,
                    'name' => 'Updated Name',
                    'address' => 'Updated Address',
                    'total_indoors_courts' => 6,
                ],
            ]);

        $this->assertDatabaseHas('locations', [
            'id_location' => $location->id_location,
            'name' => 'Updated Name',
            'address' => 'Updated Address',
        ]);
    }

    public function test_manager_can_partially_update_location()
    {
        Sanctum::actingAs($this->manager);

        $location = Location::factory()->create([
            'name' => 'Original Name',
            'address' => 'Original Address',
            'total_indoors_courts' => 4,
        ]);

        // Test partial update with only name
        $response = $this->patchJson("/api/v1/manager/locations/{$location->id_location}", [
            'name' => 'Partially Updated Name',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Location partially updated successfully',
                'data' => [
                    'name' => 'Partially Updated Name',
                    'address' => 'Original Address', // Should remain unchanged
                    'total_indoors_courts' => 4, // Should remain unchanged
                ],
            ]);

        // Test partial update with only address
        $response = $this->patchJson("/api/v1/manager/locations/{$location->id_location}", [
            'address' => 'Partially Updated Address',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Location partially updated successfully',
                'data' => [
                    'name' => 'Partially Updated Name', // Should remain from previous update
                    'address' => 'Partially Updated Address',
                ],
            ]);
    }

    public function test_manager_cannot_update_location_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $location = Location::factory()->create();

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/locations/{$location->id_location}", [
            'name' => 'Only Name',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_id']);

        // Test PUT with empty required fields
        $response = $this->putJson("/api/v1/manager/locations/{$location->id_location}", [
            'name' => '',
            'club_id' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'club_id']);
    }

    public function test_manager_can_delete_location()
    {
        Sanctum::actingAs($this->manager);

        $location = Location::factory()->create();

        $response = $this->deleteJson("/api/v1/manager/locations/{$location->id_location}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Location deleted successfully',
            ]);

        $this->assertDatabaseMissing('locations', [
            'id_location' => $location->id_location,
        ]);
    }

    public function test_manager_cannot_delete_location_with_events()
    {
        Sanctum::actingAs($this->manager);

        $location = Location::factory()->create();
        PadelEvent::factory()->create(['location_id' => $location->id_location]);

        $response = $this->deleteJson("/api/v1/manager/locations/{$location->id_location}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Location cannot be deleted because it has linked data. Please remove all associated events_count first.',
            ]);

        $this->assertDatabaseHas('locations', [
            'id_location' => $location->id_location,
        ]);
    }

    public function test_manager_can_bulk_delete_locations()
    {
        Sanctum::actingAs($this->manager);

        $locations = Location::factory()->count(3)->create();

        $response = $this->deleteJson('/api/v1/manager/locations/bulk', [
            'location_ids' => $locations->pluck('id_location')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Locations deleted successfully',
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

        // Verify all locations are deleted
        foreach ($locations as $location) {
            $this->assertDatabaseMissing('locations', ['id_location' => $location->id_location]);
        }
    }

    public function test_manager_cannot_bulk_delete_locations_with_linked_data()
    {
        Sanctum::actingAs($this->manager);

        $location1 = Location::factory()->create();
        $location2 = Location::factory()->create();
        $location3 = Location::factory()->create();

        // Create linked editions for location1
        PadelEvent::factory()->create(['location_id' => $location1->id_location]);

        $response = $this->deleteJson('/api/v1/manager/locations/bulk', [
            'location_ids' => [$location1->id_location, $location2->id_location, $location3->id_location],
        ]);

        $response->assertStatus(207)
            ->assertJson([
                'success' => false,
                'message' => 'Some resources failed to delete',
                'errors' => [
                    'total_successful' => 2,
                    'total_failed' => 1,
                    'total_requested' => 3,
                ],
            ])
            ->assertJsonStructure([
                'errors' => [
                    'successful' => [
                        '*' => [
                            'id',
                            'name',
                        ],
                    ],
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

        // Verify successful deletions
        $this->assertDatabaseMissing('locations', ['id_location' => $location2->id_location]);
        $this->assertDatabaseMissing('locations', ['id_location' => $location3->id_location]);
        // Verify failed deletion still exists
        $this->assertDatabaseHas('locations', ['id_location' => $location1->id_location]);
    }

    public function test_manager_cannot_bulk_delete_locations_with_invalid_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/locations/bulk', [
            'location_ids' => [99999, 99998],
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

    public function test_manager_cannot_bulk_delete_locations_with_empty_array()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/locations/bulk', [
            'location_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_locations_without_location_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/locations/bulk', []);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_locations_with_invalid_location_ids_format()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/locations/bulk', [
            'location_ids' => ['invalid', 'format'],
        ]);

        $response->assertStatus(422);
    }

    public function test_location_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/locations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_id', 'name']);
    }

    public function test_location_update_validation()
    {
        Sanctum::actingAs($this->manager);

        $location = Location::factory()->create();

        $response = $this->putJson("/api/v1/manager/locations/{$location->id_location}", [
            'club_id' => 99999, // Non-existent club
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_id']);
    }

    public function test_location_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/locations');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_location_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/locations');

        $response->assertStatus(403);
    }

    public function test_location_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/locations/99999');

        $response->assertStatus(404);
    }

    public function test_location_pagination()
    {
        Sanctum::actingAs($this->manager);

        Location::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/manager/locations?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_location',
                        'club_id',
                        'name',
                        'address',
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
}
