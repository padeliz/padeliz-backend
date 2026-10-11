<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\EventDivision;
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

class EventLotteryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private PadelEvent $event;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);

        $this->event = PadelEvent::factory()->create([
            'name' => 'Test PadelEvent',
        ]);
    }

    public function test_manager_can_view_lotteries(): void
    {
        Sanctum::actingAs($this->manager);

        EventLottery::factory()->count(5)->create([
            'event_id' => $this->event->id_event,
        ]);

        $response = $this->getJson('/api/v1/manager/event-lotteries');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_lottery',
                        'event_id',
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

        $lottery = EventLottery::factory()->create([
            'event_id' => $this->event->id_event,
            'name' => 'Main Draw',
            'display_order' => 2,
        ]);

        $response = $this->getJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id_lottery' => $lottery->id_lottery,
                    'event_id' => $this->event->id_event,
                    'name' => 'Main Draw',
                    'display_order' => 2,
                ],
            ]);
    }

    public function test_manager_can_create_lottery(): void
    {
        Sanctum::actingAs($this->manager);

        $payload = [
            'event_id' => $this->event->id_event,
            'name' => 'Opening Lottery',
            'display_order' => 1,
        ];

        $response = $this->postJson('/api/v1/manager/event-lotteries', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'event_id' => $this->event->id_event,
                    'name' => 'Opening Lottery',
                    'display_order' => 1,
                ],
            ]);

        $this->assertDatabaseHas('event_lotteries', [
            'event_id' => $this->event->id_event,
            'name' => 'Opening Lottery',
            'display_order' => 1,
        ]);
        $this->assertDatabaseCount('event_lottery_rules', 0);
        $this->assertDatabaseCount('event_lottery_prizes', 0);
    }

    public function test_create_rejects_legacy_prize_and_rules_fields(): void
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create();

        $response = $this->postJson('/api/v1/manager/event-lotteries', [
            'event_id' => $this->event->id_event,
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

    public function test_create_enforces_unique_event_id_name(): void
    {
        Sanctum::actingAs($this->manager);

        EventLottery::factory()->create([
            'event_id' => $this->event->id_event,
            'name' => 'Duplicate Name',
        ]);

        $response = $this->postJson('/api/v1/manager/event-lotteries', [
            'event_id' => $this->event->id_event,
            'name' => 'Duplicate Name',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_manager_can_update_lottery(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EventLottery::factory()->create([
            'event_id' => $this->event->id_event,
            'name' => 'Old Name',
            'display_order' => 0,
        ]);

        $response = $this->putJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}", [
            'event_id' => $this->event->id_event,
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

        $lottery = EventLottery::factory()->create([
            'event_id' => $this->event->id_event,
            'name' => 'Patch Me',
            'display_order' => 1,
        ]);

        $response = $this->patchJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}", [
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

        $lottery = EventLottery::factory()->create([
            'event_id' => $this->event->id_event,
            'name' => 'Blocked',
        ]);
        EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
        ]);

        $response = $this->deleteJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('event_lotteries', [
            'id_lottery' => $lottery->id_lottery,
        ]);
    }

    public function test_manager_can_delete_empty_lottery(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EventLottery::factory()->create([
            'event_id' => $this->event->id_event,
            'name' => 'Empty',
        ]);

        $response = $this->deleteJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('event_lotteries', [
            'id_lottery' => $lottery->id_lottery,
        ]);
    }

    public function test_create_requires_name(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/event-lotteries', [
            'event_id' => $this->event->id_event,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_unauthenticated_user_cannot_access_lotteries(): void
    {
        $response = $this->getJson('/api/v1/manager/event-lotteries');
        $response->assertStatus(401);
    }

    public function test_index_and_show_expose_has_luckies_false_without_luckies(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EventLottery::factory()->create([
            'event_id' => $this->event->id_event,
            'name' => 'No Luckies',
        ]);
        $line = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
        ]);
        EventLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);

        $index = $this->getJson('/api/v1/manager/event-lotteries?filters[event_id]=' . $this->event->id_event);
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lottery', $lottery->id_lottery);
        $this->assertNotNull($row);
        $this->assertFalse($row['has_luckies']);

        $show = $this->getJson('/api/v1/manager/event-lotteries/' . $lottery->id_lottery);
        $show->assertOk();
        $this->assertFalse($show->json('data.has_luckies'));
    }

    public function test_index_and_show_expose_has_luckies_true_with_lucky(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EventLottery::factory()->create([
            'event_id' => $this->event->id_event,
            'name' => 'With Luckies',
        ]);
        $line = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
        ]);
        $rule = EventLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);
        $participation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
        ]);
        EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
        ]);

        $index = $this->getJson('/api/v1/manager/event-lotteries?filters[event_id]=' . $this->event->id_event);
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lottery', $lottery->id_lottery);
        $this->assertTrue($row['has_luckies']);

        $show = $this->getJson('/api/v1/manager/event-lotteries/' . $lottery->id_lottery);
        $show->assertOk();
        $this->assertTrue($show->json('data.has_luckies'));
    }

    public function test_generate_rules_happy_path_wipes_and_inserts(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EventLottery::factory()->create([
            'event_id' => $this->event->id_event,
            'name' => 'Generate Me',
        ]);
        EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'skill_order' => 0,
        ]);
        EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'skill_order' => 1,
        ]);
        $line = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_quantity' => 3,
        ]);
        $oldRule = EventLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 3,
            'gender' => 'MALE',
        ]);

        $response = $this->postJson('/api/v1/manager/event-lotteries/' . $lottery->id_lottery . '/rules/generate');
        $response->assertOk();

        $this->assertDatabaseMissing('event_lottery_rules', [
            'id_lottery_rule' => $oldRule->id_lottery_rule,
        ]);
        $this->assertDatabaseCount('event_lottery_rules', 3);

        $rules = collect($response->json('data'));
        $this->assertCount(3, $rules);
        foreach ($rules as $rule) {
            $this->assertSame(1, $rule['rule_prize_quantity']);
            $this->assertNotNull($rule['event_division_id']);
            $this->assertNull($rule['gender']);
            $this->assertSame($line->id_lottery_prize, $rule['lottery_prize_id']);
        }

        $show = $this->getJson('/api/v1/manager/event-lotteries/' . $lottery->id_lottery);
        $this->assertFalse($show->json('data.has_luckies'));

        $prizeShow = $this->getJson('/api/v1/manager/event-lottery-prizes/' . $line->id_lottery_prize);
        $this->assertSame(3, $prizeShow->json('data.rules_count'));
        $this->assertSame(3, $prizeShow->json('data.rule_prize_quantity_total'));
        $this->assertSame(3, $prizeShow->json('data.prize_quantity'));
    }

    public function test_generate_rules_guards(): void
    {
        Sanctum::actingAs($this->manager);

        $lottery = EventLottery::factory()->create([
            'event_id' => $this->event->id_event,
            'name' => 'Guarded',
        ]);

        $this->postJson('/api/v1/manager/event-lotteries/' . $lottery->id_lottery . '/rules/generate')
            ->assertStatus(409)
            ->assertJson(['message' => 'Lottery has no prize lines.']);

        EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_quantity' => 1,
        ]);

        $this->postJson('/api/v1/manager/event-lotteries/' . $lottery->id_lottery . '/rules/generate')
            ->assertStatus(409)
            ->assertJson(['message' => 'PadelEvent has no divisions.']);

        EventDivision::factory()->create(['event_id' => $this->event->id_event]);

        $this->event->update(['finalized_at' => time()]);
        $this->postJson('/api/v1/manager/event-lotteries/' . $lottery->id_lottery . '/rules/generate')
            ->assertStatus(409)
            ->assertJson(['message' => 'PadelEvent is finalized.']);
        $this->event->update(['finalized_at' => null]);

        $line = $lottery->lotteryPrizes()->first();
        $rule = EventLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);
        $participation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
        ]);
        EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
        ]);

        $this->postJson('/api/v1/manager/event-lotteries/' . $lottery->id_lottery . '/rules/generate')
            ->assertStatus(409)
            ->assertJson(['message' => 'Lottery already has luckies.']);
    }
}
