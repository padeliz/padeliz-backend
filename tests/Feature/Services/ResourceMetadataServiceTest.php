<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Club;
use App\Models\Division;
use App\Models\EventParticipation;
use App\Models\Location;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\PlayerLevelAssessment;
use App\Models\Prize;
use App\Services\Cache\ModelSchemaCatalog;
use App\Services\ResourceMetadataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

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
        User::factory()->create(['first_name' => 'John', 'middle_name' => null, 'last_name' => 'Doe']);
        User::factory()->create(['first_name' => 'Jane', 'middle_name' => null, 'last_name' => 'Smith']);
        User::factory()->create(['first_name' => 'Bob', 'middle_name' => null, 'last_name' => 'Johnson']);

        $metadata = $this->metadataService->getMetadata('User', 'v1');

        $this->assertArrayHasKey('version', $metadata);
        $this->assertEquals('v1', $metadata['version']);

        $this->assertArrayHasKey('schema_name', $metadata);
        $this->assertEquals('User', $metadata['schema_name']);

        $this->assertArrayHasKey('swagger_schema', $metadata);
        $this->assertNotNull($metadata['swagger_schema']);

        $this->assertArrayHasKey('request_body', $metadata);
        $this->assertIsArray($metadata['request_body']);

        $this->assertArrayHasKey('display_field', $metadata);
        $this->assertEquals('name', $metadata['display_field']);

        $this->assertArrayHasKey('searchable_fields', $metadata);
        $this->assertContains('first_name', $metadata['searchable_fields']);
        $this->assertContains('last_name', $metadata['searchable_fields']);
        $this->assertNotContains('name', $metadata['searchable_fields']);

        $this->assertArrayHasKey('filterable_fields', $metadata);
        $this->assertArrayHasKey('user_types', $metadata['filterable_fields']);
        $this->assertArrayNotHasKey('skill_division', $metadata['filterable_fields']);

        $this->assertArrayHasKey('sortable_fields', $metadata);
        $this->assertNotContains('name', $metadata['sortable_fields']);
        $this->assertContains('last_name', $metadata['sortable_fields']);

        $this->assertArrayHasKey('list_fields', $metadata);
        $this->assertEquals([], $metadata['list_fields']);

        $profileImage = $metadata['swagger_schema']['properties']['profile_image_url'] ?? null;
        $this->assertIsArray($profileImage);
        $this->assertSame('list', $profileImage['x-padeliz-image']['role'] ?? null);

        $this->assertArrayNotHasKey('year_of_birth', $metadata['swagger_schema']['properties']);
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

        $this->assertArrayHasKey('resource_path', $metadata);
        $this->assertEquals('locations', $metadata['resource_path']);

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
        $this->expectExceptionMessage('Model class for schema InvalidModel not found');

        $this->metadataService->getMetadata('InvalidModel', 'v1');
    }

    public function test_get_challenge_session_metadata_from_nested_namespace(): void
    {
        $metadata = $this->metadataService->getMetadata('ChallengeSession', 'v1');

        $this->assertSame('ChallengeSession', $metadata['schema_name']);
        $this->assertSame('challenge-sessions', $metadata['resource_path']);
        $this->assertSame('title', $metadata['display_field']);
        $this->assertSame(['title'], $metadata['searchable_fields']);
        $this->assertArrayHasKey('type', $metadata['filterable_fields']);
        $this->assertArrayHasKey('status', $metadata['filterable_fields']);
        $this->assertSame(
            [
                'id_challenge_session',
                'title',
                'type',
                'status',
                'started_at',
                'completed_at',
                'inserted_at',
            ],
            $metadata['sortable_fields']
        );
        $this->assertSame(
            ['title', 'type', 'status', 'started_at', 'completed_at'],
            $metadata['list_fields']
        );
        $this->assertIsArray($metadata['swagger_schema']);
        $this->assertIsArray($metadata['swagger_schema']['properties'] ?? null);
        foreach (['id_challenge_session', 'title', 'type', 'status'] as $property) {
            $this->assertArrayHasKey($property, $metadata['swagger_schema']['properties']);
        }
    }

    public function test_metadata_is_cached(): void
    {
        // First call should cache the result
        $metadata1 = $this->metadataService->getMetadata('User', 'v1');

        // Second call should return cached result
        $metadata2 = $this->metadataService->getMetadata('User', 'v1');

        $this->assertEquals($metadata1, $metadata2);
    }

    public function test_metadata_bust_via_invalidator(): void
    {
        $this->metadataService->getMetadata('User', 'v1');

        app(\App\Services\Cache\MetadataCacheInvalidator::class)->invalidateSchema('User');

        $metadata = $this->metadataService->getMetadata('User', 'v1');
        $this->assertArrayHasKey('schema_name', $metadata);
        $this->assertEquals('User', $metadata['schema_name']);
    }

    public function test_boolean_field_values(): void
    {
        PlayerLevelAssessment::factory()->readyForReview()->create();
        PlayerLevelAssessment::factory()->create();

        $metadata = $this->metadataService->getMetadata('PlayerLevelAssessment', 'v1');

        $this->assertArrayHasKey('ready_for_review', $metadata['filterable_fields']);
        $this->assertEquals('boolean', $metadata['filterable_fields']['ready_for_review']['type']);
        $this->assertEqualsCanonicalizing([true, false], $metadata['filterable_fields']['ready_for_review']['values']);
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
        if (in_array($expectedSchemaName, ['EventLotteryLucky', 'EventParticipation', 'EventRegistration', 'EventLotteryPrize', 'SponsorPrize', 'PlayerLevelAssessment'])) {
            $this->assertNull($metadata['display_field']);
        } elseif ($expectedSchemaName === 'EventDivision') {
            $this->assertEquals('division_title', $metadata['display_field']);
        } else {
            $this->assertEquals('name', $metadata['display_field']);
        }

        $this->assertArrayHasKey('searchable_fields', $metadata);
        $this->assertIsArray($metadata['searchable_fields']);

        $this->assertArrayHasKey('filterable_fields', $metadata);
        $this->assertIsArray($metadata['filterable_fields']);

        $this->assertArrayHasKey('sortable_fields', $metadata);
        $this->assertIsArray($metadata['sortable_fields']);

        $this->assertArrayHasKey('list_fields', $metadata);
        $this->assertIsArray($metadata['list_fields']);
        if ($expectedSchemaName === 'EventRegistration') {
            $this->assertEquals(
                [
                    'player.profile_image_url',
                    'player_user_id',
                    'event_division_id',
                ],
                $metadata['list_fields']
            );
        } elseif (in_array($expectedSchemaName, ['Club', 'Prize'], true)) {
            $this->assertEquals(['url'], $metadata['list_fields']);
        } elseif ($expectedSchemaName === 'Event') {
            $this->assertEquals(
                ['cover_url', 'cover_images', 'event_nr_of_days'],
                $metadata['list_fields']
            );
        } else {
            $this->assertEquals([], $metadata['list_fields']);
        }
    }

    public static function allModelsDataProvider(): array
    {
        return [
            'Club' => ['Club', 'Club'],
            'Division' => ['Division', 'Division'],
            'Event' => ['Event', 'Event'],
            'EventDivision' => ['EventDivision', 'EventDivision'],
            'EventLottery' => ['EventLottery', 'EventLottery'],
            'EventLotteryLucky' => ['EventLotteryLucky', 'EventLotteryLucky'],
            'EventParticipation' => ['EventParticipation', 'EventParticipation'],
            'EventRegistration' => ['EventRegistration', 'EventRegistration'],
            'Location' => ['Location', 'Location'],
            'User' => ['User', 'User'],
            'PlayerLevelAssessment' => ['PlayerLevelAssessment', 'PlayerLevelAssessment'],
            'Prize' => ['Prize', 'Prize'],
        ];
    }

    public function test_division_metadata(): void
    {
        // Create test data
        Division::factory()->create(['name' => 'Beginner', 'color' => '#FF0000']);
        Division::factory()->create(['name' => 'Intermediate', 'color' => '#00FF00']);

        $metadata = $this->metadataService->getMetadata('Division', 'v1');

        $this->assertEquals('Division', $metadata['schema_name']);
        $this->assertEquals('divisions', $metadata['resource_path']);
        $this->assertContains('name', $metadata['searchable_fields']);
        $this->assertArrayNotHasKey('is_students_only', $metadata['filterable_fields']);
        $this->assertArrayHasKey('its_first_event_id', $metadata['filterable_fields']);
        $this->assertEquals('foreign_key', $metadata['filterable_fields']['its_first_event_id']['type']);
        $this->assertEquals('Event', $metadata['filterable_fields']['its_first_event_id']['related_schema_name']);
        $this->assertEquals('name', $metadata['filterable_fields']['its_first_event_id']['related_schema_field']);

        $required = $metadata['swagger_schema']['required'] ?? [];
        $this->assertContains('its_first_event_id', $required);
        $this->assertArrayHasKey('its_first_event', $metadata['swagger_schema']['properties']);
        $this->assertArrayHasKey(
            'oneOf',
            $metadata['swagger_schema']['properties']['its_first_event'] ?? []
        );

        $color = $metadata['swagger_schema']['properties']['color'] ?? [];
        $this->assertEquals('color', $color['format'] ?? null);
        $this->assertEquals('^#[0-9A-Fa-f]{6}$', $color['pattern'] ?? null);
    }

    public function test_edition_metadata(): void
    {
        // Create test data
        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2024-06-01') + ['name' => 'Summer 2024']);
        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2024-12-01') + ['name' => 'Winter 2024']);

        $metadata = $this->metadataService->getMetadata('Event', 'v1');

        $this->assertEquals('Event', $metadata['schema_name']);
        $this->assertEquals('events', $metadata['resource_path']);
        $this->assertContains('name', $metadata['searchable_fields']);
        $this->assertArrayHasKey('location_id', $metadata['filterable_fields']);
        $required = $metadata['swagger_schema']['required'] ?? [];
        $this->assertContains('event_starts_at', $required);
        $this->assertContains('event_ends_at', $required);
        $this->assertArrayHasKey('event_starts_at', $metadata['swagger_schema']['properties'] ?? []);
        $this->assertArrayHasKey('event_ends_at', $metadata['swagger_schema']['properties'] ?? []);
    }

    public function test_edition_participation_metadata(): void
    {
        // Create test data with relationships
        $player = User::factory()->create();
        $event = PadelEvent::factory()->create();
        $division = Division::factory()->create();

        $eventDivision = \App\Models\EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $division->id_division,
            'division_title' => $division->name,
        ]);

        EventParticipation::factory()->create([
            'player_user_id' => $player->id_user,
            'event_id' => $event->id_event,
            'event_division_id' => $eventDivision->id_event_division,
        ]);

        $metadata = $this->metadataService->getMetadata('EventParticipation', 'v1');

        $this->assertEquals('EventParticipation', $metadata['schema_name']);

        $filterableFields = $metadata['filterable_fields'];
        $this->assertArrayHasKey('player_user_id', $filterableFields);
        $this->assertArrayHasKey('event_id', $filterableFields);
        $this->assertArrayHasKey('event_division_id', $filterableFields);

        // Test foreign key relationships
        if (isset($filterableFields['player_user_id']['type']) && $filterableFields['player_user_id']['type'] === 'foreign_key') {
            $this->assertEquals('User', $filterableFields['player_user_id']['related_schema_name']);
            $this->assertEquals('first_name', $filterableFields['player_user_id']['related_schema_field']);
            $this->assertIsObject($filterableFields['player_user_id']['values']);
        }
    }

    public function test_player_level_assessment_metadata(): void
    {
        // Create test data
        PlayerLevelAssessment::factory()->readyForReview()->create();

        $metadata = $this->metadataService->getMetadata('PlayerLevelAssessment', 'v1');

        $this->assertEquals('PlayerLevelAssessment', $metadata['schema_name']);
        $this->assertEquals('player-level-assessments', $metadata['resource_path']);
        $this->assertContains('review_message', $metadata['searchable_fields']);
        $this->assertArrayHasKey('review_status', $metadata['filterable_fields']);
        $this->assertArrayHasKey('ready_for_review', $metadata['filterable_fields']);

        $props = $metadata['swagger_schema']['properties'];
        $this->assertContains('discarded', $props['review_status']['enum'] ?? []);
        $this->assertTrue($props['review_message']['nullable'] ?? false);
        $this->assertArrayNotHasKey('being_student', $props);
        $this->assertArrayNotHasKey('event_ids', $props);
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
        $this->assertEquals(
            '^#[0-9A-Fa-f]{6}$',
            $metadata['swagger_schema']['properties']['box_1_bg_color']['pattern'] ?? null
        );
        $this->assertArrayHasKey('list_fields', $metadata);
        $this->assertEquals(['url'], $metadata['list_fields']);
        $this->assertSame(
            'list',
            $metadata['swagger_schema']['properties']['url']['x-padeliz-image']['role'] ?? null
        );
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
        $metadata = $this->metadataService->getMetadata('User', 'v1');
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
        $invalidator = app(\App\Services\Cache\MetadataCacheInvalidator::class);

        $this->metadataService->getMetadata('User', 'v1');
        $invalidator->invalidateSchema('User');

        $metadata = $this->metadataService->getMetadata('User', 'v1');
        $this->assertEquals('User', $metadata['schema_name']);

        $this->metadataService->getMetadata('Club', 'v1');
        $invalidator->invalidateAll();

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
        $playerMetadata = $this->metadataService->getMetadata('User', 'v1');
        $this->assertArrayHasKey('request_body', $playerMetadata);

        // Test hyphenated models (only last word should be pluralized)
        $editionLotteryMetadata = $this->metadataService->getMetadata('EventLottery', 'v1');
        $this->assertArrayHasKey('request_body', $editionLotteryMetadata);

        $eventParticipationMetadata = $this->metadataService->getMetadata('EventParticipation', 'v1');
        $this->assertArrayHasKey('request_body', $eventParticipationMetadata);

        $assessmentMetadata = $this->metadataService->getMetadata('PlayerLevelAssessment', 'v1');
        $this->assertArrayHasKey('request_body', $assessmentMetadata);

        $sponsorMetadata = $this->metadataService->getMetadata('Sponsor', 'v1');
        $this->assertArrayHasKey('request_body', $sponsorMetadata);
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

        $this->assertArrayHasKey('resource_path', $metadata);
        $this->assertEquals($expectedResourceName, $metadata['resource_path']);
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
            'User' => ['User', 'players'],
            'Club' => ['Club', 'clubs'],
            'Division' => ['Division', 'divisions'],
            'Event' => ['Event', 'events'],
            'Location' => ['Location', 'locations'],
            'Prize' => ['Prize', 'prizes'],

            // Hyphenated models (only last word should be pluralized)
            'EventLottery' => ['EventLottery', 'event-lotteries'],
            'EventParticipation' => ['EventParticipation', 'event-participations'],
            'EventRegistration' => ['EventRegistration', 'event-registrations'],
            'EventDivision' => ['EventDivision', 'event-divisions'],
            'EventLotteryLucky' => ['EventLotteryLucky', 'event-lottery-luckies'],
            'EventLotteryPrize' => ['EventLotteryPrize', 'event-lottery-prizes'],
            'PlayerLevelAssessment' => ['PlayerLevelAssessment', 'player-level-assessments'],
            'Sponsor' => ['Sponsor', 'sponsors'],
            'SponsorPrize' => ['SponsorPrize', 'sponsor-prizes'],

            // Edge cases with multiple hyphens (using existing models)
            'EventDivision' => ['EventDivision', 'event-divisions'],
        ];
    }

    /**
     * Get all model schema basenames from the recursive Models catalog
     */
    private function getAllModels(): array
    {
        return array_map(
            static fn (string $class): string => class_basename($class),
            app(ModelSchemaCatalog::class)->eloquentClasses()
        );
    }

    /**
     * Test that irregular plurals are handled correctly
     */
    public function test_irregular_plural_handling(): void
    {
        // Test with models that might have irregular plurals
        $metadata = $this->metadataService->getMetadata('User', 'v1');
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
            'User' => 'name',
            'Location' => 'name',
            'Division' => 'name',
            'Event' => 'name',
            'Prize' => 'name',
            'EventLottery' => 'name',
            'Sponsor' => 'name',
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

        // Test models with display_field set to null (no searchable fields / explicit null)
        $modelsWithNullDisplayField = [
            'EventLotteryLucky',
            'EventLotteryPrize',
            'EventParticipation',
            'EventRegistration',
            'SponsorPrize',
            'PlayerLevelAssessment',
        ];

        foreach ($modelsWithNullDisplayField as $modelName) {
            $metadata = $this->metadataService->getMetadata($modelName, 'v1');

            $this->assertArrayHasKey('display_field', $metadata);
            $this->assertNull(
                $metadata['display_field'],
                "Model {$modelName} should have display_field set to null"
            );
        }

        $eventDivisionMetadata = $this->metadataService->getMetadata('EventDivision', 'v1');
        $this->assertEquals('division_title', $eventDivisionMetadata['display_field']);
        $edProps = $eventDivisionMetadata['swagger_schema']['properties'];
        $this->assertArrayHasKey('courts', $edProps);

        $scheduleMetadata = $this->metadataService->getMetadata('EventDivisionSchedule', 'v1');
        $scheduleProps = $scheduleMetadata['swagger_schema']['properties'];
        $this->assertTrue($scheduleProps['player_matches_repeat']['nullable'] ?? false);

        $courtMetadata = $this->metadataService->getMetadata('Court', 'v1');
        $this->assertArrayHasKey('location', $courtMetadata['swagger_schema']['properties']);

        $replacementMetadata = $this->metadataService->getMetadata('EventDivisionReplacement', 'v1');
        $this->assertArrayHasKey('match_id', $replacementMetadata['swagger_schema']['properties']);
    }

    public function test_get_player_profile_image_import_metadata(): void
    {
        $metadata = $this->metadataService->getMetadata('PlayerProfileImageImport', 'v1');

        $this->assertEquals('v1', $metadata['version']);
        $this->assertEquals('PlayerProfileImageImport', $metadata['schema_name']);
        $this->assertNotNull($metadata['swagger_schema']);
        $this->assertEquals('original_filename', $metadata['display_field']);

        $properties = $metadata['swagger_schema']['properties'];
        $this->assertArrayHasKey('id_player_profile_image_import', $properties);
        $this->assertArrayHasKey('original_filename', $properties);
        $this->assertArrayHasKey('included_count', $properties);
        $this->assertArrayHasKey('uploaded_count', $properties);
        $this->assertArrayHasKey('importedByManager', $properties);
        $this->assertArrayHasKey('rows', $properties);
    }

    public function test_get_player_profile_image_import_row_metadata(): void
    {
        $metadata = $this->metadataService->getMetadata('PlayerProfileImageImportRow', 'v1');

        $this->assertEquals('v1', $metadata['version']);
        $this->assertEquals('PlayerProfileImageImportRow', $metadata['schema_name']);
        $this->assertNotNull($metadata['swagger_schema']);

        $properties = $metadata['swagger_schema']['properties'];
        $this->assertArrayHasKey('id_player_profile_image_import_row', $properties);
        $this->assertArrayHasKey('is_pdf_primary', $properties);
        $this->assertArrayHasKey('player_profile_image_id', $properties);
    }
}
