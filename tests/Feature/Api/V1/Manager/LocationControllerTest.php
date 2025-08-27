<?php

namespace Tests\Feature\Api\V1\Manager;

use Tests\TestCase;
use App\Models\Player;
use App\Models\Location;
use App\Models\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class LocationControllerTest extends TestCase
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
            'user_types' => ['player', 'manager']
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
