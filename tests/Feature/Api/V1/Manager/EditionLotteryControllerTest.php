<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Edition;
use App\Models\EditionLottery;
use App\Models\EditionLotteryLucky;
use App\Models\EditionLotteryRule;
use App\Models\Player;
use App\Models\Prize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditionLotteryControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private Edition $edition;

    private Prize $prize;

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

        $this->prize = Prize::factory()->create([
            'name' => 'Test Prize',
        ]);
    }

    public function test_manager_can_view_lotteries(): void
    {
        Sanctum::actingAs($this->manager);

        EditionLottery::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/edition-lotteries');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_lottery',
                        'edition_id',
                        'prize_id',
                        'prize_quantity',
                    ],
                ],
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_manager_can_view_specific_lottery(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'prize_id' => $this->prize->id_prize,
            'prize_quantity' => 10,
        ]);

        $response = $this->getJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id_lottery' => $lottery->id_lottery,
                    'edition_id' => $this->edition->id_edition,
                    'prize_id' => $this->prize->id_prize,
                    'prize_quantity' => 10,
                ],
            ]);
    }

    public function test_manager_can_create_lottery_with_rules(): void
    {
        Sanctum::actingAs($this->manager);

        $payload = [
            'edition_id' => $this->edition->id_edition,
            'prize_id' => $this->prize->id_prize,
            'prize_quantity' => 5,
            'rules' => [
                [
                    'rule_prize_quantity' => 3,
                    'orderliness' => 0,
                ],
                [
                    'rule_prize_quantity' => 2,
                    'orderliness' => 1,
                    'has_social_status' => 'STUDENT',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/manager/edition-lotteries', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'edition_id' => $this->edition->id_edition,
                    'prize_quantity' => 5,
                ],
            ]);

        $this->assertDatabaseHas('edition_lotteries', [
            'edition_id' => $this->edition->id_edition,
            'prize_quantity' => 5,
        ]);
        $this->assertDatabaseCount('edition_lottery_rules', 2);
    }

    public function test_manager_can_update_lottery(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'prize_quantity' => 3,
        ]);

        $response = $this->putJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}", [
            'edition_id' => $this->edition->id_edition,
            'prize_id' => $this->prize->id_prize,
            'prize_quantity' => 8,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'prize_quantity' => 8,
                    'prize_id' => $this->prize->id_prize,
                ],
            ]);
    }

    public function test_manager_can_patch_lottery(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'prize_quantity' => 3,
        ]);

        $response = $this->patchJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}", [
            'prize_quantity' => 7,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'prize_quantity' => 7,
                ],
            ]);
    }

    public function test_manager_cannot_delete_lottery_with_rules_or_luckies(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'prize_quantity' => 2,
        ]);
        $rule = EditionLotteryRule::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'rule_prize_quantity' => 2,
        ]);
        EditionLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
        ]);

        $response = $this->deleteJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('edition_lotteries', [
            'id_lottery' => $lottery->id_lottery,
        ]);
    }

    public function test_manager_can_delete_empty_lottery(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'prize_quantity' => 1,
        ]);

        $response = $this->deleteJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('edition_lotteries', [
            'id_lottery' => $lottery->id_lottery,
        ]);
    }

    public function test_create_requires_prize_quantity(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/edition-lotteries', [
            'edition_id' => $this->edition->id_edition,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prize_quantity']);
    }

    public function test_unauthenticated_user_cannot_access_lotteries(): void
    {
        $response = $this->getJson('/api/v1/manager/edition-lotteries');
        $response->assertStatus(401);
    }
}
