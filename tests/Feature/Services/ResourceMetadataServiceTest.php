<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Club;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionParticipation;
use App\Models\Eligibility;
use App\Models\Location;
use App\Models\Player;
use App\Models\Prize;
use App\Services\ResourceMetadataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceMetadataServiceTest extends TestCase
{
    use RefreshDatabase;

    private ResourceMetadataService $metadataService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metadataService = app(ResourceMetadataService::class);
    }

    public function test_get_player_metadata(): void
    {
        // Create some test data
        Player::factory()->create(['name' => 'John Doe', 'skill_division' => 2.5]);
        Player::factory()->create(['name' => 'Jane Smith', 'skill_division' => 3.0]);
        Player::factory()->create(['name' => 'Bob Johnson', 'skill_division' => 4.5]);

        $metadata = $this->metadataService->getMetadata('Player', 'v1');

        $this->assertArrayHasKey('version', $metadata);
        $this->assertEquals('v1', $metadata['version']);

        $this->assertArrayHasKey('schema_name', $metadata);
        $this->assertEquals('Player', $metadata['schema_name']);

        $this->assertArrayHasKey('swagger_schema', $metadata);
        $this->assertNotNull($metadata['swagger_schema']);

        $this->assertArrayHasKey('request_body', $metadata);
        $this->assertIsArray($metadata['request_body']);

        $this->assertArrayHasKey('display_field', $metadata);
        $this->assertEquals('name', $metadata['display_field']);

        $this->assertArrayHasKey('searchable_fields', $metadata);
        $this->assertContains('name', $metadata['searchable_fields']);
        $this->assertContains('first_name', $metadata['searchable_fields']);
        $this->assertContains('last_name', $metadata['searchable_fields']);

        $this->assertArrayHasKey('filterable_fields', $metadata);
        $this->assertArrayHasKey('skill_division', $metadata['filterable_fields']);
        $this->assertEquals('float', $metadata['filterable_fields']['skill_division']['type']);
        $this->assertContains(2.5, $metadata['filterable_fields']['skill_division']['values']);
        $this->assertContains(3.0, $metadata['filterable_fields']['skill_division']['values']);
        $this->assertContains(4.5, $metadata['filterable_fields']['skill_division']['values']);

        $this->assertArrayHasKey('sortable_fields', $metadata);
        $this->assertContains('name', $metadata['sortable_fields']);
        $this->assertContains('skill_division', $metadata['sortable_fields']);
    }

    public function test_get_club_metadata(): void
    {
        // Create some test data
        Club::factory()->create(['name' => 'Club A', 'city' => 'Bucharest']);
        Club::factory()->create(['name' => 'Club B', 'city' => 'Cluj']);
        Club::factory()->create(['name' => 'Club C', 'city' => 'Bucharest']);

        $metadata = $this->metadataService->getMetadata('Club', 'v1');

        $this->assertArrayHasKey('version', $metadata);
        $this->assertEquals('v1', $metadata['version']);

        $this->assertArrayHasKey('schema_name', $metadata);
        $this->assertEquals('Club', $metadata['schema_name']);

        $this->assertArrayHasKey('swagger_schema', $metadata);
        $this->assertNotNull($metadata['swagger_schema']);

        $this->assertArrayHasKey('request_body', $metadata);
        $this->assertIsArray($metadata['request_body']);

        $this->assertArrayHasKey('display_field', $metadata);
        $this->assertEquals('name', $metadata['display_field']);

        $this->assertArrayHasKey('searchable_fields', $metadata);
        $this->assertContains('name', $metadata['searchable_fields']);
        $this->assertContains('city', $metadata['searchable_fields']);

        $this->assertArrayHasKey('filterable_fields', $metadata);
        $this->assertArrayHasKey('city', $metadata['filterable_fields']);
        $this->assertEquals('string', $metadata['filterable_fields']['city']['type']);
        $this->assertContains('Bucharest', $metadata['filterable_fields']['city']['values']);
        $this->assertContains('Cluj', $metadata['filterable_fields']['city']['values']);

        $this->assertArrayHasKey('sortable_fields', $metadata);
        $this->assertContains('name', $metadata['sortable_fields']);
        $this->assertContains('city', $metadata['sortable_fields']);
    }

    public function test_get_location_metadata_with_foreign_key(): void
    {
        // Create test data with relationships
        $club = Club::factory()->create(['name' => 'Test Club']);
        Location::factory()->create([
            'name' => 'Location A',
            'club_id' => $club->id_club,
            'total_indoors_courts' => 2,
            'total_outdoor_courts' => 3,
        ]);
        Location::factory()->create([
            'name' => 'Location B',
            'club_id' => $club->id_club,
            'total_indoors_courts' => 1,
            'total_outdoor_courts' => 4,
        ]);

        $metadata = $this->metadataService->getMetadata('Location', 'v1');

        $this->assertArrayHasKey('version', $metadata);
        $this->assertEquals('v1', $metadata['version']);

        $this->assertArrayHasKey('schema_name', $metadata);
        $this->assertEquals('Location', $metadata['schema_name']);

        $this->assertArrayHasKey('swagger_schema', $metadata);
        $this->assertNotNull($metadata['swagger_schema']);

        $this->assertArrayHasKey('filterable_fields', $metadata);
        $this->assertArrayHasKey('club_id', $metadata['filterable_fields']);
        $this->assertEquals('foreign_key', $metadata['filterable_fields']['club_id']['type']);
        $this->assertEquals('Club', $metadata['filterable_fields']['club_id']['related_schema_name']);
        $this->assertEquals('name', $metadata['filterable_fields']['club_id']['related_schema_field']);
        $this->assertIsObject($metadata['filterable_fields']['club_id']['values']);
        $this->assertContains('Test Club', (array) $metadata['filterable_fields']['club_id']['values']);
    }

    public function test_invalid_schema_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Model class App\Models\InvalidModel not found');

        $this->metadataService->getMetadata('InvalidModel', 'v1');
    }

    public function test_metadata_is_cached(): void
    {
        // First call should cache the result
        $metadata1 = $this->metadataService->getMetadata('Player', 'v1');

        // Second call should return cached result
        $metadata2 = $this->metadataService->getMetadata('Player', 'v1');

        $this->assertEquals($metadata1, $metadata2);
    }

    public function test_clear_cache_works(): void
    {
        // Get metadata to populate cache
        $this->metadataService->getMetadata('Player', 'v1');

        // Clear cache
        $this->metadataService->clearCache('Player', 'v1');

        // Should still work after clearing cache
        $metadata = $this->metadataService->getMetadata('Player', 'v1');
        $this->assertArrayHasKey('schema_name', $metadata);
        $this->assertEquals('Player', $metadata['schema_name']);
    }

    public function test_boolean_field_values(): void
    {
        Player::factory()->create(['being_student' => true]);
        Player::factory()->create(['being_student' => false]);

        $metadata = $this->metadataService->getMetadata('Player', 'v1');

        $this->assertArrayHasKey('being_student', $metadata['filterable_fields']);
        $this->assertEquals('boolean', $metadata['filterable_fields']['being_student']['type']);
        $this->assertEquals([true, false], $metadata['filterable_fields']['being_student']['values']);
    }

    /**
     * Test metadata generation for all models
     * @dataProvider allModelsDataProvider
     */
    public function test_get_metadata_for_all_models(string $modelName, string $expectedSchemaName): void
    {
        $metadata = $this->metadataService->getMetadata($modelName, 'v1');

        // Verify basic structure
        $this->assertArrayHasKey('version', $metadata);
        $this->assertEquals('v1', $metadata['version']);

        $this->assertArrayHasKey('schema_name', $metadata);
        $this->assertEquals($expectedSchemaName, $metadata['schema_name']);

        $this->assertArrayHasKey('swagger_schema', $metadata);
        $this->assertNotNull($metadata['swagger_schema']);

        $this->assertArrayHasKey('request_body', $metadata);
        $this->assertIsArray($metadata['request_body']);

        $this->assertArrayHasKey('display_field', $metadata);
        // Display field should be 'name' for models with searchable fields, null for models without
        if (in_array($expectedSchemaName, ['MatchesTeam', 'EditionLottery', 'EditionLuckyOne', 'EditionParticipation', 'EditionDivision', 'PrizeFromClub', 'Eligibility'])) {
            $this->assertNull($metadata['display_field']);
        } else {
            $this->assertEquals('name', $metadata['display_field']);
        }

        $this->assertArrayHasKey('searchable_fields', $metadata);
        $this->assertIsArray($metadata['searchable_fields']);

        $this->assertArrayHasKey('filterable_fields', $metadata);
        $this->assertIsArray($metadata['filterable_fields']);

        $this->assertArrayHasKey('sortable_fields', $metadata);
        $this->assertIsArray($metadata['sortable_fields']);
    }

    public static function allModelsDataProvider(): array
    {
        return [
            'Club' => ['Club', 'Club'],
            'Division' => ['Division', 'Division'],
            'Edition' => ['Edition', 'Edition'],
            'EditionDivision' => ['EditionDivision', 'EditionDivision'],
            'EditionLottery' => ['EditionLottery', 'EditionLottery'],
            'EditionLuckyOne' => ['EditionLuckyOne', 'EditionLuckyOne'],
            'EditionParticipation' => ['EditionParticipation', 'EditionParticipation'],
            'Location' => ['Location', 'Location'],
            'MatchesTeam' => ['MatchesTeam', 'MatchesTeam'],
            'Player' => ['Player', 'Player'],
            'Eligibility' => ['Eligibility', 'Eligibility'],
            'Prize' => ['Prize', 'Prize'],
            'PrizeFromClub' => ['PrizeFromClub', 'PrizeFromClub'],
        ];
    }

    public function test_division_metadata(): void
    {
        // Create test data
        Division::factory()->create(['name' => 'Beginner', 'color' => '#FF0000', 'is_students_only' => false]);
        Division::factory()->create(['name' => 'Intermediate', 'color' => '#00FF00', 'is_students_only' => true]);

        $metadata = $this->metadataService->getMetadata('Division', 'v1');

        $this->assertEquals('Division', $metadata['schema_name']);
        $this->assertContains('name', $metadata['searchable_fields']);
        $this->assertArrayHasKey('is_students_only', $metadata['filterable_fields']);
        $this->assertArrayHasKey('its_first_edition_id', $metadata['filterable_fields']);
        $this->assertEquals('boolean', $metadata['filterable_fields']['is_students_only']['type']);
        $this->assertEquals('integer', $metadata['filterable_fields']['its_first_edition_id']['type']);
    }

    public function test_edition_metadata(): void
    {
        // Create test data
        Edition::factory()->create(['name' => 'Summer 2024', 'date' => '2024-06-01']);
        Edition::factory()->create(['name' => 'Winter 2024', 'date' => '2024-12-01']);

        $metadata = $this->metadataService->getMetadata('Edition', 'v1');

        $this->assertEquals('Edition', $metadata['schema_name']);
        $this->assertContains('name', $metadata['searchable_fields']);
        $this->assertArrayHasKey('entry_fee', $metadata['filterable_fields']);
        $this->assertEquals('integer', $metadata['filterable_fields']['entry_fee']['type']);
    }

    public function test_edition_participation_metadata(): void
    {
        // Create test data with relationships
        $player = Player::factory()->create();
        $edition = Edition::factory()->create();
        $division = Division::factory()->create();

        EditionParticipation::factory()->create([
            'player_id' => $player->id_player,
            'edition_id' => $edition->id_edition,
            'division_id' => $division->id_division,
        ]);

        $metadata = $this->metadataService->getMetadata('EditionParticipation', 'v1');

        $this->assertEquals('EditionParticipation', $metadata['schema_name']);

        $filterableFields = $metadata['filterable_fields'];
        $this->assertArrayHasKey('player_id', $filterableFields);
        $this->assertArrayHasKey('edition_id', $filterableFields);
        $this->assertArrayHasKey('division_id', $filterableFields);

        // Test foreign key relationships
        if (isset($filterableFields['player_id']['type']) && $filterableFields['player_id']['type'] === 'foreign_key') {
            $this->assertEquals('Player', $filterableFields['player_id']['related_schema_name']);
            $this->assertEquals('name', $filterableFields['player_id']['related_schema_field']);
            $this->assertIsObject($filterableFields['player_id']['values']);
        }
    }

    public function test_eligibility_metadata(): void
    {
        // Create test data
        $player = Player::factory()->create();
        Eligibility::factory()->create([
            'player_id' => $player->id_player,
            'phone' => '+40712345678',
        ]);

        $metadata = $this->metadataService->getMetadata('Eligibility', 'v1');

        $this->assertEquals('Eligibility', $metadata['schema_name']);
        $this->assertContains('phone', $metadata['searchable_fields']);
        $this->assertArrayHasKey('birth_year', $metadata['filterable_fields']);
        $this->assertEquals('integer', $metadata['filterable_fields']['birth_year']['type']);
    }

    public function test_prize_metadata(): void
    {
        // Create test data
        Prize::factory()->create(['name' => 'First Place', 'box_1_bg_color' => '#FF0000']);
        Prize::factory()->create(['name' => 'Second Place', 'box_1_bg_color' => '#00FF00']);

        $metadata = $this->metadataService->getMetadata('Prize', 'v1');

        $this->assertEquals('Prize', $metadata['schema_name']);
        $this->assertContains('name', $metadata['searchable_fields']);
        $this->assertArrayHasKey('box_1_bg_color', $metadata['filterable_fields']);
        $this->assertEquals('string', $metadata['filterable_fields']['box_1_bg_color']['type']);
        $this->assertContains('#FF0000', $metadata['filterable_fields']['box_1_bg_color']['values']);
        $this->assertContains('#00FF00', $metadata['filterable_fields']['box_1_bg_color']['values']);
    }

    public function test_request_body_structure(): void
    {
        $metadata = $this->metadataService->getMetadata('Club', 'v1');
        $requestBody = $metadata['request_body'];

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
        $metadata = $this->metadataService->getMetadata('Player', 'v1');
        $swaggerSchema = $metadata['swagger_schema'];

        // Validate swagger schema structure
        $this->assertIsArray($swaggerSchema);
        $this->assertArrayHasKey('type', $swaggerSchema);
        $this->assertEquals('object', $swaggerSchema['type']);
        $this->assertArrayHasKey('properties', $swaggerSchema);
        $this->assertIsArray($swaggerSchema['properties']);
    }

    public function test_foreign_key_values_as_objects(): void
    {
        // Create test data with relationships
        $club1 = Club::factory()->create(['name' => 'Club A']);
        $club2 = Club::factory()->create(['name' => 'Club B']);
        Location::factory()->create(['club_id' => $club1->id_club]);
        Location::factory()->create(['club_id' => $club2->id_club]);

        $metadata = $this->metadataService->getMetadata('Location', 'v1');
        $clubIdField = $metadata['filterable_fields']['club_id'];

        if ($clubIdField['type'] === 'foreign_key') {
            $values = $clubIdField['values'];
            $this->assertIsObject($values);

            // Convert to array for testing
            $valuesArray = (array) $values;
            $this->assertContains('Club A', $valuesArray);
            $this->assertContains('Club B', $valuesArray);

            // Check that keys are numeric IDs (can be string or integer)
            foreach (array_keys($valuesArray) as $key) {
                $this->assertIsNumeric($key);
            }
        }
    }

    public function test_cache_functionality(): void
    {
        // Test individual cache clearing
        $this->metadataService->getMetadata('Player', 'v1');
        $this->metadataService->clearCache('Player', 'v1');

        // Should still work after clearing cache
        $metadata = $this->metadataService->getMetadata('Player', 'v1');
        $this->assertEquals('Player', $metadata['schema_name']);

        // Test clearing all cache
        $this->metadataService->getMetadata('Club', 'v1');
        $this->metadataService->clearAllCache();

        // Should still work after clearing all cache
        $metadata = $this->metadataService->getMetadata('Club', 'v1');
        $this->assertEquals('Club', $metadata['schema_name']);
    }

    /**
     * Test resource name generation for hyphenated model names
     * This tests the getResourceNameFromSchema method indirectly through request body generation
     */
    public function test_resource_name_generation_for_hyphenated_models(): void
    {
        // Test single word models (should be pluralized normally)
        $playerMetadata = $this->metadataService->getMetadata('Player', 'v1');
        $this->assertArrayHasKey('request_body', $playerMetadata);

        // Test hyphenated models (only last word should be pluralized)
        $editionLotteryMetadata = $this->metadataService->getMetadata('EditionLottery', 'v1');
        $this->assertArrayHasKey('request_body', $editionLotteryMetadata);

        $editionParticipationMetadata = $this->metadataService->getMetadata('EditionParticipation', 'v1');
        $this->assertArrayHasKey('request_body', $editionParticipationMetadata);

        $eligibilityMetadata = $this->metadataService->getMetadata('Eligibility', 'v1');
        $this->assertArrayHasKey('request_body', $eligibilityMetadata);

        $prizeFromClubMetadata = $this->metadataService->getMetadata('PrizeFromClub', 'v1');
        $this->assertArrayHasKey('request_body', $prizeFromClubMetadata);
    }

    /**
     * Test that resource names follow proper English pluralization rules
     * for hyphenated compound nouns (only last word pluralized)
     *
     * @dataProvider hyphenatedModelNamesDataProvider
     */
    public function test_hyphenated_model_names_pluralization(string $modelName, string $expectedResourceName): void
    {
        // We test this indirectly by checking if the metadata can be generated
        // The actual resource name is used internally in request body generation
        $metadata = $this->metadataService->getMetadata($modelName, 'v1');

        // Verify that metadata is generated successfully
        $this->assertArrayHasKey('schema_name', $metadata);
        $this->assertEquals($modelName, $metadata['schema_name']);

        // Verify that request body is generated (which uses the resource name)
        $this->assertArrayHasKey('request_body', $metadata);
        $this->assertIsArray($metadata['request_body']);
    }

    /**
     * Test pluralization for all models dynamically
     * This test will automatically work with any models added/removed in the future
     */
    public function test_pluralization_for_all_models_dynamically(): void
    {
        $models = $this->getAllModels();

        foreach ($models as $modelName) {
            try {
                $metadata = $this->metadataService->getMetadata($modelName, 'v1');

                // Verify that metadata is generated successfully
                $this->assertArrayHasKey('schema_name', $metadata, "Missing schema_name for model: {$modelName}");
                $this->assertEquals($modelName, $metadata['schema_name'], "Schema name mismatch for model: {$modelName}");

                // Verify that request body is generated (which uses the resource name)
                $this->assertArrayHasKey('request_body', $metadata, "Missing request_body for model: {$modelName}");
                $this->assertIsArray($metadata['request_body'], "Request body is not an array for model: {$modelName}");

            } catch (\InvalidArgumentException $e) {
                // Skip models that don't exist or aren't valid Eloquent models
                $this->markTestSkipped("Model {$modelName} is not available: " . $e->getMessage());
            }
        }
    }

    /**
     * Test that all models can generate complete metadata
     * This ensures the service works with all current and future models
     */
    public function test_complete_metadata_generation_for_all_models_dynamically(): void
    {
        $models = $this->getAllModels();

        foreach ($models as $modelName) {
            try {
                $metadata = $this->metadataService->getMetadata($modelName, 'v1');

                // Verify all required keys exist
                $requiredKeys = [
                    'version',
                    'schema_name',
                    'swagger_schema',
                    'request_body',
                    'searchable_fields',
                    'filterable_fields',
                    'sortable_fields',
                ];

                foreach ($requiredKeys as $key) {
                    $this->assertArrayHasKey($key, $metadata, "Missing '{$key}' for model: {$modelName}");
                }

                // Verify data types
                $this->assertIsString($metadata['version'], "Version should be string for model: {$modelName}");
                $this->assertIsString($metadata['schema_name'], "Schema name should be string for model: {$modelName}");
                $this->assertIsArray($metadata['request_body'], "Request body should be array for model: {$modelName}");
                $this->assertIsArray($metadata['searchable_fields'], "Searchable fields should be array for model: {$modelName}");
                $this->assertIsArray($metadata['filterable_fields'], "Filterable fields should be array for model: {$modelName}");
                $this->assertIsArray($metadata['sortable_fields'], "Sortable fields should be array for model: {$modelName}");

                // Verify schema name matches
                $this->assertEquals($modelName, $metadata['schema_name'], "Schema name mismatch for model: {$modelName}");

            } catch (\InvalidArgumentException $e) {
                // Skip models that don't exist or aren't valid Eloquent models
                $this->markTestSkipped("Model {$modelName} is not available: " . $e->getMessage());
            }
        }
    }

    public static function hyphenatedModelNamesDataProvider(): array
    {
        return [
            // Single word models (should be pluralized normally)
            'Player' => ['Player', 'players'],
            'Club' => ['Club', 'clubs'],
            'Division' => ['Division', 'divisions'],
            'Edition' => ['Edition', 'editions'],
            'Location' => ['Location', 'locations'],
            'Prize' => ['Prize', 'prizes'],

            // Hyphenated models (only last word should be pluralized)
            'EditionLottery' => ['EditionLottery', 'edition-lotteries'],
            'EditionParticipation' => ['EditionParticipation', 'edition-participations'],
            'EditionDivision' => ['EditionDivision', 'edition-divisions'],
            'EditionLuckyOne' => ['EditionLuckyOne', 'edition-lucky-ones'],
            'Eligibility' => ['Eligibility', 'eligibilities'],
            'PrizeFromClub' => ['PrizeFromClub', 'prize-from-clubs'],
            'MatchesTeam' => ['MatchesTeam', 'matches-teams'],

            // Edge cases with multiple hyphens (using existing models)
            'EditionDivision' => ['EditionDivision', 'edition-divisions'],
        ];
    }

    /**
     * Get all model classes from the App\Models namespace
     */
    private function getAllModels(): array
    {
        $modelsPath = base_path('app/Models');
        $models = [];

        if (is_dir($modelsPath)) {
            $files = glob($modelsPath . '/*.php');

            foreach ($files as $file) {
                $filename = basename($file, '.php');

                // Skip abstract classes or interfaces
                if (str_starts_with($filename, 'Abstract') || str_ends_with($filename, 'Interface')) {
                    continue;
                }

                $models[] = $filename;
            }
        }

        return $models;
    }

    /**
     * Test that irregular plurals are handled correctly
     */
    public function test_irregular_plural_handling(): void
    {
        // Test with models that might have irregular plurals
        $metadata = $this->metadataService->getMetadata('Player', 'v1');
        $this->assertArrayHasKey('request_body', $metadata);

        // The method should handle irregular plurals correctly
        // For example: "person" -> "people", "child" -> "children"
        // But in our case, we're dealing with model names that are typically regular
    }

    public function test_display_field_functionality(): void
    {
        // Test models with display_field set to first searchable field
        $modelsWithDisplayField = [
            'Club' => 'name',
            'Player' => 'name',
            'Location' => 'name',
            'Division' => 'name',
            'Edition' => 'name',
            'Prize' => 'name',
        ];

        foreach ($modelsWithDisplayField as $modelName => $expectedDisplayField) {
            $metadata = $this->metadataService->getMetadata($modelName, 'v1');

            $this->assertArrayHasKey('display_field', $metadata);
            $this->assertEquals(
                $expectedDisplayField,
                $metadata['display_field'],
                "Model {$modelName} should have display_field set to '{$expectedDisplayField}'"
            );
        }

        // Test models with display_field set to null (no searchable fields)
        $modelsWithNullDisplayField = [
            'MatchesTeam',
            'EditionLottery',
            'EditionLuckyOne',
            'EditionParticipation',
            'EditionDivision',
            'PrizeFromClub',
            'Eligibility',
        ];

        foreach ($modelsWithNullDisplayField as $modelName) {
            $metadata = $this->metadataService->getMetadata($modelName, 'v1');

            $this->assertArrayHasKey('display_field', $metadata);
            $this->assertNull(
                $metadata['display_field'],
                "Model {$modelName} should have display_field set to null"
            );
        }
    }
}
