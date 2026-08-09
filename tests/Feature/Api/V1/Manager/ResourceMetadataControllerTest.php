<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionParticipation;
use App\Models\Eligibility;
use App\Models\Location;
use App\Models\Player;
use App\Models\Prize;
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
        Player::factory()->create(['first_name' => 'John', 'middle_name' => null, 'last_name' => 'Doe', 'skill_division' => 2.5]);
        Player::factory()->create(['first_name' => 'Jane', 'middle_name' => null, 'last_name' => 'Smith', 'skill_division' => 3.0]);

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
                    'display_field',
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

        $this->assertContains('first_name', $response->json('data.searchable_fields'));
        $this->assertNotContains('name', $response->json('data.searchable_fields'));
        $this->assertArrayHasKey('skill_division', $response->json('data.filterable_fields'));
        $this->assertEquals('name', $response->json('data.display_field'));
        $this->assertNotContains('name', $response->json('data.sortable_fields'));
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
                    'display_field',
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
                    'display_field',
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

    /**
     * Test metadata endpoint for all models
     * @dataProvider modelDataProvider
     */
    public function test_get_metadata_for_all_models(string $modelName, string $expectedSchemaName): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson("/api/v1/manager/metadata/{$modelName}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'version',
                    'schema_name',
                    'swagger_schema',
                    'request_body',
                    'display_field',
                    'searchable_fields',
                    'filterable_fields',
                    'sortable_fields',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => $expectedSchemaName,
                ],
            ]);

        // Verify all required fields are present
        $this->assertIsString($response->json('data.version'));
        $this->assertIsString($response->json('data.schema_name'));
        $this->assertIsArray($response->json('data.swagger_schema'));
        $this->assertIsArray($response->json('data.request_body'));
        $this->assertIsArray($response->json('data.searchable_fields'));
        $this->assertIsArray($response->json('data.filterable_fields'));
        $this->assertIsArray($response->json('data.sortable_fields'));
    }

    public static function modelDataProvider(): array
    {
        return [
            'Club' => ['Club', 'Club'],
            'Division' => ['Division', 'Division'],
            'Edition' => ['Edition', 'Edition'],
            'EditionDivision' => ['EditionDivision', 'EditionDivision'],
            'EditionLottery' => ['EditionLottery', 'EditionLottery'],
            'EditionLotteryLucky' => ['EditionLotteryLucky', 'EditionLotteryLucky'],
            'EditionParticipation' => ['EditionParticipation', 'EditionParticipation'],
            'Location' => ['Location', 'Location'],
            'MatchesTeam' => ['MatchesTeam', 'MatchesTeam'],
            'Player' => ['Player', 'Player'],
            'Eligibility' => ['Eligibility', 'Eligibility'],
            'Prize' => ['Prize', 'Prize'],
            'PrizeFromClub' => ['PrizeFromClub', 'PrizeFromClub'],
        ];
    }

    public function test_division_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        // Create test data
        Division::factory()->create(['name' => 'Beginner', 'color' => '#FF0000']);
        Division::factory()->create(['name' => 'Intermediate', 'color' => '#00FF00']);

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/Division');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'Division',
                ],
            ]);

        $this->assertContains('name', $response->json('data.searchable_fields'));
        $this->assertArrayNotHasKey('is_students_only', $response->json('data.filterable_fields'));
        $this->assertArrayHasKey('its_first_edition_id', $response->json('data.filterable_fields'));
        $this->assertIsArray($response->json('data.request_body'));
    }

    public function test_edition_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        // Create test data
        Edition::factory()->create(['name' => 'Summer 2024', 'date' => '2024-06-01']);
        Edition::factory()->create(['name' => 'Winter 2024', 'date' => '2024-12-01']);

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/Edition');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'Edition',
                ],
            ]);

        $this->assertContains('name', $response->json('data.searchable_fields'));
        $this->assertIsArray($response->json('data.request_body'));
    }

    public function test_edition_participation_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        // Create test data with relationships
        $player = Player::factory()->create();
        $edition = Edition::factory()->create();
        $division = Division::factory()->create();

        $editionDivision = \App\Models\EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $division->id_division,
            'division_title' => $division->name,
        ]);

        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $editionDivision->id_edition_division,
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/EditionParticipation');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'EditionParticipation',
                ],
            ]);

        $filterableFields = $response->json('data.filterable_fields');
        $this->assertArrayHasKey('player_id', $filterableFields);
        $this->assertArrayHasKey('edition_id', $filterableFields);
        $this->assertArrayHasKey('edition_division_id', $filterableFields);

        // Test foreign key relationships
        if (isset($filterableFields['player_id']['type']) && $filterableFields['player_id']['type'] === 'foreign_key') {
            $this->assertEquals('Player', $filterableFields['player_id']['related_schema_name']);
            $this->assertEquals('first_name', $filterableFields['player_id']['related_schema_field']);
        }

        $this->assertIsArray($response->json('data.request_body'));
    }

    public function test_eligibility_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        // Create test data
        $player = Player::factory()->create();
        Eligibility::factory()->create([
            'player_id' => $player->id_player,
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/Eligibility');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'Eligibility',
                ],
            ]);

        $this->assertContains('phone', $response->json('data.searchable_fields'));
        $this->assertIsArray($response->json('data.request_body'));
    }

    public function test_prize_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        // Create test data
        Prize::factory()->create(['name' => 'First Place', 'box_1_bg_color' => '#FF0000']);
        Prize::factory()->create(['name' => 'Second Place', 'box_1_bg_color' => '#00FF00']);

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/Prize');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'Prize',
                ],
            ]);

        $this->assertContains('name', $response->json('data.searchable_fields'));
        $this->assertArrayHasKey('box_1_bg_color', $response->json('data.filterable_fields'));
        $this->assertIsArray($response->json('data.request_body'));
    }

    public function test_request_body_structure_validation(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/Club');

        $requestBody = $response->json('data.request_body');

        // Validate request body structure
        $this->assertIsArray($requestBody);

        // Check that methods are properly organized
        $expectedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        foreach ($expectedMethods as $method) {
            if (isset($requestBody[$method])) {
                $this->assertIsArray($requestBody[$method]);

                // Check that paths are properly formatted
                foreach ($requestBody[$method] as $path => $schema) {
                    $this->assertStringStartsWith('/', $path);
                    $this->assertIsArray($schema);
                }
            }
        }
    }

    public function test_swagger_schema_structure(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/Player');

        $swaggerSchema = $response->json('data.swagger_schema');

        // Validate swagger schema structure
        $this->assertIsArray($swaggerSchema);
        $this->assertArrayHasKey('type', $swaggerSchema);
        $this->assertEquals('object', $swaggerSchema['type']);
        $this->assertArrayHasKey('properties', $swaggerSchema);
        $this->assertIsArray($swaggerSchema['properties']);
    }

    public function test_display_field_functionality(): void
    {
        // Create a manager user
        /** @var Player $manager */
        $manager = Player::factory()->create();
        $manager->addUserType('manager');

        // Test models with display_field set to first searchable field
        $modelsWithDisplayField = [
            'Club' => 'name',
            'Player' => 'name',
            'Location' => 'name',
            'Division' => 'name',
            'Edition' => 'name',
            'EditionDivision' => 'division_title',
            'Prize' => 'name',
        ];

        foreach ($modelsWithDisplayField as $modelName => $expectedDisplayField) {
            $response = $this->actingAs($manager)
                ->getJson("/api/v1/manager/metadata/{$modelName}");

            $response->assertStatus(200);
            $this->assertEquals(
                $expectedDisplayField,
                $response->json('data.display_field'),
                "Model {$modelName} should have display_field set to '{$expectedDisplayField}'"
            );
        }

        // Test models with display_field set to null (no searchable fields)
        $modelsWithNullDisplayField = [
            'MatchesTeam',
            'EditionLottery',
            'EditionLotteryLucky',
            'EditionParticipation',
            'PrizeFromClub',
            'Eligibility',
        ];

        foreach ($modelsWithNullDisplayField as $modelName) {
            $response = $this->actingAs($manager)
                ->getJson("/api/v1/manager/metadata/{$modelName}");

            $response->assertStatus(200);
            $this->assertNull(
                $response->json('data.display_field'),
                "Model {$modelName} should have display_field set to null"
            );
        }
    }
}
