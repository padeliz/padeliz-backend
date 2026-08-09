<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Location;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubBulkDeletionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);
    }

    public function test_api_returns_200_when_bulk_deleting_clubs_without_linked_data()
    {
        Sanctum::actingAs($this->manager);

        $clubs = Club::factory()->count(5)->create();
        $clubIds = $clubs->pluck('id_club')->toArray();

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', [
            'club_ids' => $clubIds,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Clubs deleted successfully',
                'data' => [
                    'total_requested' => 5,
                    'total_successful' => 5,
                    'total_failed' => 0,
                ],
            ]);

        // Verify all clubs are deleted
        foreach ($clubIds as $clubId) {
            $this->assertDatabaseMissing('clubs', ['id_club' => $clubId]);
        }
    }

    public function test_api_returns_207_when_bulk_deleting_clubs_with_partial_linked_data()
    {
        Sanctum::actingAs($this->manager);

        $clubs = Club::factory()->count(4)->create();
        $clubIds = $clubs->pluck('id_club')->toArray();

        // Add linked data to the first two clubs
        Location::factory()->count(2)->create(['club_id' => $clubs[0]->id_club]);
        Location::factory()->count(1)->create(['club_id' => $clubs[1]->id_club]);

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', [
            'club_ids' => $clubIds,
        ]);

        $response->assertStatus(207) // Multi-Status
            ->assertJson([
                'success' => false,
                'message' => 'Some resources failed to delete',
                'errors' => [
                    'total_requested' => 4,
                    'total_successful' => 2,
                    'total_failed' => 2,
                ],
            ]);

        // Verify successful deletions
        $this->assertDatabaseMissing('clubs', ['id_club' => $clubs[2]->id_club]);
        $this->assertDatabaseMissing('clubs', ['id_club' => $clubs[3]->id_club]);

        // Verify failed deletions (clubs with linked data still exist)
        $this->assertDatabaseHas('clubs', ['id_club' => $clubs[0]->id_club]);
        $this->assertDatabaseHas('clubs', ['id_club' => $clubs[1]->id_club]);
    }

    public function test_api_returns_400_when_bulk_deleting_with_empty_array()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', [
            'club_ids' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_ids']);
    }

    public function test_api_returns_422_when_bulk_deleting_without_club_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_ids']);
    }

    public function test_api_returns_422_when_bulk_deleting_with_invalid_club_ids_format()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', [
            'club_ids' => 'not-an-array',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_ids']);
    }

    public function test_api_returns_422_when_bulk_deleting_with_non_integer_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', [
            'club_ids' => ['invalid', 'ids'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_ids.0', 'club_ids.1']);
    }

    public function test_api_returns_207_when_bulk_deleting_with_mixed_scenarios()
    {
        Sanctum::actingAs($this->manager);

        $clubs = Club::factory()->count(3)->create();
        $clubIds = $clubs->pluck('id_club')->toArray();

        // Add linked data to the first club
        Location::factory()->count(2)->create(['club_id' => $clubs[0]->id_club]);

        // Add invalid IDs
        $mixedIds = array_merge($clubIds, [999, 1000]);

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', [
            'club_ids' => $mixedIds,
        ]);

        $response->assertStatus(207) // Multi-Status
            ->assertJson([
                'success' => false,
                'message' => 'Some resources failed to delete',
                'errors' => [
                    'total_requested' => 5,
                    'total_successful' => 2, // 2 valid clubs without linked data
                    'total_failed' => 3,     // 1 with linked data + 2 invalid IDs
                ],
            ]);

        // Verify successful deletions
        $this->assertDatabaseMissing('clubs', ['id_club' => $clubs[1]->id_club]);
        $this->assertDatabaseMissing('clubs', ['id_club' => $clubs[2]->id_club]);

        // Verify failed deletion (club with linked data still exists)
        $this->assertDatabaseHas('clubs', ['id_club' => $clubs[0]->id_club]);
    }

    public function test_api_returns_detailed_error_information_for_failed_deletions()
    {
        Sanctum::actingAs($this->manager);

        $clubs = Club::factory()->count(2)->create();
        $clubIds = $clubs->pluck('id_club')->toArray();

        // Add linked data to the first club
        Location::factory()->count(3)->create(['club_id' => $clubs[0]->id_club]);

        $response = $this->deleteJson('/api/v1/manager/clubs/bulk', [
            'club_ids' => $clubIds,
        ]);

        $response->assertStatus(207)
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
            ]);

        $responseData = $response->json('errors');

        // Check successful array
        $this->assertCount(1, $responseData['successful']);
        $this->assertEquals($clubs[1]->id_club, $responseData['successful'][0]['id']);

        // Check failed array
        $this->assertCount(1, $responseData['failed']);
        $this->assertEquals($clubs[0]->id_club, $responseData['failed'][0]['id']);
        $this->assertStringContainsString('Club cannot be deleted because it has linked data', $responseData['failed'][0]['reason']);
        $this->assertArrayHasKey('linked_data', $responseData['failed'][0]);
        $this->assertEquals(3, $responseData['failed'][0]['linked_data']['locations_count']);
    }
}
