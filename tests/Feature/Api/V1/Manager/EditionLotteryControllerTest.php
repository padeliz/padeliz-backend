<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionLottery;
use App\Models\EditionLuckyOne;
use App\Models\Player;
use App\Models\Prize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditionLotteryControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    private $edition;

    private $division;

    private $prize;

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

        $this->prize = Prize::factory()->create([
            'name' => 'Test Prize',
        ]);
    }

    public function test_manager_can_view_lotteries()
    {
        Sanctum::actingAs($this->manager);

        EditionLottery::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/lotteries');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_lottery',
                        'edition_id',
                        'orderliness',
                        'division_id',
                        'prize_id',
                        'total_draws_nr',
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

    public function test_manager_can_view_specific_lottery()
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'prize_id' => $this->prize->id_prize,
            'total_draws_nr' => 10,
        ]);

        $response = $this->getJson("/api/v1/manager/lotteries/{$lottery->id_lottery}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lottery details retrieved successfully',
                'data' => [
                    'id_lottery' => $lottery->id_lottery,
                    'edition_id' => $this->edition->id_edition,
                    'division_id' => $this->division->id_division,
                    'prize_id' => $this->prize->id_prize,
                    'total_draws_nr' => 10,
                ],
            ]);
    }

    public function test_manager_can_create_lottery()
    {
        Sanctum::actingAs($this->manager);

        $lotteryData = [
            'edition_id' => $this->edition->id_edition,
            'orderliness' => 1,
            'division_id' => $this->division->id_division,
            'prize_id' => $this->prize->id_prize,
            'total_draws_nr' => 5,
        ];

        $response = $this->postJson('/api/v1/manager/lotteries', $lotteryData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Lottery created successfully',
                'data' => [
                    'edition_id' => $this->edition->id_edition,
                    'orderliness' => 1,
                    'division_id' => $this->division->id_division,
                    'prize_id' => $this->prize->id_prize,
                    'total_draws_nr' => 5,
                ],
            ]);

        $this->assertDatabaseHas('edition_lotteries', [
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'prize_id' => $this->prize->id_prize,
            'total_draws_nr' => 5,
        ]);
    }

    public function test_manager_can_update_lottery()
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'orderliness' => 1,
            'total_draws_nr' => 5,
        ]);

        $updateData = [
            'edition_id' => $this->edition->id_edition,
            'orderliness' => 2,
            'total_draws_nr' => 10,
            'division_id' => $this->division->id_division,
        ];

        $response = $this->putJson("/api/v1/manager/lotteries/{$lottery->id_lottery}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lottery updated successfully',
                'data' => [
                    'id_lottery' => $lottery->id_lottery,
                    'orderliness' => 2,
                    'total_draws_nr' => 10,
                    'division_id' => $this->division->id_division,
                ],
            ]);

        $this->assertDatabaseHas('edition_lotteries', [
            'id_lottery' => $lottery->id_lottery,
            'orderliness' => 2,
            'total_draws_nr' => 10,
        ]);
    }

    public function test_manager_can_partially_update_lottery()
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'orderliness' => 1,
            'total_draws_nr' => 5,
        ]);

        // Test partial update with only orderliness
        $response = $this->patchJson("/api/v1/manager/lotteries/{$lottery->id_lottery}", [
            'orderliness' => 3,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lottery partially updated successfully',
                'data' => [
                    'orderliness' => 3,
                    'total_draws_nr' => 5, // Should remain unchanged
                ],
            ]);

        // Test partial update with only total_draws_nr
        $response = $this->patchJson("/api/v1/manager/lotteries/{$lottery->id_lottery}", [
            'total_draws_nr' => 8,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lottery partially updated successfully',
                'data' => [
                    'orderliness' => 3, // Should remain from previous update
                    'total_draws_nr' => 8,
                ],
            ]);
    }

    public function test_manager_cannot_update_lottery_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
        ]);

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/lotteries/{$lottery->id_lottery}", [
            'orderliness' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id', 'total_draws_nr']);

        // Test PUT with empty required fields
        $response = $this->putJson("/api/v1/manager/lotteries/{$lottery->id_lottery}", [
            'edition_id' => '',
            'orderliness' => '',
            'total_draws_nr' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id', 'orderliness', 'total_draws_nr']);
    }

    public function test_manager_can_delete_lottery()
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create();

        $response = $this->deleteJson("/api/v1/manager/lotteries/{$lottery->id_lottery}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lottery deleted successfully',
            ]);

        $this->assertDatabaseMissing('edition_lotteries', [
            'id_lottery' => $lottery->id_lottery,
        ]);
    }

    public function test_manager_cannot_delete_lottery_with_lucky_ones()
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create();
        EditionLuckyOne::factory()->create(['lottery_id' => $lottery->id_lottery]);

        $response = $this->deleteJson("/api/v1/manager/lotteries/{$lottery->id_lottery}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Lottery cannot be deleted because it has linked data. Please remove all associated lucky_ones_count first.',
            ]);

        $this->assertDatabaseHas('edition_lotteries', [
            'id_lottery' => $lottery->id_lottery,
        ]);
    }

    public function test_manager_can_bulk_delete_lotteries()
    {
        Sanctum::actingAs($this->manager);

        $lotteries = EditionLottery::factory()->count(3)->create();

        $response = $this->deleteJson('/api/v1/manager/lotteries/bulk', [
            'lottery_ids' => $lotteries->pluck('id_lottery')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lotteries deleted successfully',
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

        // Verify all lotteries are deleted
        foreach ($lotteries as $lottery) {
            $this->assertDatabaseMissing('edition_lotteries', ['id_lottery' => $lottery->id_lottery]);
        }
    }

    public function test_manager_cannot_bulk_delete_lotteries_with_linked_data()
    {
        Sanctum::actingAs($this->manager);

        $lottery1 = EditionLottery::factory()->create();
        $lottery2 = EditionLottery::factory()->create();
        $lottery3 = EditionLottery::factory()->create();

        // Create linked lucky ones for lottery1
        EditionLuckyOne::factory()->create(['lottery_id' => $lottery1->id_lottery]);

        $response = $this->deleteJson('/api/v1/manager/lotteries/bulk', [
            'lottery_ids' => [$lottery1->id_lottery, $lottery2->id_lottery, $lottery3->id_lottery],
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
        $this->assertDatabaseMissing('edition_lotteries', ['id_lottery' => $lottery2->id_lottery]);
        $this->assertDatabaseMissing('edition_lotteries', ['id_lottery' => $lottery3->id_lottery]);
        // Verify failed deletion still exists
        $this->assertDatabaseHas('edition_lotteries', ['id_lottery' => $lottery1->id_lottery]);
    }

    public function test_manager_cannot_bulk_delete_lotteries_with_invalid_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/lotteries/bulk', [
            'lottery_ids' => [99999, 99998],
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

    public function test_manager_cannot_bulk_delete_lotteries_with_empty_array()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/lotteries/bulk', [
            'lottery_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_lotteries_without_lottery_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/lotteries/bulk', []);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_lotteries_with_invalid_lottery_ids_format()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/lotteries/bulk', [
            'lottery_ids' => ['invalid', 'format'],
        ]);

        $response->assertStatus(422);
    }

    public function test_lottery_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/lotteries', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id', 'total_draws_nr']);
    }

    public function test_lottery_creation_validation_invalid_edition()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/lotteries', [
            'edition_id' => 99999, // Non-existent edition
            'total_draws_nr' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id']);
    }

    public function test_lottery_creation_validation_invalid_draws()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/lotteries', [
            'edition_id' => $this->edition->id_edition,
            'total_draws_nr' => 0, // Invalid - must be at least 1
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['total_draws_nr']);
    }

    public function test_lottery_update_validation()
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create();

        $response = $this->putJson("/api/v1/manager/lotteries/{$lottery->id_lottery}", [
            'edition_id' => 99999, // Non-existent edition
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id']);
    }

    public function test_lottery_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/lotteries');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_lottery_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/lotteries');

        $response->assertStatus(403);
    }

    public function test_lottery_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/lotteries/99999');

        $response->assertStatus(404);
    }

    public function test_lottery_pagination()
    {
        Sanctum::actingAs($this->manager);

        EditionLottery::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/manager/lotteries?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_lottery',
                        'edition_id',
                        'orderliness',
                        'total_draws_nr',
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

    public function test_lottery_creation_with_optional_fields()
    {
        Sanctum::actingAs($this->manager);

        $lotteryData = [
            'edition_id' => $this->edition->id_edition,
            'total_draws_nr' => 3,
            // Not providing division_id and prize_id (optional)
        ];

        $response = $this->postJson('/api/v1/manager/lotteries', $lotteryData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Lottery created successfully',
                'data' => [
                    'edition_id' => $this->edition->id_edition,
                    'total_draws_nr' => 3,
                    'division_id' => null,
                    'prize_id' => null,
                ],
            ]);

        $this->assertDatabaseHas('edition_lotteries', [
            'edition_id' => $this->edition->id_edition,
            'total_draws_nr' => 3,
            'division_id' => null,
            'prize_id' => null,
        ]);
    }
}
