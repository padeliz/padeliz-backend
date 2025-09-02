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

    public function test_get_players_metadata(): void
    {
        // Create some test data
        Player::factory()->create(['name' => 'John Doe', 'skill_division' => 2.5]);
        Player::factory()->create(['name' => 'Jane Smith', 'skill_division' => 3.0]);
        Player::factory()->create(['name' => 'Bob Johnson', 'skill_division' => 4.5]);

        $metadata = $this->metadataService->getMetadata('players');

        $this->assertArrayHasKey('resource', $metadata);
        $this->assertEquals('players', $metadata['resource']);

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

    public function test_get_clubs_metadata(): void
    {
        // Create some test data
        Club::factory()->create(['name' => 'Club A', 'city' => 'Bucharest']);
        Club::factory()->create(['name' => 'Club B', 'city' => 'Cluj']);
        Club::factory()->create(['name' => 'Club C', 'city' => 'Bucharest']);

        $metadata = $this->metadataService->getMetadata('clubs');

        $this->assertArrayHasKey('resource', $metadata);
        $this->assertEquals('clubs', $metadata['resource']);

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

    public function test_get_locations_metadata_with_foreign_key(): void
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

        $metadata = $this->metadataService->getMetadata('locations');

        $this->assertArrayHasKey('resource', $metadata);
        $this->assertEquals('locations', $metadata['resource']);

        $this->assertArrayHasKey('filterable_fields', $metadata);
        $this->assertArrayHasKey('club_id', $metadata['filterable_fields']);
        $this->assertEquals('foreign_key', $metadata['filterable_fields']['club_id']['type']);
        $this->assertEquals('clubs', $metadata['filterable_fields']['club_id']['related_model']);
        $this->assertEquals('name', $metadata['filterable_fields']['club_id']['searchable_field']);
        $this->assertContains('Test Club', $metadata['filterable_fields']['club_id']['values']);
    }

    public function test_invalid_resource_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Model class App\Models\InvalidModel not found');

        $this->metadataService->getMetadata('invalid_model');
    }

    public function test_metadata_is_cached(): void
    {
        // First call should cache the result
        $metadata1 = $this->metadataService->getMetadata('players');

        // Second call should return cached result
        $metadata2 = $this->metadataService->getMetadata('players');

        $this->assertEquals($metadata1, $metadata2);
    }

    public function test_clear_cache_works(): void
    {
        // Get metadata to populate cache
        $this->metadataService->getMetadata('players');

        // Clear cache
        $this->metadataService->clearCache('players');

        // Should still work after clearing cache
        $metadata = $this->metadataService->getMetadata('players');
        $this->assertArrayHasKey('resource', $metadata);
        $this->assertEquals('players', $metadata['resource']);
    }

    public function test_boolean_field_values(): void
    {
        Player::factory()->create(['being_student' => true]);
        Player::factory()->create(['being_student' => false]);

        $metadata = $this->metadataService->getMetadata('players');

        $this->assertArrayHasKey('being_student', $metadata['filterable_fields']);
        $this->assertEquals('boolean', $metadata['filterable_fields']['being_student']['type']);
        $this->assertEquals([true, false], $metadata['filterable_fields']['being_student']['values']);
    }
}
