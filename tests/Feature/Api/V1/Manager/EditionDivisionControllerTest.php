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

class EditionDivisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    private $edition;

    private $division;

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

        $this->edition = Edition::factory()->create([
            'name' => 'Test Edition',
        ]);

        $this->division = Division::factory()->create([
            'name' => 'Test Division',
        ]);
    }

    public function test_manager_can_view_edition_divisions()
    {
        Sanctum::actingAs($this->manager);

        EditionDivision::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/edition-divisions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_edition_division',
                        'edition_id',
                        'division_id',
                        'players_limit',
                        'court',
                        'playing_start_time',
                        'playing_end_time',
                        'player_opponents_nr',
                        'player_matches_repeat',
                        'include_scores',
                        'has_demonstrative_match',
                        'fixed_teams',
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

    public function test_manager_can_view_specific_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'court' => 'Court A',
        ]);

        $response = $this->getJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Edition division details retrieved successfully',
                'data' => [
                    'id_edition_division' => $editionDivision->id_edition_division,
                    'edition_id' => $this->edition->id_edition,
                    'division_id' => $this->division->id_division,
                    'players_limit' => 16,
                    'court' => 'Court A',
                ],
            ]);
    }

    public function test_manager_can_create_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $editionDivisionData = [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'court' => 'Court A',
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
            'player_opponents_nr' => 3,
            'player_matches_repeat' => 1,
            'include_scores' => true,
            'has_demonstrative_match' => false,
            'fixed_teams' => false,
        ];

        $response = $this->postJson('/api/v1/manager/edition-divisions', $editionDivisionData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Edition division created successfully',
                'data' => [
                    'edition_id' => $this->edition->id_edition,
                    'division_id' => $this->division->id_division,
                    'players_limit' => 16,
                    'court' => 'Court A',
                    'player_opponents_nr' => 3,
                    'player_matches_repeat' => 1,
                    'include_scores' => true,
                    'has_demonstrative_match' => false,
                    'fixed_teams' => false,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_edition_division',
                    'edition_id',
                    'division_id',
                    'players_limit',
                    'court',
                    'playing_start_time',
                    'playing_end_time',
                    'player_opponents_nr',
                    'player_matches_repeat',
                    'include_scores',
                    'has_demonstrative_match',
                    'fixed_teams',
                ],
            ]);

        $this->assertDatabaseHas('edition_divisions', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'court' => 'Court A',
        ]);
    }

    public function test_manager_can_update_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'court' => 'Court A',
        ]);

        $updateData = [
            'players_limit' => 32,
            'court' => 'Court B',
            'playing_start_time' => '10:00:00',
            'playing_end_time' => '18:00:00',
        ];

        $response = $this->putJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Edition division updated successfully',
                'data' => [
                    'id_edition_division' => $editionDivision->id_edition_division,
                    'players_limit' => 32,
                    'court' => 'Court B',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_edition_division',
                    'edition_id',
                    'division_id',
                    'players_limit',
                    'court',
                    'playing_start_time',
                    'playing_end_time',
                    'player_opponents_nr',
                    'player_matches_repeat',
                    'include_scores',
                    'has_demonstrative_match',
                    'fixed_teams',
                ],
            ]);

        $this->assertDatabaseHas('edition_divisions', [
            'id_edition_division' => $editionDivision->id_edition_division,
            'players_limit' => 32,
            'court' => 'Court B',
        ]);
    }

    public function test_manager_can_delete_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision = EditionDivision::factory()->create();

        $response = $this->deleteJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Edition division deleted successfully',
            ]);

        $this->assertDatabaseMissing('edition_divisions', [
            'id_edition_division' => $editionDivision->id_edition_division,
        ]);
    }

    public function test_manager_cannot_delete_edition_division_with_participations()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $player = Player::factory()->create();
        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player->id_player,
            'division_id' => $this->division->id_division,
        ]);

        $response = $this->deleteJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Edition division cannot be deleted because it has linked data. Please remove all associated participations first.',
            ]);

        $this->assertDatabaseHas('edition_divisions', [
            'id_edition_division' => $editionDivision->id_edition_division,
        ]);
    }

    public function test_manager_can_bulk_delete_edition_divisions()
    {
        Sanctum::actingAs($this->manager);

        $editionDivisions = EditionDivision::factory()->count(3)->create();

        $response = $this->deleteJson('/api/v1/manager/edition-divisions/bulk', [
            'edition_division_ids' => $editionDivisions->pluck('id_edition_division')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Edition divisions deleted successfully',
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

        // Verify all edition divisions are deleted
        foreach ($editionDivisions as $editionDivision) {
            $this->assertDatabaseMissing('edition_divisions', ['id_edition_division' => $editionDivision->id_edition_division]);
        }
    }

    public function test_manager_cannot_bulk_delete_edition_divisions_with_linked_data()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision1 = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);
        $editionDivision2 = EditionDivision::factory()->create();
        $editionDivision3 = EditionDivision::factory()->create();

        // Create linked participation for editionDivision1
        $player = Player::factory()->create();
        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player->id_player,
            'division_id' => $this->division->id_division,
        ]);

        $response = $this->deleteJson('/api/v1/manager/edition-divisions/bulk', [
            'edition_division_ids' => [$editionDivision1->id_edition_division, $editionDivision2->id_edition_division, $editionDivision3->id_edition_division],
        ]);

        $response->assertStatus(207)
            ->assertJson([
                'success' => false,
                'message' => 'Some edition divisions failed to delete',
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
        $this->assertDatabaseMissing('edition_divisions', ['id_edition_division' => $editionDivision2->id_edition_division]);
        $this->assertDatabaseMissing('edition_divisions', ['id_edition_division' => $editionDivision3->id_edition_division]);
        // Verify failed deletion still exists
        $this->assertDatabaseHas('edition_divisions', ['id_edition_division' => $editionDivision1->id_edition_division]);
    }

    public function test_manager_cannot_bulk_delete_edition_divisions_with_invalid_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/edition-divisions/bulk', [
            'edition_division_ids' => [99999, 99998],
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'All edition divisions failed to delete',
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

    public function test_manager_cannot_bulk_delete_edition_divisions_with_empty_array()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/edition-divisions/bulk', [
            'edition_division_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_edition_divisions_without_edition_division_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/edition-divisions/bulk', []);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_edition_divisions_with_invalid_edition_division_ids_format()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/edition-divisions/bulk', [
            'edition_division_ids' => ['invalid', 'format'],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_create_duplicate_edition_division()
    {
        Sanctum::actingAs($this->manager);

        // Create first edition division
        EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        // Try to create duplicate
        $duplicateData = [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'court' => 'Court A',
            'playing_start_time' => '09:00:00',
            'playing_end_time' => '17:00:00',
        ];

        $response = $this->postJson('/api/v1/manager/edition-divisions', $duplicateData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This division is already assigned to this edition',
            ]);
    }

    public function test_manager_cannot_update_to_duplicate_edition_division()
    {
        Sanctum::actingAs($this->manager);

        $edition2 = Edition::factory()->create();
        $division2 = Division::factory()->create();

        // Create two different edition divisions
        $editionDivision1 = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $editionDivision2 = EditionDivision::factory()->create([
            'edition_id' => $edition2->id_edition,
            'division_id' => $division2->id_division,
        ]);

        // Try to update second to match first
        $response = $this->putJson("/api/v1/manager/edition-divisions/{$editionDivision2->id_edition_division}", [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This division is already assigned to this edition',
            ]);
    }

    public function test_edition_division_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/edition-divisions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id', 'division_id', 'players_limit', 'court', 'playing_start_time', 'playing_end_time']);
    }

    public function test_edition_division_creation_validation_invalid_times()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/edition-divisions', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'court' => 'Court A',
            'playing_start_time' => '17:00:00',
            'playing_end_time' => '09:00:00', // End time before start time
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['playing_end_time']);
    }

    public function test_edition_division_update_validation()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision = EditionDivision::factory()->create();

        $response = $this->putJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}", [
            'edition_id' => 99999, // Non-existent edition
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id']);
    }

    public function test_manager_can_view_edition_division_participants_with_pagination()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        // Create multiple participants
        $players = Player::factory()->count(25)->create();
        foreach ($players as $player) {
            EditionParticipation::factory()->create([
                'edition_id' => $this->edition->id_edition,
                'player_id' => $player->id_player,
                'division_id' => $this->division->id_division,
            ]);
        }

        $response = $this->getJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/participants?page=1&per_page=10");

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
                        'ban_reason',
                        'inserted_at',
                        'updated_at',
                        'player' => [
                            'id_player',
                            'name',
                            'phone',
                        ],
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
        $this->assertEquals(10, $response->json('meta.per_page'));
    }

    public function test_manager_can_view_edition_division_participants_with_search()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $player1 = Player::factory()->create();
        $player2 = Player::factory()->create();

        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player1->id_player,
            'division_id' => $this->division->id_division,
            'absence_reason' => 'SICK',
        ]);

        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player2->id_player,
            'division_id' => $this->division->id_division,
            'absence_reason' => 'EXAMS',
        ]);

        $response = $this->getJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/participants?q=SICK");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('SICK', $response->json('data.0.absence_reason'));
    }

    public function test_manager_can_view_edition_division_participants_with_filters()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $player1 = Player::factory()->create();
        $player2 = Player::factory()->create();

        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player1->id_player,
            'division_id' => $this->division->id_division,
            'is_student' => true,
            'payment' => 25.00,
        ]);

        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player2->id_player,
            'division_id' => $this->division->id_division,
            'is_student' => false,
            'payment' => 50.00,
        ]);

        $response = $this->getJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/participants?filters[is_student]=1");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue((bool) $response->json('data.0.is_student'));
    }

    public function test_manager_can_view_edition_division_participants_with_sorting()
    {
        Sanctum::actingAs($this->manager);

        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        $player1 = Player::factory()->create();
        $player2 = Player::factory()->create();

        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player1->id_player,
            'division_id' => $this->division->id_division,
            'payment' => 25.00,
        ]);

        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player2->id_player,
            'division_id' => $this->division->id_division,
            'payment' => 50.00,
        ]);

        $response = $this->getJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/participants?sort_by[payment]=desc");

        $response->assertStatus(200);
        $this->assertEquals(50.00, $response->json('data.0.payment'));
        $this->assertEquals(25.00, $response->json('data.1.payment'));
    }

    public function test_edition_division_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/edition-divisions');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_edition_division_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/edition-divisions');

        $response->assertStatus(403);
    }

    public function test_edition_division_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/edition-divisions/99999');

        $response->assertStatus(404);
    }

    public function test_edition_division_pagination()
    {
        Sanctum::actingAs($this->manager);

        EditionDivision::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/manager/edition-divisions?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_edition_division',
                        'edition_id',
                        'division_id',
                        'players_limit',
                        'court',
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
