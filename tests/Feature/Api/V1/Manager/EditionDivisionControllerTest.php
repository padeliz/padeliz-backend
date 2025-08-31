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
                'message' => 'Cannot delete edition division because it has related participations',
            ]);

        $this->assertDatabaseHas('edition_divisions', [
            'id_edition_division' => $editionDivision->id_edition_division,
        ]);
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

    public function test_manager_can_view_edition_division_participants()
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

        $response = $this->getJson("/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/participants");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participants retrieved successfully',
            ]);

        $this->assertCount(1, $response->json('data'));
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
