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
                'message' => 'Cannot delete lottery because it has related lucky ones',
            ]);

        $this->assertDatabaseHas('edition_lotteries', [
            'id_lottery' => $lottery->id_lottery,
        ]);
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
