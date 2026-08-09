<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DivisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);
    }

    public function test_manager_can_view_divisions()
    {
        Sanctum::actingAs($this->manager);

        Division::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/divisions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_division',
                        'name',
                        'color',
                    ],
                ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'path',
                'per_page',
                'to',
                'total',
            ],
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_manager_can_view_specific_division()
    {
        Sanctum::actingAs($this->manager);

        $division = Division::factory()->create([
            'name' => 'Test Division',
            'color' => '#FF0000',
        ]);

        $response = $this->getJson("/api/v1/manager/divisions/{$division->id_division}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Division details retrieved successfully',
                'data' => [
                'id_division' => $division->id_division,
                'name' => 'Test Division',
                'color' => '#FF0000',
                ],
            ]);
    }

    public function test_manager_can_create_division()
    {
        Sanctum::actingAs($this->manager);

        // Create an edition first
        $edition = \App\Models\Edition::factory()->create();

        $divisionData = [
            'name' => 'New Division',
            'color' => '#00FF00',
            'its_first_edition_id' => $edition->id_edition,
        ];

        $response = $this->postJson('/api/v1/manager/divisions', $divisionData);

        $response->assertStatus(201)
            ->assertJson([
            'success' => true,
                'message' => 'Division created successfully',
                'data' => [
                    'name' => 'New Division',
                    'color' => '#00FF00',
                ],
            ]);

        $this->assertDatabaseHas('divisions', [
            'name' => 'New Division',
            'color' => '#00FF00',
        ]);
    }

    public function test_manager_can_update_division()
    {
        Sanctum::actingAs($this->manager);

        $division = Division::factory()->create([
            'name' => 'Old Name',
            'color' => '#FF0000',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'color' => '#0000FF',
        ];

        $response = $this->putJson("/api/v1/manager/divisions/{$division->id_division}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Division updated successfully',
                'data' => [
                    'id_division' => $division->id_division,
                    'name' => 'Updated Name',
                    'color' => '#0000FF',
                ],
            ]);

        $this->assertDatabaseHas('divisions', [
            'id_division' => $division->id_division,
            'name' => 'Updated Name',
            'color' => '#0000FF',
        ]);
    }

    public function test_manager_can_partially_update_division()
    {
        Sanctum::actingAs($this->manager);

        $division = Division::factory()->create([
            'name' => 'Original Name',
            'color' => '#FF0000',
        ]);

        // Test partial update with only name
        $response = $this->patchJson("/api/v1/manager/divisions/{$division->id_division}", [
            'name' => 'Partially Updated Name',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Division partially updated successfully',
                'data' => [
                    'name' => 'Partially Updated Name',
                    'color' => '#FF0000', // Should remain unchanged // Should remain unchanged
                ],
            ]);

        // Test partial update with only color
        $response = $this->patchJson("/api/v1/manager/divisions/{$division->id_division}", [
            'color' => '#00FF00',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Division partially updated successfully',
                'data' => [
                    'name' => 'Partially Updated Name', // Should remain from previous update
                    'color' => '#00FF00',
                ],
            ]);
    }

    public function test_manager_cannot_update_division_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $division = Division::factory()->create();

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/divisions/{$division->id_division}", [
            'name' => 'Only Name',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);

        // Test PUT with empty required fields
        $response = $this->putJson("/api/v1/manager/divisions/{$division->id_division}", [
            'name' => '',
            'color' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'color']);
    }

    public function test_manager_can_delete_division()
    {
        Sanctum::actingAs($this->manager);

        $division = Division::factory()->create();

        $response = $this->deleteJson("/api/v1/manager/divisions/{$division->id_division}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Division deleted successfully',
            ]);

        $this->assertDatabaseMissing('divisions', [
            'id_division' => $division->id_division,
        ]);
    }

    public function test_manager_cannot_delete_division_with_edition_divisions()
    {
        Sanctum::actingAs($this->manager);

        $division = Division::factory()->create();
        $edition = Edition::factory()->create();
        EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $division->id_division,
        ]);

        $response = $this->deleteJson("/api/v1/manager/divisions/{$division->id_division}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Division cannot be deleted because it has linked data. Please remove all associated edition_divisions_count, participations_count first.',
            ]);

        $this->assertDatabaseHas('divisions', [
            'id_division' => $division->id_division,
        ]);
    }

    public function test_manager_cannot_delete_division_with_participations()
    {
        Sanctum::actingAs($this->manager);

        $division = Division::factory()->create();
        $player = Player::factory()->create();
        $edition = Edition::factory()->create();
        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $division->id_division,
        ]);
        EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $player->id_player,
            'edition_division_id' => $editionDivision->id_edition_division,
        ]);

        $response = $this->deleteJson("/api/v1/manager/divisions/{$division->id_division}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Division cannot be deleted because it has linked data. Please remove all associated edition_divisions_count, participations_count first.',
            ]);

        $this->assertDatabaseHas('divisions', [
            'id_division' => $division->id_division,
        ]);
    }

    public function test_manager_can_bulk_delete_divisions()
    {
        Sanctum::actingAs($this->manager);

        $divisions = Division::factory()->count(3)->create();

        $response = $this->deleteJson('/api/v1/manager/divisions/bulk', [
            'division_ids' => $divisions->pluck('id_division')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Divisions deleted successfully',
                'data' => [
                    'total_successful' => 3,
                    'total_failed' => 0,
                    'total_requested' => 3,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'successful' => [
                        '*' => [
                            'id',
                            'name',
                        ],
                    ],
                    'failed',
                    'total_requested',
                    'total_successful',
                    'total_failed',
                ],
            ]);

        // Verify all divisions are deleted
        foreach ($divisions as $division) {
            $this->assertDatabaseMissing('divisions', ['id_division' => $division->id_division]);
        }
    }

    public function test_manager_cannot_bulk_delete_divisions_with_linked_data()
    {
        Sanctum::actingAs($this->manager);

        $division1 = Division::factory()->create();
        $division2 = Division::factory()->create();
        $division3 = Division::factory()->create();

        // Create linked edition division for division1
        $edition = Edition::factory()->create();
        EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $division1->id_division,
        ]);

        $response = $this->deleteJson('/api/v1/manager/divisions/bulk', [
            'division_ids' => [$division1->id_division, $division2->id_division, $division3->id_division],
        ]);

        $response->assertStatus(207)
            ->assertJson([
                'success' => false,
                'message' => 'Some resources failed to delete',
                'errors' => [
                    'total_successful' => 2,
                    'total_failed' => 1,
                    'total_requested' => 3,
                ],
            ])
            ->assertJsonStructure([
                'errors' => [
                    'successful' => [
                        '*' => [
                            'id',
                            'name',
                        ],
                    ],
                    'failed' => [
                        '*' => [
                            'id',
                            'reason',
                        ],
                    ],
                    'total_requested',
                    'total_successful',
                    'total_failed',
                ],
            ]);

        // Verify successful deletions
        $this->assertDatabaseMissing('divisions', ['id_division' => $division2->id_division]);
        $this->assertDatabaseMissing('divisions', ['id_division' => $division3->id_division]);
        // Verify failed deletion still exists
        $this->assertDatabaseHas('divisions', ['id_division' => $division1->id_division]);
    }

    public function test_manager_cannot_bulk_delete_divisions_with_invalid_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/divisions/bulk', [
            'division_ids' => [99999, 99998],
        ]);

        $response->assertStatus(207)
            ->assertJson([
                'success' => false,
                'message' => 'All resources failed to delete',
                'errors' => [
                    'total_successful' => 0,
                    'total_failed' => 2,
                    'total_requested' => 2,
                ],
            ])
            ->assertJsonStructure([
                'errors' => [
                    'successful',
                    'failed' => [
                        '*' => [
                            'id',
                            'reason',
                        ],
                    ],
                    'total_requested',
                    'total_successful',
                    'total_failed',
                ],
            ]);
    }

    public function test_manager_cannot_bulk_delete_divisions_with_empty_array()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/divisions/bulk', [
            'division_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_divisions_without_division_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/divisions/bulk', []);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_divisions_with_invalid_division_ids_format()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/divisions/bulk', [
            'division_ids' => ['invalid', 'format'],
        ]);

        $response->assertStatus(422);
    }

    public function test_division_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/divisions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'color']);
    }

    public function test_division_creation_validation_invalid_color()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/divisions', [
            'name' => 'Test Division',
            'color' => 'invalid-color',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    public function test_division_update_validation()
    {
        Sanctum::actingAs($this->manager);

        $division = Division::factory()->create();

        $response = $this->putJson("/api/v1/manager/divisions/{$division->id_division}", [
            'color' => 'invalid-color',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    public function test_division_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/divisions');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_division_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/divisions');

        $response->assertStatus(403);
    }

    public function test_division_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/divisions/99999');

        $response->assertStatus(404);
    }

    public function test_division_pagination()
    {
        Sanctum::actingAs($this->manager);

        Division::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/manager/divisions?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_division',
                        'name',
                        'color',
                    ],
                ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'path',
                'per_page',
                'to',
                'total',
            ],
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }
}
