<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Edition;
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

class EditionLotteryRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: EditionLotteryPrize, 1: EditionLotteryRule}
     */
    private function createPrizeLineWithRule(Edition $edition, string $prizeName, int $quantity = 2): array
    {
        $prize = Prize::factory()->create(['name' => $prizeName]);
        $lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'name' => 'Rule Lottery',
        ]);
        $lotteryPrize = EditionLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_id' => $prize->id_prize,
            'prize_quantity' => $quantity,
        ]);
        $rule = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'rule_prize_quantity' => $quantity,
        ]);

        return [$lotteryPrize, $rule];
    }

    public function test_rule_index_and_show_include_lottery_prize_name_without_rules_cycle(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create();
        [, $rule] = $this->createPrizeLineWithRule($edition, 'Towel', 2);

        $index = $this->getJson('/api/v1/manager/edition-lottery-rules');
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lottery_rule', $rule->id_lottery_rule);
        $this->assertNotNull($row);
        $this->assertSame('Towel', $row['lottery_prize']['prize']['name'] ?? null);
        $this->assertArrayNotHasKey('lottery', $row);
        $this->assertArrayNotHasKey('rules', $row['lottery_prize'] ?? []);
        $this->assertArrayNotHasKey('luckies', $row);

        $show = $this->getJson('/api/v1/manager/edition-lottery-rules/' . $rule->id_lottery_rule);
        $show->assertOk();
        $this->assertSame('Towel', $show->json('data.lottery_prize.prize.name'));
        $this->assertArrayNotHasKey('rules', $show->json('data.lottery_prize') ?? []);
        $this->assertArrayNotHasKey('luckies', $show->json('data') ?? []);
    }

    public function test_rule_index_omits_luckies_even_when_with_luckies_is_sent(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create();
        [, $rule] = $this->createPrizeLineWithRule($edition, 'Towel', 1);

        $player = Player::factory()->create();
        $participation = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $player->id_player,
        ]);
        EditionLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
            'accepted_at' => null,
            'rejected_at' => null,
        ]);

        $index = $this->getJson('/api/v1/manager/edition-lottery-rules?with_luckies=1');
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lottery_rule', $rule->id_lottery_rule);
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('luckies', $row);
    }

    public function test_rule_show_omits_luckies(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create();
        [, $rule] = $this->createPrizeLineWithRule($edition, 'Towel', 1);

        $player = Player::factory()->create();
        $participation = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $player->id_player,
        ]);
        EditionLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
            'accepted_at' => null,
            'rejected_at' => null,
        ]);

        $show = $this->getJson('/api/v1/manager/edition-lottery-rules/' . $rule->id_lottery_rule);
        $show->assertOk();
        $this->assertArrayNotHasKey('luckies', $show->json('data') ?? []);
    }

    public function test_index_and_show_expose_luckies_count(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create();
        [, $rule] = $this->createPrizeLineWithRule($edition, 'Bottle', 1);

        $indexEmpty = $this->getJson('/api/v1/manager/edition-lottery-rules');
        $indexEmpty->assertOk();
        $row = collect($indexEmpty->json('data'))->firstWhere('id_lottery_rule', $rule->id_lottery_rule);
        $this->assertSame(0, $row['luckies_count']);

        $participation = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
        ]);
        EditionLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
        ]);

        $index = $this->getJson('/api/v1/manager/edition-lottery-rules');
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lottery_rule', $rule->id_lottery_rule);
        $this->assertSame(1, $row['luckies_count']);

        $show = $this->getJson('/api/v1/manager/edition-lottery-rules/' . $rule->id_lottery_rule);
        $show->assertOk();
        $this->assertSame(1, $show->json('data.luckies_count'));
    }

    public function test_cud_returns_409_when_edition_finalized(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create(['finalized_at' => time()]);
        [$lotteryPrize, $rule] = $this->createPrizeLineWithRule($edition, 'Finalized Prize', 1);

        $this->postJson('/api/v1/manager/edition-lottery-rules', [
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ])->assertStatus(409)->assertJson(['message' => 'Edition is finalized.']);

        $this->putJson('/api/v1/manager/edition-lottery-rules/' . $rule->id_lottery_rule, [
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'rule_prize_quantity' => 2,
        ])->assertStatus(409)->assertJson(['message' => 'Edition is finalized.']);

        $this->patchJson('/api/v1/manager/edition-lottery-rules/' . $rule->id_lottery_rule, [
            'rule_prize_quantity' => 2,
        ])->assertStatus(409)->assertJson(['message' => 'Edition is finalized.']);

        $this->deleteJson('/api/v1/manager/edition-lottery-rules/' . $rule->id_lottery_rule)
            ->assertStatus(409)
            ->assertJson(['message' => 'Edition is finalized.']);

        $this->deleteJson('/api/v1/manager/edition-lottery-rules/bulk', [
            'lottery_rule_ids' => [$rule->id_lottery_rule],
        ])->assertStatus(409)->assertJson(['message' => 'Edition is finalized.']);
    }

    public function test_reorder_rewrites_orderliness(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create();
        $lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'name' => 'Reorder Rules',
        ]);
        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_quantity' => 2,
        ]);
        $first = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
            'orderliness' => 9,
        ]);
        $second = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
            'orderliness' => 3,
        ]);

        $response = $this->postJson('/api/v1/manager/edition-lottery-rules/reorder', [
            'lottery_id' => $lottery->id_lottery,
            'lottery_rule_ids' => [$second->id_lottery_rule, $first->id_lottery_rule],
        ]);
        $response->assertOk();

        $this->assertSame(0, $second->fresh()->orderliness);
        $this->assertSame(1, $first->fresh()->orderliness);
    }

    public function test_reorder_rejects_incomplete_ids_and_guards(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create();
        $lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'name' => 'Reorder Guard',
        ]);
        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_quantity' => 2,
        ]);
        $a = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);
        $b = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);

        $this->postJson('/api/v1/manager/edition-lottery-rules/reorder', [
            'lottery_id' => $lottery->id_lottery,
            'lottery_rule_ids' => [$a->id_lottery_rule],
        ])->assertStatus(422);

        $edition->update(['finalized_at' => time()]);
        $this->postJson('/api/v1/manager/edition-lottery-rules/reorder', [
            'lottery_id' => $lottery->id_lottery,
            'lottery_rule_ids' => [$a->id_lottery_rule, $b->id_lottery_rule],
        ])->assertStatus(409)->assertJson(['message' => 'Edition is finalized.']);
        $edition->update(['finalized_at' => null]);

        $participation = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
        ]);
        EditionLotteryLucky::factory()->create([
            'lottery_rule_id' => $a->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
        ]);

        $this->postJson('/api/v1/manager/edition-lottery-rules/reorder', [
            'lottery_id' => $lottery->id_lottery,
            'lottery_rule_ids' => [$a->id_lottery_rule, $b->id_lottery_rule],
        ])->assertStatus(409)->assertJson(['message' => 'Lottery already has luckies.']);
    }
}
