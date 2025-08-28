<?php

namespace Tests\Feature\Api\V1\Manager;

use Tests\TestCase;
use App\Models\Player;
use App\Models\Location;
use App\Models\Club;
use App\Models\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class LocationControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;
    private $club;

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
            'user_types' => ['player', 'manager']
        ]);

        $this->club = Club::factory()->create([
            'name' => 'Test Club',
            'city' => 'Test City'
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
                        'address'
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

    public function test_manager_can_view_specific_location()
    {
        Sanctum::actingAs($this->manager);

        $location = Location::factory()->create([
            'name' => 'Test Location'
        ]);

        $response = $this->getJson("/api/v1/manager/locations/{$location->id_location}");

        $response->assertStatus(200)
            ->assertJson([
            'success' => true,
            'message' => 'Location details retrieved successfully',
            'data' => [
                'id_location' => $location->id_location,
                'name' => 'Test Location'
            ]
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
            'total_outdoor_courts' => 2
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
                    'total_outdoor_courts' => 2
                ]
            ]);

        $this->assertDatabaseHas('locations', [
            'name' => 'New Location',
            'club_id' => $this->club->id_club
        ]);
    }

    public function test_manager_can_update_location()
    {
        Sanctum::actingAs($this->manager);

        $location = Location::factory()->create([
            'name' => 'Old Name',
            'address' => 'Old Address'
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'address' => 'Updated Address',
            'total_indoors_courts' => 6
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
                    'total_indoors_courts' => 6
                ]
            ]);

        $this->assertDatabaseHas('locations', [
            'id_location' => $location->id_location,
            'name' => 'Updated Name',
            'address' => 'Updated Address'
        ]);
    }

    public function test_manager_can_delete_location()
    {
        Sanctum::actingAs($this->manager);

        $location = Location::factory()->create();

        $response = $this->deleteJson("/api/v1/manager/locations/{$location->id_location}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Location deleted successfully'
            ]);

        $this->assertDatabaseMissing('locations', [
            'id_location' => $location->id_location
        ]);
    }

    public function test_manager_cannot_delete_location_with_editions()
    {
        Sanctum::actingAs($this->manager);

        $location = Location::factory()->create();
        Edition::factory()->create(['location_id' => $location->id_location]);

        $response = $this->deleteJson("/api/v1/manager/locations/{$location->id_location}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete location because it has related editions'
            ]);

        $this->assertDatabaseHas('locations', [
            'id_location' => $location->id_location
        ]);
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
            'club_id' => 99999 // Non-existent club
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_id']);
    }

    public function test_manager_can_view_location_editions()
    {
        Sanctum::actingAs($this->manager);

        $location = Location::factory()->create();
        Edition::factory()->count(3)->create(['location_id' => $location->id_location]);

        $response = $this->getJson("/api/v1/manager/locations/{$location->id_location}/editions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_edition',
                        'location_id',
                        'name',
                        'date'
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

        $this->assertCount(3, $response->json('data'));
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
                        'address'
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
