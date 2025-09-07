<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Club;
use App\Models\Location;
use App\Models\Player;
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
}
