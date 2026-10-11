<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\EventLottery;
use App\Models\EventLotteryLucky;
use App\Models\EventLotteryPrize;
use App\Models\EventLotteryRule;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\Prize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventLotteryRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: EventLotteryPrize, 1: EventLotteryRule}
     */
    private function createPrizeLineWithRule(PadelEvent $event, string $prizeName, int $quantity = 2): array
    {
        $prize = Prize::factory()->create(['name' => $prizeName]);
        $lottery = EventLottery::factory()->create([
            'event_id' => $event->id_event,
            'name' => 'Rule Lottery',
        ]);
        $lotteryPrize = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_id' => $prize->id_prize,
            'prize_quantity' => $quantity,
        ]);
        $rule = EventLotteryRule::factory()->create([
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'rule_prize_quantity' => $quantity,
        ]);

        return [$lotteryPrize, $rule];
    }

    public function test_rule_index_and_show_include_lottery_prize_name_without_rules_cycle(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        [, $rule] = $this->createPrizeLineWithRule($event, 'Towel', 2);

        $index = $this->getJson('/api/v1/manager/event-lottery-rules');
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lottery_rule', $rule->id_lottery_rule);
        $this->assertNotNull($row);
        $this->assertSame('Towel', $row['lottery_prize']['prize']['name'] ?? null);
        $this->assertArrayNotHasKey('lottery', $row);
        $this->assertArrayNotHasKey('rules', $row['lottery_prize'] ?? []);
        $this->assertArrayNotHasKey('luckies', $row);

        $show = $this->getJson('/api/v1/manager/event-lottery-rules/' . $rule->id_lottery_rule);
        $show->assertOk();
        $this->assertSame('Towel', $show->json('data.lottery_prize.prize.name'));
        $this->assertArrayNotHasKey('rules', $show->json('data.lottery_prize') ?? []);
        $this->assertArrayNotHasKey('luckies', $show->json('data') ?? []);
    }

    public function test_rule_index_omits_luckies_even_when_with_luckies_is_sent(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        [, $rule] = $this->createPrizeLineWithRule($event, 'Towel', 1);

        $player = User::factory()->create();
        $participation = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'player_user_id' => $player->id_user,
        ]);
        EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
            'accepted_at' => null,
            'rejected_at' => null,
        ]);

        $index = $this->getJson('/api/v1/manager/event-lottery-rules?with_luckies=1');
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lottery_rule', $rule->id_lottery_rule);
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('luckies', $row);
    }

    public function test_rule_show_omits_luckies(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        [, $rule] = $this->createPrizeLineWithRule($event, 'Towel', 1);

        $player = User::factory()->create();
        $participation = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'player_user_id' => $player->id_user,
        ]);
        EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
            'accepted_at' => null,
            'rejected_at' => null,
        ]);

        $show = $this->getJson('/api/v1/manager/event-lottery-rules/' . $rule->id_lottery_rule);
        $show->assertOk();
        $this->assertArrayNotHasKey('luckies', $show->json('data') ?? []);
    }

    public function test_index_and_show_expose_luckies_count(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        [, $rule] = $this->createPrizeLineWithRule($event, 'Bottle', 1);

        $indexEmpty = $this->getJson('/api/v1/manager/event-lottery-rules');
        $indexEmpty->assertOk();
        $row = collect($indexEmpty->json('data'))->firstWhere('id_lottery_rule', $rule->id_lottery_rule);
        $this->assertSame(0, $row['luckies_count']);

        $participation = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
        ]);
        EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
        ]);

        $index = $this->getJson('/api/v1/manager/event-lottery-rules');
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lottery_rule', $rule->id_lottery_rule);
        $this->assertSame(1, $row['luckies_count']);

        $show = $this->getJson('/api/v1/manager/event-lottery-rules/' . $rule->id_lottery_rule);
        $show->assertOk();
        $this->assertSame(1, $show->json('data.luckies_count'));
    }

    public function test_cud_returns_409_when_edition_finalized(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create(['finalized_at' => time()]);
        [$lotteryPrize, $rule] = $this->createPrizeLineWithRule($event, 'Finalized Prize', 1);

        $this->postJson('/api/v1/manager/event-lottery-rules', [
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ])->assertStatus(409)->assertJson(['message' => 'PadelEvent is finalized.']);

        $this->putJson('/api/v1/manager/event-lottery-rules/' . $rule->id_lottery_rule, [
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'rule_prize_quantity' => 2,
        ])->assertStatus(409)->assertJson(['message' => 'PadelEvent is finalized.']);

        $this->patchJson('/api/v1/manager/event-lottery-rules/' . $rule->id_lottery_rule, [
            'rule_prize_quantity' => 2,
        ])->assertStatus(409)->assertJson(['message' => 'PadelEvent is finalized.']);

        $this->deleteJson('/api/v1/manager/event-lottery-rules/' . $rule->id_lottery_rule)
            ->assertStatus(409)
            ->assertJson(['message' => 'PadelEvent is finalized.']);

        $this->deleteJson('/api/v1/manager/event-lottery-rules/bulk', [
            'lottery_rule_ids' => [$rule->id_lottery_rule],
        ])->assertStatus(409)->assertJson(['message' => 'PadelEvent is finalized.']);
    }

    public function test_reorder_rewrites_orderliness(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        $lottery = EventLottery::factory()->create([
            'event_id' => $event->id_event,
            'name' => 'Reorder Rules',
        ]);
        $line = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_quantity' => 2,
        ]);
        $first = EventLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
            'orderliness' => 9,
        ]);
        $second = EventLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
            'orderliness' => 3,
        ]);

        $response = $this->postJson('/api/v1/manager/event-lottery-rules/reorder', [
            'lottery_id' => $lottery->id_lottery,
            'lottery_rule_ids' => [$second->id_lottery_rule, $first->id_lottery_rule],
        ]);
        $response->assertOk();

        $this->assertSame(0, $second->fresh()->orderliness);
        $this->assertSame(1, $first->fresh()->orderliness);
    }

    public function test_reorder_rejects_incomplete_ids_and_guards(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        $lottery = EventLottery::factory()->create([
            'event_id' => $event->id_event,
            'name' => 'Reorder Guard',
        ]);
        $line = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_quantity' => 2,
        ]);
        $a = EventLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);
        $b = EventLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);

        $this->postJson('/api/v1/manager/event-lottery-rules/reorder', [
            'lottery_id' => $lottery->id_lottery,
            'lottery_rule_ids' => [$a->id_lottery_rule],
        ])->assertStatus(422);

        $event->update(['finalized_at' => time()]);
        $this->postJson('/api/v1/manager/event-lottery-rules/reorder', [
            'lottery_id' => $lottery->id_lottery,
            'lottery_rule_ids' => [$a->id_lottery_rule, $b->id_lottery_rule],
        ])->assertStatus(409)->assertJson(['message' => 'PadelEvent is finalized.']);
        $event->update(['finalized_at' => null]);

        $participation = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
        ]);
        EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $a->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
        ]);

        $this->postJson('/api/v1/manager/event-lottery-rules/reorder', [
            'lottery_id' => $lottery->id_lottery,
            'lottery_rule_ids' => [$a->id_lottery_rule, $b->id_lottery_rule],
        ])->assertStatus(409)->assertJson(['message' => 'Lottery already has luckies.']);
    }
}
