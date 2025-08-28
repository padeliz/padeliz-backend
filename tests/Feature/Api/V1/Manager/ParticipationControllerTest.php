<?php

namespace Tests\Feature\Api\V1\Manager;

use Tests\TestCase;
use App\Models\Player;
use App\Models\EditionParticipation;
use App\Models\Edition;
use App\Models\Division;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ParticipationControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;
    private $edition;
    private $division;
    private $player;

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
            'user_types' => ['player', 'manager']
        ]);

        $this->edition = Edition::factory()->create([
            'name' => 'Test Edition'
        ]);

        $this->division = Division::factory()->create([
            'name' => 'Test Division'
        ]);

        $this->player = Player::factory()->create([
            'name' => 'Test Player'
        ]);
    }

    public function test_manager_can_view_participations_for_edition()
    {
        Sanctum::actingAs($this->manager);

        EditionParticipation::factory()->count(5)->create([
            'edition_id' => $this->edition->id_edition
        ]);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_participation',
                        'edition_id',
                        'player_id',
                        'division_id',
                        'is_minor',
                        'is_student',
                        'discount',
                        'payment',
                        'absence_reason',
                        'ban_reason'
                    ]
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next'
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total'
                ]
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_manager_can_view_specific_participation()
    {
        Sanctum::actingAs($this->manager);

        $participation = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'division_id' => $this->division->id_division,
            'is_minor' => true,
            'is_student' => false
        ]);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations/{$participation->id_participation}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participation details retrieved successfully',
                'data' => [
                    'id_participation' => $participation->id_participation,
                    'edition_id' => $this->edition->id_edition,
                    'player_id' => $this->player->id_player,
                    'division_id' => $this->division->id_division,
                    'is_minor' => true,
                    'is_student' => false
                ]
            ]);
    }

    public function test_manager_cannot_view_participation_from_different_edition()
    {
        Sanctum::actingAs($this->manager);

        $otherEdition = Edition::factory()->create();
        $participation = EditionParticipation::factory()->create([
            'edition_id' => $otherEdition->id_edition,
            'player_id' => $this->player->id_player,
            'division_id' => $this->division->id_division
        ]);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations/{$participation->id_participation}");

        $response->assertStatus(404);
    }

    public function test_manager_can_create_participation()
    {
        Sanctum::actingAs($this->manager);

        $participationData = [
            'player_id' => $this->player->id_player,
            'division_id' => $this->division->id_division,
            'is_minor' => true,
            'is_student' => false,
            'discount' => 10.00,
            'payment' => 50.00,
            'absence_reason' => null,
            'ban_reason' => null
        ];

        $response = $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations", $participationData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Participation created successfully',
                'data' => [
                    'edition_id' => $this->edition->id_edition,
                    'player_id' => $this->player->id_player,
                    'division_id' => $this->division->id_division,
                    'is_minor' => true,
                    'is_student' => false,
                    'discount' => 10.00,
                    'payment' => 50.00
                ]
            ]);

        $this->assertDatabaseHas('edition_participations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'division_id' => $this->division->id_division
        ]);
    }

    public function test_manager_can_update_participation()
    {
        Sanctum::actingAs($this->manager);

        $participation = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'division_id' => $this->division->id_division,
            'is_minor' => false,
            'is_student' => false
        ]);

        $updateData = [
            'is_minor' => true,
            'is_student' => true,
            'discount' => 15.00,
            'payment' => 45.00
        ];

        $response = $this->putJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations/{$participation->id_participation}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participation updated successfully',
                'data' => [
                    'id_participation' => $participation->id_participation,
                    'is_minor' => true,
                    'is_student' => true,
                    'discount' => 15.00,
                    'payment' => 45.00
                ]
            ]);

        $this->assertDatabaseHas('edition_participations', [
            'id_participation' => $participation->id_participation,
            'is_minor' => true,
            'is_student' => true
        ]);
    }

    public function test_manager_can_delete_participation()
    {
        Sanctum::actingAs($this->manager);

        $participation = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'division_id' => $this->division->id_division
        ]);

        $response = $this->deleteJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations/{$participation->id_participation}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participation deleted successfully'
            ]);

        $this->assertDatabaseMissing('edition_participations', [
            'id_participation' => $participation->id_participation
        ]);
    }

    public function test_manager_cannot_create_duplicate_participation()
    {
        Sanctum::actingAs($this->manager);

        // Create first participation
        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'division_id' => $this->division->id_division
        ]);

        // Try to create duplicate
        $duplicateData = [
            'player_id' => $this->player->id_player,
            'division_id' => $this->division->id_division
        ];

        $response = $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations", $duplicateData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This player is already participating in this edition'
            ]);
    }

    public function test_manager_cannot_update_to_duplicate_participation()
    {
        Sanctum::actingAs($this->manager);

        $player2 = Player::factory()->create();

        // Create two different participations
        $participation1 = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'division_id' => $this->division->id_division
        ]);

        $participation2 = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player2->id_player,
            'division_id' => $this->division->id_division
        ]);

        // Try to update second to match first
        $response = $this->putJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations/{$participation2->id_participation}", [
            'player_id' => $this->player->id_player
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This player is already participating in this edition'
            ]);
    }

    public function test_participation_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_id', 'division_id']);
    }

    public function test_participation_creation_validation_invalid_player()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations", [
            'player_id' => 99999, // Non-existent player
            'division_id' => $this->division->id_division
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_id']);
    }

    public function test_participation_creation_validation_invalid_division()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations", [
            'player_id' => $this->player->id_player,
            'division_id' => 99999 // Non-existent division
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['division_id']);
    }

    public function test_participation_update_validation()
    {
        Sanctum::actingAs($this->manager);

        $participation = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition
        ]);

        $response = $this->putJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations/{$participation->id_participation}", [
            'player_id' => 99999 // Non-existent player
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_id']);
    }

    public function test_participation_requires_manager_authentication()
    {
        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations");

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_participation_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations");

        $response->assertStatus(403);
    }

    public function test_participation_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations/99999");

        $response->assertStatus(404);
    }

    public function test_participation_pagination()
    {
        Sanctum::actingAs($this->manager);

        EditionParticipation::factory()->count(25)->create([
            'edition_id' => $this->edition->id_edition
        ]);

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations?page=1&per_page=10");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_participation',
                        'edition_id',
                        'player_id',
                        'division_id'
                    ]
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next'
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total'
                ]
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }

    public function test_participation_with_optional_fields()
    {
        Sanctum::actingAs($this->manager);

        $participationData = [
            'player_id' => $this->player->id_player,
            'division_id' => $this->division->id_division
            // Not providing optional fields
        ];

        $response = $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/participations", $participationData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Participation created successfully'
            ]);

        $this->assertDatabaseHas('edition_participations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'division_id' => $this->division->id_division,
            'is_minor' => 0,
            'is_student' => 0,
            'discount' => 0,
            'payment' => 0
        ]);
    }
}

