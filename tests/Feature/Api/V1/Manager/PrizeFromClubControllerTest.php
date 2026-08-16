<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Player;
use App\Models\Prize;
use App\Models\PrizeFromClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrizeFromClubControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    private $club;

    private $prize;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);

        $this->club = Club::factory()->create([
            'name' => 'Test Club',
            'city' => 'Test City',
        ]);

        $this->prize = Prize::factory()->create([
            'name' => 'Test Prize',
        ]);
    }

    public function test_manager_can_view_prizes_from_clubs()
    {
        Sanctum::actingAs($this->manager);

        PrizeFromClub::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/prize-from-clubs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_club_prize',
                        'club_id',
                        'prize_id',
                        'media_path',
                        'box_1_text',
                        'box_1_bg_color',
                        'box_1_text_color',
                        'box_2_text',
                        'box_2_bg_color',
                        'box_2_text_color',
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

    public function test_manager_can_view_specific_prize_from_club()
    {
        Sanctum::actingAs($this->manager);

        $prizeFromClub = PrizeFromClub::factory()->create([
            'club_id' => $this->club->id_club,
            'prize_id' => $this->prize->id_prize,
            'box_1_text' => 'Test Box 1',
        ]);

        $response = $this->getJson("/api/v1/manager/prize-from-clubs/{$prizeFromClub->id_club_prize}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize from club details retrieved successfully',
                'data' => [
                    'id_club_prize' => $prizeFromClub->id_club_prize,
                    'club_id' => $this->club->id_club,
                    'prize_id' => $this->prize->id_prize,
                    'box_1_text' => 'Test Box 1',
                ],
            ]);
    }

    public function test_manager_can_create_prize_from_club()
    {
        Sanctum::actingAs($this->manager);

        $prizeFromClubData = [
            'club_id' => $this->club->id_club,
            'prize_id' => $this->prize->id_prize,
            'media_path' => 'club-prize-image.jpg',
            'box_1_text' => 'Club Box 1 Text',
            'box_1_bg_color' => '#FF0000',
            'box_1_text_color' => '#FFFFFF',
            'box_2_text' => 'Club Box 2 Text',
            'box_2_bg_color' => '#00FF00',
            'box_2_text_color' => '#000000',
        ];

        $response = $this->postJson('/api/v1/manager/prize-from-clubs', $prizeFromClubData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Prize from club created successfully',
                'data' => [
                    'club_id' => $this->club->id_club,
                    'prize_id' => $this->prize->id_prize,
                    'media_path' => 'club-prize-image.jpg',
                    'box_1_text' => 'Club Box 1 Text',
                    'box_1_bg_color' => '#FF0000',
                    'box_1_text_color' => '#FFFFFF',
                    'box_2_text' => 'Club Box 2 Text',
                    'box_2_bg_color' => '#00FF00',
                    'box_2_text_color' => '#000000',
                ],
            ]);

        $this->assertDatabaseHas('prizes_from_clubs', [
            'club_id' => $this->club->id_club,
            'prize_id' => $this->prize->id_prize,
            'media_path' => 'club-prize-image.jpg',
        ]);
    }

    public function test_manager_can_update_prize_from_club()
    {
        Sanctum::actingAs($this->manager);

        $prizeFromClub = PrizeFromClub::factory()->create([
            'club_id' => $this->club->id_club,
            'prize_id' => $this->prize->id_prize,
            'box_1_text' => 'Old Box 1',
        ]);

        $updateData = [
            'club_id' => $this->club->id_club,
            'prize_id' => $this->prize->id_prize,
            'box_1_text' => 'Updated Box 1',
            'box_1_bg_color' => '#0000FF',
            'media_path' => 'updated-image.jpg',
        ];

        $response = $this->putJson("/api/v1/manager/prize-from-clubs/{$prizeFromClub->id_club_prize}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize from club updated successfully',
                'data' => [
                    'id_club_prize' => $prizeFromClub->id_club_prize,
                    'box_1_text' => 'Updated Box 1',
                    'box_1_bg_color' => '#0000FF',
                    'media_path' => 'updated-image.jpg',
                ],
            ]);

        $this->assertDatabaseHas('prizes_from_clubs', [
            'id_club_prize' => $prizeFromClub->id_club_prize,
            'box_1_text' => 'Updated Box 1',
            'box_1_bg_color' => '#0000FF',
        ]);
    }

    public function test_manager_can_partially_update_prize_from_club()
    {
        Sanctum::actingAs($this->manager);

        $prizeFromClub = PrizeFromClub::factory()->create([
            'club_id' => $this->club->id_club,
            'prize_id' => $this->prize->id_prize,
            'box_1_text' => 'Original Box 1',
            'box_1_bg_color' => '#FF0000',
        ]);

        // Test partial update with only box_1_text
        $response = $this->patchJson("/api/v1/manager/prize-from-clubs/{$prizeFromClub->id_club_prize}", [
            'box_1_text' => 'Partially Updated Box 1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize from club partially updated successfully',
                'data' => [
                    'box_1_text' => 'Partially Updated Box 1',
                    'box_1_bg_color' => '#FF0000', // Should remain unchanged
                ],
            ]);

        // Test partial update with only box_1_bg_color
        $response = $this->patchJson("/api/v1/manager/prize-from-clubs/{$prizeFromClub->id_club_prize}", [
            'box_1_bg_color' => '#00FF00',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize from club partially updated successfully',
                'data' => [
                    'box_1_text' => 'Partially Updated Box 1', // Should remain from previous update
                    'box_1_bg_color' => '#00FF00',
                ],
            ]);
    }

    public function test_manager_cannot_update_prize_from_club_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $prizeFromClub = PrizeFromClub::factory()->create([
            'club_id' => $this->club->id_club,
            'prize_id' => $this->prize->id_prize,
        ]);

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/prize-from-clubs/{$prizeFromClub->id_club_prize}", [
            'box_1_text' => 'Only Box Text',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_id', 'prize_id']);

        // Test PUT with empty required fields
        $response = $this->putJson("/api/v1/manager/prize-from-clubs/{$prizeFromClub->id_club_prize}", [
            'club_id' => '',
            'prize_id' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_id', 'prize_id']);
    }

    public function test_manager_can_delete_prize_from_club()
    {
        Sanctum::actingAs($this->manager);

        $prizeFromClub = PrizeFromClub::factory()->create();

        $response = $this->deleteJson("/api/v1/manager/prize-from-clubs/{$prizeFromClub->id_club_prize}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize from club deleted successfully',
            ]);

        $this->assertDatabaseMissing('prizes_from_clubs', [
            'id_club_prize' => $prizeFromClub->id_club_prize,
        ]);
    }

    public function test_manager_can_bulk_delete_prizes_from_clubs()
    {
        Sanctum::actingAs($this->manager);

        $prizesFromClubs = PrizeFromClub::factory()->count(3)->create();

        $response = $this->deleteJson('/api/v1/manager/prize-from-clubs/bulk', [
            'prize_from_club_ids' => $prizesFromClubs->pluck('id_club_prize')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prizes from clubs deleted successfully',
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

        // Verify all prizes from clubs are deleted
        foreach ($prizesFromClubs as $prizeFromClub) {
            $this->assertDatabaseMissing('prizes_from_clubs', ['id_club_prize' => $prizeFromClub->id_club_prize]);
        }
    }

    public function test_manager_cannot_bulk_delete_prizes_from_clubs_with_invalid_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/prize-from-clubs/bulk', [
            'prize_from_club_ids' => [99999, 99998],
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

    public function test_manager_cannot_bulk_delete_prizes_from_clubs_with_empty_array()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/prize-from-clubs/bulk', [
            'prize_from_club_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_prizes_from_clubs_without_club_prize_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/prize-from-clubs/bulk', []);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_prizes_from_clubs_with_invalid_club_prize_ids_format()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/prize-from-clubs/bulk', [
            'prize_from_club_ids' => ['invalid', 'format'],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_create_duplicate_club_prize()
    {
        Sanctum::actingAs($this->manager);

        // Create first prize from club
        PrizeFromClub::factory()->create([
            'club_id' => $this->club->id_club,
            'prize_id' => $this->prize->id_prize,
        ]);

        // Try to create duplicate
        $duplicateData = [
            'club_id' => $this->club->id_club,
            'prize_id' => $this->prize->id_prize,
            'media_path' => 'duplicate-image.jpg',
        ];

        $response = $this->postJson('/api/v1/manager/prize-from-clubs', $duplicateData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This club already has this prize',
            ]);
    }

    public function test_manager_cannot_update_to_duplicate_club_prize()
    {
        Sanctum::actingAs($this->manager);

        $club2 = Club::factory()->create();
        $prize2 = Prize::factory()->create();

        // Create two different club prizes
        $prizeFromClub1 = PrizeFromClub::factory()->create([
            'club_id' => $this->club->id_club,
            'prize_id' => $this->prize->id_prize,
        ]);

        $prizeFromClub2 = PrizeFromClub::factory()->create([
            'club_id' => $club2->id_club,
            'prize_id' => $prize2->id_prize,
        ]);

        // Try to update second to match first
        $response = $this->putJson("/api/v1/manager/prize-from-clubs/{$prizeFromClub2->id_club_prize}", [
            'club_id' => $this->club->id_club,
            'prize_id' => $this->prize->id_prize,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This club already has this prize',
            ]);
    }

    public function test_prize_from_club_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/prize-from-clubs', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_id', 'prize_id']);
    }

    public function test_prize_from_club_creation_validation_invalid_colors()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/prize-from-clubs', [
            'club_id' => $this->club->id_club,
            'prize_id' => $this->prize->id_prize,
            'box_1_bg_color' => 'invalid-color',
            'box_1_text_color' => 'invalid-color',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['box_1_bg_color', 'box_1_text_color']);
    }

    public function test_prize_from_club_update_validation()
    {
        Sanctum::actingAs($this->manager);

        $prizeFromClub = PrizeFromClub::factory()->create();

        $response = $this->putJson("/api/v1/manager/prize-from-clubs/{$prizeFromClub->id_club_prize}", [
            'club_id' => 99999, // Non-existent club
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['club_id']);
    }

    public function test_prize_from_club_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/prize-from-clubs');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_prize_from_club_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/prize-from-clubs');

        $response->assertStatus(403);
    }

    public function test_prize_from_club_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/prize-from-clubs/99999');

        $response->assertStatus(404);
    }

    public function test_prize_from_club_pagination()
    {
        Sanctum::actingAs($this->manager);

        PrizeFromClub::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/manager/prize-from-clubs?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_club_prize',
                        'club_id',
                        'prize_id',
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
