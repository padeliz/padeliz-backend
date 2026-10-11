<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Location;
use App\Models\User;
use App\Models\Sponsor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubDeletionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);
    }

    public function test_api_returns_409_when_deleting_club_with_linked_data()
    {
        Sanctum::actingAs($this->manager);

        // Create a club with linked locations
        $club = Club::factory()->create([
            'name' => 'Test Club with Locations',
            'city' => 'Test City',
        ]);

        Location::factory()->count(2)->create(['club_id' => $club->id_club]);

        // Attempt to delete the club
        $response = $this->deleteJson("/api/v1/manager/clubs/{$club->id_club}");

        // Should return 409 Conflict
        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Club cannot be deleted because it has linked data. Please remove all associated locations_count, sponsors_count first.',
                'errors' => [
                    'linked_data' => [
                        'locations_count' => 2,
                        'sponsors_count' => 0,
                    ],
                ],
            ]);

        // Club should still exist in database
        $this->assertDatabaseHas('clubs', [
            'id_club' => $club->id_club,
            'name' => 'Test Club with Locations',
        ]);

        // Locations should still exist
        $this->assertDatabaseCount('locations', 2);
    }

    public function test_api_returns_200_when_deleting_club_without_linked_data()
    {
        Sanctum::actingAs($this->manager);

        // Create a club without any linked data
        $club = Club::factory()->create([
            'name' => 'Test Club without Links',
            'city' => 'Test City',
        ]);

        // Attempt to delete the club
        $response = $this->deleteJson("/api/v1/manager/clubs/{$club->id_club}");

        // Should return 200 Success
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Club deleted successfully',
            ]);

        // Club should be deleted from database
        $this->assertDatabaseMissing('clubs', [
            'id_club' => $club->id_club,
        ]);
    }

    public function test_api_returns_409_with_detailed_linked_data_information()
    {
        Sanctum::actingAs($this->manager);

        // Create a club with both locations and sponsors
        $club = Club::factory()->create([
            'name' => 'Test Club with Both',
            'city' => 'Test City',
        ]);

        Location::factory()->count(3)->create(['club_id' => $club->id_club]);

        Sponsor::factory()->forClub($club)->create();

        // Attempt to delete the club
        $response = $this->deleteJson("/api/v1/manager/clubs/{$club->id_club}");

        // Should return 409 with detailed information
        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Club cannot be deleted because it has linked data. Please remove all associated locations_count, sponsors_count first.',
                'errors' => [
                    'linked_data' => [
                        'locations_count' => 3,
                        'sponsors_count' => 1,
                    ],
                ],
            ]);

        // Club should still exist
        $this->assertDatabaseHas('clubs', [
            'id_club' => $club->id_club,
        ]);
    }
}
