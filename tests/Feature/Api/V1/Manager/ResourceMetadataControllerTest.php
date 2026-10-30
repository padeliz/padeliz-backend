<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Division;
use App\Models\EventParticipation;
use App\Models\Location;
use App\Models\PadelEvent;
use App\Models\PlayerLevelAssessment;
use App\Models\Prize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UnixEventFactory;
use Tests\TestCase;

class ResourceMetadataControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_player_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        // Create some test data
        User::factory()->create(['first_name' => 'John', 'middle_name' => null, 'last_name' => 'Doe']);
        User::factory()->create(['first_name' => 'Jane', 'middle_name' => null, 'last_name' => 'Smith']);

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/User');

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
                    'list_fields',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'User',
                ],
            ]);

        $this->assertContains('first_name', $response->json('data.searchable_fields'));
        $this->assertNotContains('name', $response->json('data.searchable_fields'));
        $this->assertArrayHasKey('user_types', $response->json('data.filterable_fields'));
        $this->assertArrayNotHasKey('skill_division', $response->json('data.filterable_fields'));
        $this->assertEquals('name', $response->json('data.display_field'));
        $this->assertNotContains('name', $response->json('data.sortable_fields'));
        $this->assertNotNull($response->json('data.swagger_schema'));
        $this->assertIsArray($response->json('data.request_body'));
    }

    public function test_get_club_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var User $manager */
        $manager = User::factory()->create();
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
                    'list_fields',
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
        /** @var User $manager */
        $manager = User::factory()->create();
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
                    'list_fields',
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
        /** @var User $manager */
        $manager = User::factory()->create();
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

    public function test_missing_openapi_docs_returns_503(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        $swaggerFile = storage_path('api-docs/api-docs-v1.json');
        $backup = $swaggerFile . '.bak-test';
        $this->assertFileExists($swaggerFile);
        rename($swaggerFile, $backup);

        try {
            $this->artisan('padeliz:cache:invalidate-metadata', ['--schema' => 'Club']);

            $response = $this->actingAs($manager)
                ->getJson('/api/v1/manager/metadata/Club');

            $response->assertStatus(503)
                ->assertJson([
                    'success' => false,
                ]);
            $this->assertStringContainsString('OpenAPI', (string) $response->json('message'));
        } finally {
            if (is_file($backup)) {
                rename($backup, $swaggerFile);
            }
        }
    }

    public function test_invalid_schema_name_returns_422(): void
    {
        // Create a manager user
        /** @var User $manager */
        $manager = User::factory()->create();
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

    public function test_dotted_schema_name_returns_422(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/Challenge.Session');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_get_challenge_session_metadata_endpoint(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/ChallengeSession');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'schema_name' => 'ChallengeSession',
                    'resource_path' => 'challenge-sessions',
                    'display_field' => 'title',
                ],
            ]);

        $this->assertSame(['title'], $response->json('data.searchable_fields'));
        $this->assertArrayHasKey('type', $response->json('data.filterable_fields'));
        $this->assertArrayHasKey('status', $response->json('data.filterable_fields'));
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
            $response->json('data.sortable_fields')
        );
        $this->assertSame(
            ['title', 'type', 'status', 'started_at', 'completed_at'],
            $response->json('data.list_fields')
        );
        $this->assertIsArray($response->json('data.swagger_schema'));
        $this->assertIsArray($response->json('data.swagger_schema.properties'));
        foreach (['id_challenge_session', 'title', 'type', 'status'] as $property) {
            $this->assertArrayHasKey($property, $response->json('data.swagger_schema.properties'));
        }
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/manager/metadata/User');

        $response->assertStatus(401);
    }

    public function test_non_manager_user_returns_403(): void
    {
        // Create a regular player (not manager)
        /** @var User $player */
        $player = User::factory()->create();
        $player->addUserType('player');

        $response = $this->actingAs($player)
            ->getJson('/api/v1/manager/metadata/User');

        $response->assertStatus(403);
    }

    public function test_metadata_delete_routes_removed(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        $this->actingAs($manager)
            ->deleteJson('/api/v1/manager/metadata/User/cache')
            ->assertStatus(404);

        $this->actingAs($manager)
            ->deleteJson('/api/v1/manager/metadata/cache')
            ->assertStatus(405);
    }

    /**
     * Test metadata endpoint for all models
     * @dataProvider modelDataProvider
     */
    public function test_get_metadata_for_all_models(string $modelName, string $expectedSchemaName): void
    {
        // Create a manager user
        /** @var User $manager */
        $manager = User::factory()->create();
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
                    'list_fields',
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
        $this->assertIsArray($response->json('data.list_fields'));
    }

    public static function modelDataProvider(): array
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

    public function test_division_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var User $manager */
        $manager = User::factory()->create();
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
        $this->assertArrayHasKey('its_first_event_id', $response->json('data.filterable_fields'));
        $this->assertIsArray($response->json('data.request_body'));
    }

    public function test_edition_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        // Create test data
        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2024-06-01') + ['name' => 'Summer 2024']);
        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2024-12-01') + ['name' => 'Winter 2024']);

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/Event');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'Event',
                ],
            ]);

        $this->assertContains('name', $response->json('data.searchable_fields'));
        $this->assertIsArray($response->json('data.request_body'));
        $this->assertArrayNotHasKey('registrations', $response->json('data.swagger_schema.properties') ?? []);
    }

    public function test_edition_participation_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->addUserType('manager');

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

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/EventParticipation');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'EventParticipation',
                ],
            ]);

        $filterableFields = $response->json('data.filterable_fields');
        $this->assertArrayHasKey('player_user_id', $filterableFields);
        $this->assertArrayHasKey('event_id', $filterableFields);
        $this->assertArrayHasKey('event_division_id', $filterableFields);

        // Test foreign key relationships
        if (isset($filterableFields['player_user_id']['type']) && $filterableFields['player_user_id']['type'] === 'foreign_key') {
            $this->assertEquals('User', $filterableFields['player_user_id']['related_schema_name']);
            $this->assertEquals('first_name', $filterableFields['player_user_id']['related_schema_field']);
        }

        $this->assertArrayNotHasKey('is_minor', $filterableFields);
        $this->assertArrayNotHasKey('is_student', $filterableFields);
        $this->assertArrayNotHasKey('discount', $filterableFields);
        $this->assertArrayNotHasKey('payment', $filterableFields);

        $swagger = $response->json('data.swagger_schema');
        $this->assertArrayNotHasKey('is_minor', $swagger['properties'] ?? []);

        $this->assertIsArray($response->json('data.request_body'));
    }

    public function test_edition_registration_metadata_endpoint(): void
    {
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/EventRegistration');

        $response->assertStatus(200)
            ->assertJsonPath('data.schema_name', 'EventRegistration');

        $filterableFields = $response->json('data.filterable_fields');
        $this->assertArrayHasKey('player_user_id', $filterableFields);
        $this->assertArrayHasKey('event_id', $filterableFields);
        $this->assertArrayHasKey('event_division_id', $filterableFields);
        $this->assertArrayHasKey('is_minor', $filterableFields);
        $this->assertArrayHasKey('payment', $filterableFields);

        $swagger = $response->json('data.swagger_schema');
        $this->assertArrayHasKey('is_minor', $swagger['properties']);
        $this->assertTrue($swagger['properties']['event_division_id']['nullable'] ?? false);
        $this->assertNotContains('event_division_id', $swagger['required'] ?? []);
        $this->assertContains('event_id', $swagger['required'] ?? []);
        $this->assertContains('payment', $swagger['required'] ?? []);
    }

    public function test_edition_registration_checkout_metadata_endpoint(): void
    {
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/EventRegistrationCheckout');

        $response->assertStatus(200)
            ->assertJsonPath('data.schema_name', 'EventRegistrationCheckout');

        $filterableFields = $response->json('data.filterable_fields');
        $this->assertArrayHasKey('player_user_id', $filterableFields);
        $this->assertArrayHasKey('event_id', $filterableFields);
        $this->assertArrayHasKey('status', $filterableFields);
        $this->assertArrayHasKey('total_amount', $filterableFields);
        $this->assertArrayHasKey('payment_method', $filterableFields);

        $swagger = $response->json('data.swagger_schema');
        $this->assertIsArray($swagger);
        $this->assertArrayHasKey('properties', $swagger);
        $this->assertArrayHasKey('id_checkout', $swagger['properties']);
        $this->assertArrayHasKey('status', $swagger['properties']);
        $this->assertArrayHasKey('total_amount', $swagger['properties']);
        $this->assertContains('event_id', $swagger['required'] ?? []);
        $this->assertContains('player_user_id', $swagger['required'] ?? []);
        $this->assertContains('status', $swagger['required'] ?? []);
        $this->assertContains('total_amount', $swagger['required'] ?? []);
    }

    public function test_player_metadata_links_emails_phones_and_identities(): void
    {
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)->getJson('/api/v1/manager/metadata/User');

        $response->assertStatus(200);
        $properties = $response->json('data.swagger_schema.properties');

        foreach (['emails' => 'UserEmail', 'phones' => 'UserPhone', 'identities' => 'UserIdentity'] as $field => $schema) {
            $this->assertSame('array', $properties[$field]['type'] ?? null, $field);
            $this->assertSame('#/components/schemas/' . $schema, $properties[$field]['items']['$ref'] ?? null, $field);
        }
    }

    /**
     * @dataProvider linkedPlayerChildSchemas
     */
    public function test_player_child_metadata_is_filterable_by_player(string $schema, string $resourcePath, string $displayField): void
    {
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)->getJson('/api/v1/manager/metadata/' . $schema);

        $response->assertStatus(200)
            ->assertJsonPath('data.schema_name', $schema)
            ->assertJsonPath('data.resource_path', $resourcePath)
            ->assertJsonPath('data.display_field', $displayField)
            ->assertJsonPath('data.filterable_fields.user_id.type', 'foreign_key')
            ->assertJsonPath('data.filterable_fields.user_id.related_schema_name', 'User');
        $this->assertNotNull($response->json('data.swagger_schema'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function linkedPlayerChildSchemas(): array
    {
        return [
            'phones' => ['UserPhone', 'player-phones', 'phone'],
            'emails' => ['UserEmail', 'player-emails', 'email'],
            'identities' => ['UserIdentity', 'player-identities', 'display_name'],
        ];
    }

    public function test_player_level_assessment_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        PlayerLevelAssessment::factory()->readyForReview()->create();

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/PlayerLevelAssessment');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'PlayerLevelAssessment',
                ],
            ]);

        $this->assertContains('review_message', $response->json('data.searchable_fields'));
        $this->assertArrayHasKey('review_status', $response->json('data.filterable_fields'));
        $this->assertContains('discarded', $response->json('data.swagger_schema.properties.review_status.enum'));
        $this->assertIsArray($response->json('data.request_body'));
    }

    public function test_prize_metadata_endpoint(): void
    {
        // Create a manager user
        /** @var User $manager */
        $manager = User::factory()->create();
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
        /** @var User $manager */
        $manager = User::factory()->create();
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
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/User');

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
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        // Test models with display_field set to first searchable field
        $modelsWithDisplayField = [
            'Club' => 'name',
            'User' => 'name',
            'Location' => 'name',
            'Division' => 'name',
            'Event' => 'name',
            'EventDivision' => 'division_title',
            'Prize' => 'name',
            'EventLottery' => 'name',
            'Sponsor' => 'name',
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
            'EventLotteryLucky',
            'EventLotteryPrize',
            'EventParticipation',
            'EventRegistration',
            'SponsorPrize',
            'PlayerLevelAssessment',
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

    public function test_get_player_profile_image_import_metadata_endpoint(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/PlayerProfileImageImport');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'PlayerProfileImageImport',
                    'display_field' => 'original_filename',
                ],
            ]);

        $this->assertNotNull($response->json('data.swagger_schema'));
        $properties = $response->json('data.swagger_schema.properties');
        $this->assertArrayHasKey('importedByManager', $properties);
        $this->assertArrayHasKey('rows', $properties);
    }

    public function test_get_player_profile_image_import_row_metadata_endpoint(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->addUserType('manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/v1/manager/metadata/PlayerProfileImageImportRow');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'schema_name' => 'PlayerProfileImageImportRow',
                ],
            ]);

        $this->assertNotNull($response->json('data.swagger_schema'));
        $properties = $response->json('data.swagger_schema.properties');
        $this->assertArrayHasKey('is_pdf_primary', $properties);
        $this->assertArrayHasKey('player_profile_image_id', $properties);
    }
}
