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

class EditionParticipationControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    private $edition;

    private $division;

    private $editionDivision;

    private $player;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);

        $this->edition = Edition::factory()->create([
            'name' => 'Test Edition',
        ]);

        $this->division = Division::factory()->create([
            'name' => 'Test Division',
        ]);

        $this->editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'division_title' => $this->division->name,
        ]);

        $this->player = Player::factory()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Player',
        ]);
    }

    public function test_manager_can_view_participations_for_edition()
    {
        Sanctum::actingAs($this->manager);

        EditionParticipation::factory()->count(5)->create([
            'edition_id' => $this->edition->id_edition,
        ]);

        $response = $this->getJson("/api/v1/manager/edition-participations");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_participation',
                        'edition_id',
                        'player_id',
                        'edition_division_id',
                        'is_minor',
                        'is_student',
                        'discount',
                        'payment',
                        'absence_reason',
                        'ban_reason',
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

    public function test_manager_can_view_specific_participation()
    {
        Sanctum::actingAs($this->manager);

        $editionParticipation = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_minor' => true,
            'is_student' => false,
        ]);

        $response = $this->getJson("/api/v1/manager/edition-participations/{$editionParticipation->id_participation}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participation details retrieved successfully',
                'data' => [
                    'id_participation' => $editionParticipation->id_participation,
                    'edition_id' => $this->edition->id_edition,
                    'player_id' => $this->player->id_player,
                    'edition_division_id' => $this->editionDivision->id_edition_division,
                    'is_minor' => true,
                    'is_student' => false,
                ],
            ]);
    }


    public function test_manager_can_create_participation()
    {
        Sanctum::actingAs($this->manager);

        $editionParticipationData = [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_minor' => true,
            'is_student' => false,
            'discount' => 10.00,
            'payment' => 50.00,
            'absence_reason' => null,
            'ban_reason' => null,
        ];

        $response = $this->postJson("/api/v1/manager/edition-participations", $editionParticipationData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Participation created successfully',
                'data' => [
                    'edition_id' => $this->edition->id_edition,
                    'player_id' => $this->player->id_player,
                    'edition_division_id' => $this->editionDivision->id_edition_division,
                    'is_minor' => true,
                    'is_student' => false,
                    'discount' => 10.00,
                    'payment' => 50.00,
                ],
            ]);

        $this->assertDatabaseHas('edition_participations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]);
    }

    public function test_manager_can_update_participation()
    {
        Sanctum::actingAs($this->manager);

        $editionParticipation = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_minor' => false,
            'is_student' => false,
        ]);

        $updateData = [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_minor' => true,
            'is_student' => true,
            'discount' => 15.00,
            'payment' => 45.00,
        ];

        $response = $this->putJson("/api/v1/manager/edition-participations/{$editionParticipation->id_participation}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participation updated successfully',
                'data' => [
                    'id_participation' => $editionParticipation->id_participation,
                    'is_minor' => true,
                    'is_student' => true,
                    'discount' => 15.00,
                    'payment' => 45.00,
                ],
            ]);

        $this->assertDatabaseHas('edition_participations', [
            'id_participation' => $editionParticipation->id_participation,
            'is_minor' => true,
            'is_student' => true,
        ]);
    }

    public function test_manager_can_partially_update_participation()
    {
        Sanctum::actingAs($this->manager);

        $editionParticipation = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_minor' => false,
            'is_student' => false,
        ]);

        // Test partial update with only is_minor
        $response = $this->patchJson("/api/v1/manager/edition-participations/{$editionParticipation->id_participation}", [
            'is_minor' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participation partially updated successfully',
                'data' => [
                    'is_minor' => true,
                    'is_student' => false, // Should remain unchanged
                ],
            ]);

        // Test partial update with only is_student
        $response = $this->patchJson("/api/v1/manager/edition-participations/{$editionParticipation->id_participation}", [
            'is_student' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participation partially updated successfully',
                'data' => [
                    'is_minor' => true, // Should remain from previous update
                    'is_student' => true,
                ],
            ]);
    }

    public function test_manager_cannot_update_participation_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $editionParticipation = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]);

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/edition-participations/{$editionParticipation->id_participation}", [
            'is_minor' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id', 'player_id', 'edition_division_id', 'is_student']);

        // Test PUT with empty required fields
        $response = $this->putJson("/api/v1/manager/edition-participations/{$editionParticipation->id_participation}", [
            'edition_id' => '',
            'player_id' => '',
            'edition_division_id' => '',
            'is_minor' => '',
            'is_student' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id', 'player_id', 'edition_division_id', 'is_minor', 'is_student']);
    }

    public function test_manager_can_delete_participation()
    {
        Sanctum::actingAs($this->manager);

        $editionParticipation = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]);

        $response = $this->deleteJson("/api/v1/manager/edition-participations/{$editionParticipation->id_participation}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participation deleted successfully',
            ]);

        $this->assertDatabaseMissing('edition_participations', [
            'id_participation' => $editionParticipation->id_participation,
        ]);
    }

    public function test_manager_can_bulk_delete_participations()
    {
        Sanctum::actingAs($this->manager);

        $editionParticipations = EditionParticipation::factory()->count(3)->create([
            'edition_id' => $this->edition->id_edition,
        ]);

        $response = $this->deleteJson("/api/v1/manager/edition-participations/bulk", [
            'participation_ids' => $editionParticipations->pluck('id_participation')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participations deleted successfully',
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

        // Verify all participations are deleted
        foreach ($editionParticipations as $editionParticipation) {
            $this->assertDatabaseMissing('edition_participations', ['id_participation' => $editionParticipation->id_participation]);
        }
    }

    public function test_manager_cannot_bulk_delete_participations_with_invalid_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson("/api/v1/manager/edition-participations/bulk", [
            'participation_ids' => [99999, 99998],
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'All participations failed to delete',
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

    public function test_manager_cannot_bulk_delete_participations_with_empty_array()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson("/api/v1/manager/edition-participations/bulk", [
            'participation_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_participations_without_participation_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson("/api/v1/manager/edition-participations/bulk", []);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_participations_with_invalid_participation_ids_format()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson("/api/v1/manager/edition-participations/bulk", [
            'participation_ids' => ['invalid', 'format'],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_create_duplicate_participation()
    {
        Sanctum::actingAs($this->manager);

        // Create first participation
        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]);

        // Try to create duplicate
        $duplicateData = [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_minor' => false,
            'is_student' => false,
        ];

        $response = $this->postJson("/api/v1/manager/edition-participations", $duplicateData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This player is already participating in this edition',
            ]);
    }

    public function test_manager_cannot_update_to_duplicate_participation()
    {
        Sanctum::actingAs($this->manager);

        $player2 = Player::factory()->create();

        // Create two different participations
        $editionParticipation1 = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]);

        $editionParticipation2 = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player2->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]);

        // Try to update second to match first
        $response = $this->putJson("/api/v1/manager/edition-participations/{$editionParticipation2->id_participation}", [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_minor' => false,
            'is_student' => false,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This player is already participating in this edition',
            ]);
    }

    public function test_participation_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/edition-participations", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id', 'player_id', 'edition_division_id']);
    }

    public function test_participation_creation_validation_invalid_player()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/edition-participations", [
            'edition_id' => $this->edition->id_edition,
            'player_id' => 99999, // Non-existent player
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_id']);
    }

    public function test_participation_creation_validation_invalid_division()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/edition-participations", [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => 99999, // Non-existent division
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_division_id']);
    }

    public function test_participation_update_validation()
    {
        Sanctum::actingAs($this->manager);

        $editionParticipation = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
        ]);

        $response = $this->putJson("/api/v1/manager/edition-participations/{$editionParticipation->id_participation}", [
            'edition_id' => $this->edition->id_edition,
            'player_id' => 99999, // Non-existent player
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_id']);
    }

    public function test_participation_requires_manager_authentication()
    {
        $response = $this->getJson("/api/v1/manager/edition-participations");

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_participation_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson("/api/v1/manager/edition-participations");

        $response->assertStatus(403);
    }

    public function test_participation_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/manager/edition-participations/99999");

        $response->assertStatus(404);
    }

    public function test_participation_pagination()
    {
        Sanctum::actingAs($this->manager);

        EditionParticipation::factory()->count(25)->create([
            'edition_id' => $this->edition->id_edition,
        ]);

        $response = $this->getJson("/api/v1/manager/edition-participations?page=1&per_page=10");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_participation',
                        'edition_id',
                        'player_id',
                        'edition_division_id',
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

    public function test_participation_with_optional_fields()
    {
        Sanctum::actingAs($this->manager);

        $editionParticipationData = [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            // Not providing optional fields
        ];

        $response = $this->postJson("/api/v1/manager/edition-participations", $editionParticipationData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Participation created successfully',
            ]);

        $this->assertDatabaseHas('edition_participations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_minor' => 0,
            'is_student' => 0,
            'discount' => 0,
            'payment' => 0,
        ]);
    }
}
