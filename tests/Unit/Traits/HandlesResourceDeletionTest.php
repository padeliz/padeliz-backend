<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Exceptions\ResourceDeletionException;
use App\Models\Club;
use App\Models\Location;
use App\Models\Player;
use App\Models\Prize;
use App\Traits\ApiResponse;
use App\Traits\HandlesResourceDeletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandlesResourceDeletionTest extends TestCase
{
    use RefreshDatabase;
    use HandlesResourceDeletion;
    use ApiResponse;

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

    public function test_safe_delete_resource_succeeds_without_linked_data()
    {
        $club = Club::factory()->create();
        $linkedDataRelations = [
            'locations' => 'locations_count',
            'prizes' => 'prizes_count',
        ];

        $result = $this->safeDeleteResource($club, $linkedDataRelations, 'club');

        $this->assertTrue($result);
        $this->assertDatabaseMissing('clubs', ['id_club' => $club->id_club]);
    }

    public function test_safe_delete_resource_throws_exception_with_linked_data()
    {
        $club = Club::factory()->create();
        Location::factory()->count(2)->create(['club_id' => $club->id_club]);

        $linkedDataRelations = [
            'locations' => 'locations_count',
            'prizes' => 'prizes_count',
        ];

        $this->expectException(ResourceDeletionException::class);
        $this->expectExceptionMessage('Club cannot be deleted because it has linked data. Please remove all associated locations_count, prizes_count first.');

        $this->safeDeleteResource($club, $linkedDataRelations, 'club');
    }

    public function test_safe_delete_resource_includes_linked_data_in_exception()
    {
        $club = Club::factory()->create();
        Location::factory()->count(3)->create(['club_id' => $club->id_club]);

        $linkedDataRelations = [
            'locations' => 'locations_count',
            'prizes' => 'prizes_count',
        ];

        try {
            $this->safeDeleteResource($club, $linkedDataRelations, 'club');
            $this->fail('Expected ResourceDeletionException was not thrown');
        } catch (ResourceDeletionException $e) {
            $this->assertEquals('club', $e->getResourceType());
            $this->assertEquals([
                'locations_count' => 3,
                'prizes_count' => 0,
            ], $e->getLinkedData());
        }
    }

    public function test_delete_resource_with_response_returns_success_response()
    {
        $club = Club::factory()->create();
        $linkedDataRelations = [
            'locations' => 'locations_count',
            'prizes' => 'prizes_count',
        ];

        $response = $this->deleteResourceWithResponse(
            $club,
            $linkedDataRelations,
            'club',
            'Club deleted successfully'
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([
            'success' => true,
            'message' => 'Club deleted successfully',
            'data' => null,
        ], json_decode($response->getContent(), true));
    }

    public function test_delete_resource_with_response_returns_error_response_with_linked_data()
    {
        $club = Club::factory()->create();
        Location::factory()->count(2)->create(['club_id' => $club->id_club]);

        $linkedDataRelations = [
            'locations' => 'locations_count',
            'prizes' => 'prizes_count',
        ];

        $response = $this->deleteResourceWithResponse(
            $club,
            $linkedDataRelations,
            'club'
        );

        $this->assertEquals(409, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Club cannot be deleted because it has linked data', $responseData['message']);
        $this->assertEquals([
            'locations_count' => 2,
            'prizes_count' => 0,
        ], $responseData['errors']['linked_data']);
    }

    public function test_trait_handles_multiple_linked_data_types()
    {
        $club = Club::factory()->create();
        Location::factory()->count(3)->create(['club_id' => $club->id_club]);

        $prizes = Prize::factory()->count(2)->create();
        foreach ($prizes as $prize) {
            \App\Models\PrizeFromClub::factory()->create([
                'club_id' => $club->id_club,
                'prize_id' => $prize->id_prize,
            ]);
        }

        $linkedDataRelations = [
            'locations' => 'locations_count',
            'prizes' => 'prizes_count',
        ];

        try {
            $this->safeDeleteResource($club, $linkedDataRelations, 'club');
            $this->fail('Expected ResourceDeletionException was not thrown');
        } catch (ResourceDeletionException $e) {
            $this->assertEquals([
                'locations_count' => 3,
                'prizes_count' => 2,
            ], $e->getLinkedData());
            $this->assertStringContainsString('locations_count, prizes_count', $e->getMessage());
        }
    }

    public function test_delete_bulk_resources_with_response_returns_success_response()
    {
        $clubs = Club::factory()->count(3)->create();
        $clubIds = $clubs->pluck('id_club')->toArray();

        $linkedDataRelations = [
            'locations' => 'locations_count',
            'prizes' => 'prizes_count',
        ];

        $response = $this->deleteBulkResourcesWithResponse(
            $clubIds,
            Club::class,
            $linkedDataRelations,
            'club',
            'Clubs deleted successfully'
        );

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('Clubs deleted successfully', $responseData['message']);
        $this->assertEquals(3, $responseData['data']['total_successful']);
        $this->assertEquals(0, $responseData['data']['total_failed']);
    }

    public function test_delete_bulk_resources_with_response_returns_partial_success_response()
    {
        $clubs = Club::factory()->count(3)->create();
        $clubIds = $clubs->pluck('id_club')->toArray();

        // Add linked data to the first club
        Location::factory()->count(2)->create(['club_id' => $clubs[0]->id_club]);

        $linkedDataRelations = [
            'locations' => 'locations_count',
            'prizes' => 'prizes_count',
        ];

        $response = $this->deleteBulkResourcesWithResponse(
            $clubIds,
            Club::class,
            $linkedDataRelations,
            'club'
        );

        $this->assertEquals(207, $response->getStatusCode()); // Multi-Status
        $responseData = json_decode($response->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('Some resources failed to delete', $responseData['message']);
        $this->assertEquals(2, $responseData['errors']['total_successful']);
        $this->assertEquals(1, $responseData['errors']['total_failed']);
    }

    public function test_delete_bulk_resources_with_response_returns_error_for_empty_array()
    {
        $linkedDataRelations = [
            'locations' => 'locations_count',
            'prizes' => 'prizes_count',
        ];

        $response = $this->deleteBulkResourcesWithResponse(
            [],
            Club::class,
            $linkedDataRelations,
            'club'
        );

        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('No resources provided for deletion', $responseData['message']);
    }

    public function test_delete_bulk_resources_with_response_handles_invalid_ids()
    {
        $invalidIds = [999, 1000, 1001];

        $linkedDataRelations = [
            'locations' => 'locations_count',
            'prizes' => 'prizes_count',
        ];

        $response = $this->deleteBulkResourcesWithResponse(
            $invalidIds,
            Club::class,
            $linkedDataRelations,
            'club'
        );

        $this->assertEquals(207, $response->getStatusCode()); // Multi-Status
        $responseData = json_decode($response->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('All resources failed to delete', $responseData['message']);
        $this->assertEquals(0, $responseData['errors']['total_successful']);
        $this->assertEquals(3, $responseData['errors']['total_failed']);
    }
}
