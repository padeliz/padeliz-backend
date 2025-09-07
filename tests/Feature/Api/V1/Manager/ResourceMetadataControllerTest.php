<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Location;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceMetadataControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_player_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        // Create some test data
        Player::factory()->create(['name' => 'John Doe', 'skill_division' => 2.5]);
        Player::factory()->create(['name' => 'Jane Smith', 'skill_division' => 3.0]);

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/Player');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'version',
                    'schema_name',
                    'swagger_schema',
                    'request_body',
                    'searchable_fields',
                    'filterable_fields',
                    'sortable_fields',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'Player',
                ],
            ]);

        $this->assertContains('name', $response->json('data.searchable_fields'));
        $this->assertArrayHasKey('skill_division', $response->json('data.filterable_fields'));
        $this->assertContains('name', $response->json('data.sortable_fields'));
        $this->assertNotNull($response->json('data.swagger_schema'));
        $this->assertIsArray($response->json('data.request_body'));
    }

    public function test_get_club_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        // Create some test data
        Club::factory()->create(['name' => 'Club A', 'city' => 'Bucharest']);
        Club::factory()->create(['name' => 'Club B', 'city' => 'Cluj']);

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/Club');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'version',
                    'schema_name',
                    'swagger_schema',
                    'request_body',
                    'searchable_fields',
                    'filterable_fields',
                    'sortable_fields',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'Club',
                ],
            ]);

        $this->assertContains('name', $response->json('data.searchable_fields'));
        $this->assertArrayHasKey('city', $response->json('data.filterable_fields'));
        $this->assertNotNull($response->json('data.swagger_schema'));
        $this->assertIsArray($response->json('data.request_body'));

        // Test request body structure for Club
        $requestBody = $response->json('data.request_body');
        $this->assertArrayHasKey('POST', $requestBody);
        $this->assertArrayHasKey('/clubs', $requestBody['POST']);
        $this->assertArrayHasKey('name', $requestBody['POST']['/clubs']);
        $this->assertArrayHasKey('city', $requestBody['POST']['/clubs']);
    }

    public function test_get_location_metadata_with_foreign_key(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        // Create test data with relationships
        /** @var Club $club */
        $club = Club::factory()->create(['name' => 'Test Club']);
        Location::factory()->create([
            'name' => 'Location A',
            'club_id' => $club->id_club,
            'total_indoors_courts' => 2,
            'total_outdoor_courts' => 3,
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/Location');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'version',
                    'schema_name',
                    'swagger_schema',
                    'request_body',
                    'searchable_fields',
                    'filterable_fields',
                    'sortable_fields',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'Location',
                ],
            ]);

        $filterableFields = $response->json('data.filterable_fields');
        $this->assertArrayHasKey('club_id', $filterableFields);
        $this->assertEquals('foreign_key', $filterableFields['club_id']['type']);
        $this->assertEquals('Club', $filterableFields['club_id']['related_schema_name']);
        $this->assertEquals('name', $filterableFields['club_id']['related_schema_field']);
        $this->assertIsArray($filterableFields['club_id']['values']); // JSON decodes objects as arrays
        $this->assertNotNull($response->json('data.swagger_schema'));
    }

    public function test_invalid_schema_returns_404(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/InvalidModel');

        $response->assertStatus(404)
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_invalid_schema_name_returns_422(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/invalidSchema');

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/manager/metadata/Player');

        $response->assertStatus(401);
    }

    public function test_non_manager_user_returns_403(): void
    {
        // Create a regular player (not manager)
        /** @var Player $player */
        $player = Player::factory()->create();
        $player->addUserType('player');

        $response = $this->actingAs($player)
            ->getJson('/api/v1/manager/metadata/Player');

        $response->assertStatus(403);
    }

    public function test_clear_cache_endpoint(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->deleteJson('/api/v1/manager/metadata/Player/cache');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Resource metadata cache cleared successfully',
            ]);
    }

    public function test_clear_all_cache_endpoint(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->deleteJson('/api/v1/manager/metadata/cache');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'All resource metadata cache cleared successfully',
            ]);
    }
}
