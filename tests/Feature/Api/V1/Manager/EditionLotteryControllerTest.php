<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionLottery;
use App\Models\EditionLotteryLucky;
use App\Models\EditionLotteryPrize;
use App\Models\EditionLotteryRule;
use App\Models\EditionParticipation;
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
    }

    public function test_manager_can_view_lotteries(): void
    {
        Sanctum::actingAs($this->manager);

        EditionLottery::factory()->count(5)->create([
            'edition_id' => $this->edition->id_edition,
        ]);

        $response = $this->getJson('/api/v1/manager/edition-lotteries');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_lottery',
                        'edition_id',
                        'name',
                        'display_order',
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
            'name' => 'Main Draw',
            'display_order' => 2,
        ]);

        $response = $this->getJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id_lottery' => $lottery->id_lottery,
                    'edition_id' => $this->edition->id_edition,
                    'name' => 'Main Draw',
                    'display_order' => 2,
                ],
            ]);
    }

    public function test_manager_can_create_lottery(): void
    {
        Sanctum::actingAs($this->manager);

        $payload = [
            'edition_id' => $this->edition->id_edition,
            'name' => 'Opening Lottery',
            'display_order' => 1,
        ];

        $response = $this->postJson('/api/v1/manager/edition-lotteries', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'edition_id' => $this->edition->id_edition,
                    'name' => 'Opening Lottery',
                    'display_order' => 1,
                ],
            ]);

        $this->assertDatabaseHas('edition_lotteries', [
            'edition_id' => $this->edition->id_edition,
            'name' => 'Opening Lottery',
            'display_order' => 1,
        ]);
        $this->assertDatabaseCount('edition_lottery_rules', 0);
        $this->assertDatabaseCount('edition_lottery_prizes', 0);
    }

    public function test_create_rejects_legacy_prize_and_rules_fields(): void
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create();

        $response = $this->postJson('/api/v1/manager/edition-lotteries', [
            'edition_id' => $this->edition->id_edition,
            'name' => 'Legacy Payload',
            'prize_id' => $prize->id_prize,
            'prize_quantity' => 5,
            'rules' => [
                ['rule_prize_quantity' => 1, 'orderliness' => 0],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prize_id', 'prize_quantity', 'rules']);
    }

    public function test_create_enforces_unique_edition_id_name(): void
    {
        Sanctum::actingAs($this->manager);

        EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'name' => 'Duplicate Name',
        ]);

        $response = $this->postJson('/api/v1/manager/edition-lotteries', [
            'edition_id' => $this->edition->id_edition,
            'name' => 'Duplicate Name',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_manager_can_update_lottery(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'name' => 'Old Name',
            'display_order' => 0,
        ]);

        $response = $this->putJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}", [
            'edition_id' => $this->edition->id_edition,
            'name' => 'New Name',
            'display_order' => 4,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Name',
                    'display_order' => 4,
                ],
            ]);
    }

    public function test_manager_can_patch_lottery(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'name' => 'Patch Me',
            'display_order' => 1,
        ]);

        $response = $this->patchJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}", [
            'display_order' => 9,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Patch Me',
                    'display_order' => 9,
                ],
            ]);
    }

    public function test_manager_cannot_delete_lottery_with_lottery_prizes(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'name' => 'Blocked',
        ]);
        EditionLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
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
            'name' => 'Empty',
        ]);

        $response = $this->deleteJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('edition_lotteries', [
            'id_lottery' => $lottery->id_lottery,
        ]);
    }

    public function test_create_requires_name(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/edition-lotteries', [
            'edition_id' => $this->edition->id_edition,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_unauthenticated_user_cannot_access_lotteries(): void
    {
        $response = $this->getJson('/api/v1/manager/edition-lotteries');
        $response->assertStatus(401);
    }

    public function test_index_and_show_expose_has_luckies_false_without_luckies(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'name' => 'No Luckies',
        ]);
        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
        ]);
        EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);

        $index = $this->getJson('/api/v1/manager/edition-lotteries?filters[edition_id]=' . $this->edition->id_edition);
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lottery', $lottery->id_lottery);
        $this->assertNotNull($row);
        $this->assertFalse($row['has_luckies']);

        $show = $this->getJson('/api/v1/manager/edition-lotteries/' . $lottery->id_lottery);
        $show->assertOk();
        $this->assertFalse($show->json('data.has_luckies'));
    }

    public function test_index_and_show_expose_has_luckies_true_with_lucky(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'name' => 'With Luckies',
        ]);
        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
        ]);
        $rule = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);
        $participation = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
        ]);
        EditionLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
        ]);

        $index = $this->getJson('/api/v1/manager/edition-lotteries?filters[edition_id]=' . $this->edition->id_edition);
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lottery', $lottery->id_lottery);
        $this->assertTrue($row['has_luckies']);

        $show = $this->getJson('/api/v1/manager/edition-lotteries/' . $lottery->id_lottery);
        $show->assertOk();
        $this->assertTrue($show->json('data.has_luckies'));
    }

    public function test_generate_rules_happy_path_wipes_and_inserts(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'name' => 'Generate Me',
        ]);
        EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'skill_order' => 0,
        ]);
        EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'skill_order' => 1,
        ]);
        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_quantity' => 3,
        ]);
        $oldRule = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 3,
            'gender' => 'MALE',
        ]);

        $response = $this->postJson('/api/v1/manager/edition-lotteries/' . $lottery->id_lottery . '/rules/generate');
        $response->assertOk();

        $this->assertDatabaseMissing('edition_lottery_rules', [
            'id_lottery_rule' => $oldRule->id_lottery_rule,
        ]);
        $this->assertDatabaseCount('edition_lottery_rules', 3);

        $rules = collect($response->json('data'));
        $this->assertCount(3, $rules);
        foreach ($rules as $rule) {
            $this->assertSame(1, $rule['rule_prize_quantity']);
            $this->assertNotNull($rule['edition_division_id']);
            $this->assertNull($rule['gender']);
            $this->assertSame($line->id_lottery_prize, $rule['lottery_prize_id']);
        }

        $show = $this->getJson('/api/v1/manager/edition-lotteries/' . $lottery->id_lottery);
        $this->assertFalse($show->json('data.has_luckies'));

        $prizeShow = $this->getJson('/api/v1/manager/edition-lottery-prizes/' . $line->id_lottery_prize);
        $this->assertSame(3, $prizeShow->json('data.rules_count'));
        $this->assertSame(3, $prizeShow->json('data.rule_prize_quantity_total'));
        $this->assertSame(3, $prizeShow->json('data.prize_quantity'));
    }

    public function test_generate_rules_guards(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EditionLottery::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'name' => 'Guarded',
        ]);

        $this->postJson('/api/v1/manager/edition-lotteries/' . $lottery->id_lottery . '/rules/generate')
            ->assertStatus(409)
            ->assertJson(['message' => 'Lottery has no prize lines.']);

        EditionLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_quantity' => 1,
        ]);

        $this->postJson('/api/v1/manager/edition-lotteries/' . $lottery->id_lottery . '/rules/generate')
            ->assertStatus(409)
            ->assertJson(['message' => 'Edition has no divisions.']);

        EditionDivision::factory()->create(['edition_id' => $this->edition->id_edition]);

        $this->edition->update(['finalized_at' => time()]);
        $this->postJson('/api/v1/manager/edition-lotteries/' . $lottery->id_lottery . '/rules/generate')
            ->assertStatus(409)
            ->assertJson(['message' => 'Edition is finalized.']);
        $this->edition->update(['finalized_at' => null]);

        $line = $lottery->lotteryPrizes()->first();
        $rule = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);
        $participation = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
        ]);
        EditionLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
        ]);

        $this->postJson('/api/v1/manager/edition-lotteries/' . $lottery->id_lottery . '/rules/generate')
            ->assertStatus(409)
            ->assertJson(['message' => 'Lottery already has luckies.']);
    }
}
