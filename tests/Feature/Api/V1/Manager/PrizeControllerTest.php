<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\EventLottery;
use App\Models\EventLotteryPrize;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\Prize;
use App\Models\Sponsor;
use App\Models\SponsorPrize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrizeControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);
    }

    public function test_manager_can_view_prizes()
    {
        Sanctum::actingAs($this->manager);

        Prize::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/prizes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_prize',
                        'name',
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

    public function test_manager_can_view_specific_prize()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create([
            'name' => 'Test Prize',
            'box_1_text' => 'Test Box 1',
        ]);

        $response = $this->getJson("/api/v1/manager/prizes/{$prize->id_prize}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize details retrieved successfully',
                'data' => [
                    'id_prize' => $prize->id_prize,
                    'name' => 'Test Prize',
                    'box_1_text' => 'Test Box 1',
                ],
            ]);
    }

    public function test_manager_can_create_prize()
    {
        Sanctum::actingAs($this->manager);

        $prizeData = [
            'name' => 'New Prize',
            'media_path' => 'prize-image.jpg',
            'box_1_text' => 'Box 1 Text',
            'box_1_bg_color' => '#FF0000',
            'box_1_text_color' => '#FFFFFF',
            'box_2_text' => 'Box 2 Text',
            'box_2_bg_color' => '#00FF00',
            'box_2_text_color' => '#000000',
        ];

        $response = $this->postJson('/api/v1/manager/prizes', $prizeData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Prize created successfully',
                'data' => [
                    'name' => 'New Prize',
                    'media_path' => 'prize-image.jpg',
                    'box_1_text' => 'Box 1 Text',
                    'box_1_bg_color' => '#FF0000',
                    'box_1_text_color' => '#FFFFFF',
                    'box_2_text' => 'Box 2 Text',
                    'box_2_bg_color' => '#00FF00',
                    'box_2_text_color' => '#000000',
                ],
            ]);

        $this->assertDatabaseHas('prizes', [
            'name' => 'New Prize',
            'media_path' => 'prize-image.jpg',
        ]);
    }

    public function test_manager_can_update_prize()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create([
            'name' => 'Old Name',
            'box_1_text' => 'Old Box 1',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'box_1_text' => 'Updated Box 1',
            'box_1_bg_color' => '#0000FF',
        ];

        $response = $this->putJson("/api/v1/manager/prizes/{$prize->id_prize}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize updated successfully',
                'data' => [
                    'id_prize' => $prize->id_prize,
                    'name' => 'Updated Name',
                    'box_1_text' => 'Updated Box 1',
                    'box_1_bg_color' => '#0000FF',
                ],
            ]);

        $this->assertDatabaseHas('prizes', [
            'id_prize' => $prize->id_prize,
            'name' => 'Updated Name',
            'box_1_text' => 'Updated Box 1',
        ]);
    }

    public function test_manager_can_partially_update_prize()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create([
            'name' => 'Original Name',
            'box_1_text' => 'Original Box 1',
            'box_1_bg_color' => '#FF0000',
        ]);

        // Test partial update with only name
        $response = $this->patchJson("/api/v1/manager/prizes/{$prize->id_prize}", [
            'name' => 'Partially Updated Name',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize partially updated successfully',
                'data' => [
                    'name' => 'Partially Updated Name',
                    'box_1_text' => 'Original Box 1', // Should remain unchanged
                    'box_1_bg_color' => '#FF0000', // Should remain unchanged
                ],
            ]);

        // Test partial update with only box_1_text
        $response = $this->patchJson("/api/v1/manager/prizes/{$prize->id_prize}", [
            'box_1_text' => 'Partially Updated Box 1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize partially updated successfully',
                'data' => [
                    'name' => 'Partially Updated Name', // Should remain from previous update
                    'box_1_text' => 'Partially Updated Box 1',
                ],
            ]);
    }

    public function test_manager_cannot_update_prize_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create();

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/prizes/{$prize->id_prize}", [
            'box_1_text' => 'Only Box Text',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Test PUT with empty required fields
        $response = $this->putJson("/api/v1/manager/prizes/{$prize->id_prize}", [
            'name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_manager_cannot_delete_prize_with_club_prizes()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create();
        SponsorPrize::factory()->create(['prize_id' => $prize->id_prize]);

        $response = $this->deleteJson("/api/v1/manager/prizes/{$prize->id_prize}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Prize cannot be deleted because it has linked data. Please remove all associated lottery_prizes_count, sponsor_prizes_count first.',
            ]);

        $this->assertDatabaseHas('prizes', [
            'id_prize' => $prize->id_prize,
        ]);
    }

    public function test_manager_cannot_delete_prize_with_lotteries()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create();
        EventLotteryPrize::factory()->create(['prize_id' => $prize->id_prize]);

        $response = $this->deleteJson("/api/v1/manager/prizes/{$prize->id_prize}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Prize cannot be deleted because it has linked data. Please remove all associated lottery_prizes_count, sponsor_prizes_count first.',
            ]);

        $this->assertDatabaseHas('prizes', [
            'id_prize' => $prize->id_prize,
        ]);
    }

    public function test_prize_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/prizes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_prize_creation_validation_invalid_colors()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/prizes', [
            'name' => 'Test Prize',
            'box_1_bg_color' => 'invalid-color',
            'box_1_text_color' => 'invalid-color',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['box_1_bg_color', 'box_1_text_color']);
    }

    public function test_prize_update_validation_duplicate_name()
    {
        Sanctum::actingAs($this->manager);

        $prize1 = Prize::factory()->create(['name' => 'Existing Prize']);
        $prize2 = Prize::factory()->create(['name' => 'Another Prize']);

        $response = $this->putJson("/api/v1/manager/prizes/{$prize2->id_prize}", [
            'name' => 'Existing Prize',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_prize_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/prizes');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_prize_management()
    {
        $player = User::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/prizes');

        $response->assertStatus(403);
    }

    public function test_prize_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/prizes/99999');

        $response->assertStatus(404);
    }

    public function test_prize_pagination()
    {
        Sanctum::actingAs($this->manager);

        Prize::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/manager/prizes?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_prize',
                        'name',
                        'media_path',
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

    public function test_manager_can_delete_prize()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create();

        $response = $this->deleteJson("/api/v1/manager/prizes/{$prize->id_prize}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize deleted successfully',
            ]);

        $this->assertDatabaseMissing('prizes', ['id_prize' => $prize->id_prize]);
    }

    public function test_manager_cannot_delete_prize_with_linked_club_prizes()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create();
        $club = Club::factory()->create();
        $sponsorPrize = SponsorPrize::factory()->create([
            'sponsor_id' => Sponsor::factory()->forClub($club)->create()->id_sponsor,
            'prize_id' => $prize->id_prize,
        ]);

        $response = $this->deleteJson("/api/v1/manager/prizes/{$prize->id_prize}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Prize cannot be deleted because it has linked data. Please remove all associated lottery_prizes_count, sponsor_prizes_count first.',
            ]);

        $this->assertDatabaseHas('prizes', ['id_prize' => $prize->id_prize]);
    }

    public function test_manager_cannot_delete_prize_with_linked_lotteries()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create();
        $event = PadelEvent::factory()->create();
        $lottery = EventLottery::factory()->create([
            'event_id' => $event->id_event,
        ]);
        EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_id' => $prize->id_prize,
        ]);

        $response = $this->deleteJson("/api/v1/manager/prizes/{$prize->id_prize}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Prize cannot be deleted because it has linked data. Please remove all associated lottery_prizes_count, sponsor_prizes_count first.',
            ]);

        $this->assertDatabaseHas('prizes', ['id_prize' => $prize->id_prize]);
    }

    public function test_manager_can_bulk_delete_prizes()
    {
        Sanctum::actingAs($this->manager);

        $prizes = Prize::factory()->count(3)->create();

        $response = $this->deleteJson('/api/v1/manager/prizes/bulk', [
            'prize_ids' => $prizes->pluck('id_prize')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prizes deleted successfully',
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

        // Verify all prizes are deleted
        foreach ($prizes as $prize) {
            $this->assertDatabaseMissing('prizes', ['id_prize' => $prize->id_prize]);
        }
    }

    public function test_manager_cannot_bulk_delete_prizes_with_linked_data()
    {
        Sanctum::actingAs($this->manager);

        $prize1 = Prize::factory()->create();
        $prize2 = Prize::factory()->create();
        $prize3 = Prize::factory()->create();

        // Create linked sponsor prize for prize1
        $club = Club::factory()->create();
        SponsorPrize::factory()->create([
            'sponsor_id' => Sponsor::factory()->forClub($club)->create()->id_sponsor,
            'prize_id' => $prize1->id_prize,
        ]);

        $response = $this->deleteJson('/api/v1/manager/prizes/bulk', [
            'prize_ids' => [$prize1->id_prize, $prize2->id_prize, $prize3->id_prize],
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
        $this->assertDatabaseMissing('prizes', ['id_prize' => $prize2->id_prize]);
        $this->assertDatabaseMissing('prizes', ['id_prize' => $prize3->id_prize]);
        // Verify failed deletion still exists
        $this->assertDatabaseHas('prizes', ['id_prize' => $prize1->id_prize]);
    }

    public function test_manager_cannot_bulk_delete_prizes_with_invalid_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/prizes/bulk', [
            'prize_ids' => [99999, 99998],
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

    public function test_manager_cannot_bulk_delete_prizes_with_empty_array()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/prizes/bulk', [
            'prize_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_prizes_without_prize_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/prizes/bulk', []);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_prizes_with_invalid_prize_ids_format()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/prizes/bulk', [
            'prize_ids' => ['invalid', 'format'],
        ]);

        $response->assertStatus(422);
    }
}
