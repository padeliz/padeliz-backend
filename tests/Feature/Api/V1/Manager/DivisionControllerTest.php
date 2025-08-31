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
            'name' => 'Test Manager',
            'phone' => '1234567890',
            'google_id' => 'google_manager_123',
            'google_name' => 'Test Manager',
            'google_email' => 'manager@example.com',
            'google_avatar' => 'https://example.com/manager-avatar.jpg',
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
                        'is_students_only',
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
            'is_students_only' => true,
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
                    'is_students_only' => true,
                ],
            ]);

        $this->assertDatabaseHas('divisions', [
            'name' => 'New Division',
            'color' => '#00FF00',
            'is_students_only' => true,
        ]);
    }

    public function test_manager_can_update_division()
    {
        Sanctum::actingAs($this->manager);

        $division = Division::factory()->create([
            'name' => 'Old Name',
            'color' => '#FF0000',
            'is_students_only' => false,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'color' => '#0000FF',
            'is_students_only' => true,
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
                    'is_students_only' => true,
                ],
            ]);

        $this->assertDatabaseHas('divisions', [
            'id_division' => $division->id_division,
            'name' => 'Updated Name',
            'color' => '#0000FF',
            'is_students_only' => true,
        ]);
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
                'message' => 'Cannot delete division because it has related edition divisions or participations',
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
        EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $player->id_player,
            'division_id' => $division->id_division,
        ]);

        $response = $this->deleteJson("/api/v1/manager/divisions/{$division->id_division}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete division because it has related edition divisions or participations',
            ]);

        $this->assertDatabaseHas('divisions', [
            'id_division' => $division->id_division,
        ]);
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
                        'is_students_only',
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
