<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Club;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionParticipation;
use App\Models\Location;
use App\Models\Player;
use App\Models\PlayerEligibility;
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
            'PlayerEligibility' => ['PlayerEligibility', 'PlayerEligibility'],
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

    public function test_player_eligibility_metadata(): void
    {
        // Create test data
        $player = Player::factory()->create();
        PlayerEligibility::factory()->create([
            'player_id' => $player->id_player,
            'phone' => '+40712345678',
        ]);

        $metadata = $this->metadataService->getMetadata('PlayerEligibility', 'v1');

        $this->assertEquals('PlayerEligibility', $metadata['schema_name']);
        $this->assertContains('phone', $metadata['searchable_fields']);
        $this->assertArrayHasKey('skill_division', $metadata['filterable_fields']);
        $this->assertEquals('float', $metadata['filterable_fields']['skill_division']['type']);
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
}
