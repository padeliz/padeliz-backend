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

    public function test_get_players_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        // Create some test data
        Player::factory()->create(['name' => 'John Doe', 'skill_division' => 2.5]);
        Player::factory()->create(['name' => 'Jane Smith', 'skill_division' => 3.0]);

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/players');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'resource',
                    'searchable_fields',
                    'filterable_fields',
                    'sortable_fields',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'resource' => 'players',
                ],
            ]);

        $this->assertContains('name', $response->json('data.searchable_fields'));
        $this->assertArrayHasKey('skill_division', $response->json('data.filterable_fields'));
        $this->assertContains('name', $response->json('data.sortable_fields'));
    }

    public function test_get_clubs_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        // Create some test data
        Club::factory()->create(['name' => 'Club A', 'city' => 'Bucharest']);
        Club::factory()->create(['name' => 'Club B', 'city' => 'Cluj']);

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/clubs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'resource',
                    'searchable_fields',
                    'filterable_fields',
                    'sortable_fields',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'resource' => 'clubs',
                ],
            ]);

        $this->assertContains('name', $response->json('data.searchable_fields'));
        $this->assertArrayHasKey('city', $response->json('data.filterable_fields'));
    }

    public function test_get_locations_metadata_with_foreign_key(): void
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
            ->getJson('/api/v1/manager/metadata/locations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'resource',
                    'searchable_fields',
                    'filterable_fields',
                    'sortable_fields',
                ],
            ]);

        $filterableFields = $response->json('data.filterable_fields');
        $this->assertArrayHasKey('club_id', $filterableFields);
        $this->assertEquals('foreign_key', $filterableFields['club_id']['type']);
        $this->assertEquals('clubs', $filterableFields['club_id']['related_model']);
        $this->assertEquals('name', $filterableFields['club_id']['searchable_field']);
    }

    public function test_invalid_resource_returns_404(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/invalid_resource');

        $response->assertStatus(404)
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_invalid_resource_name_returns_422(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/InvalidResource');

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
        $response = $this->getJson('/api/v1/manager/metadata/players');

        $response->assertStatus(401);
    }

    public function test_non_manager_user_returns_403(): void
    {
        // Create a regular player (not manager)
        /** @var Player $player */
        $player = Player::factory()->create();
        $player->addUserType('player');

        $response = $this->actingAs($player)
            ->getJson('/api/v1/manager/metadata/players');

        $response->assertStatus(403);
    }

    public function test_clear_cache_endpoint(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->deleteJson('/api/v1/manager/metadata/players/cache');

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
