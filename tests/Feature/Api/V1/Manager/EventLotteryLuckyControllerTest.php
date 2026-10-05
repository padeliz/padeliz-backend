<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\PadelEvent;
use App\Models\EventDivision;
use App\Models\EventLottery;
use App\Models\EventLotteryLucky;
use App\Models\EventLotteryPrize;
use App\Models\EventLotteryRule;
use App\Models\EventParticipation;
use App\Models\Player;
use App\Models\Prize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventLotteryLuckyControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private EventLotteryRule $lotteryRule;

    private EventParticipation $participation;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);

        $event = PadelEvent::factory()->create();
        $eventDivision = EventDivision::factory()->create([
            'event_id' => $event->id_event,
        ]);
        $player = Player::factory()->create();

        $prize = Prize::factory()->create(['name' => 'Towel']);
        $lottery = EventLottery::factory()->create([
            'event_id' => $event->id_event,
            'name' => 'Lucky Lottery',
        ]);
        $lotteryPrize = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_id' => $prize->id_prize,
            'prize_quantity' => 3,
        ]);

        $this->lotteryRule = EventLotteryRule::factory()->create([
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'rule_prize_quantity' => 3,
        ]);

        $this->participation = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'player_id' => $player->id_player,
            'event_division_id' => $eventDivision->id_event_division,
        ]);
    }

    public function test_manager_can_view_all_luckies(): void
    {
        Sanctum::actingAs($this->manager);

        EventLotteryLucky::factory()->count(3)->create([
            'lottery_rule_id' => $this->lotteryRule->id_lottery_rule,
        ]);

        $response = $this->getJson('/api/v1/manager/event-lottery-luckies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_lucky',
                        'lottery_rule_id',
                        'drawn_participation_id',
                        'drawn_at',
                        'available_at',
                        'announced_at',
                        'accepted_at',
                        'rejected_at',
                        'inserted_at',
                        'updated_at',
                        'short_name',
                        'pdf_avatar_url',
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_lucky_includes_short_name_and_pdf_avatar_from_cache(): void
    {
        Sanctum::actingAs($this->manager);

        $this->participation->update(['is_collecting_points' => true]);
        $player = $this->participation->player()->first();
        $player->update([
            'first_name' => 'Alice',
            'middle_name' => null,
            'last_name' => 'Wonder',
        ]);
        $player->refresh();

        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $player->id_player . '/picture/avatar.png',
        ]);

        $lucky = EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $this->lotteryRule->id_lottery_rule,
            'drawn_participation_id' => $this->participation->id_participation,
        ]);

        $response = $this->getJson("/api/v1/manager/event-lottery-luckies/{$lucky->id_lucky}");
        $response->assertOk();
        $this->assertNotNull($response->json('data.short_name'));
        $this->assertStringContainsString('.', (string) $response->json('data.short_name'));
        $this->assertNotNull($response->json('data.pdf_avatar_url'));

        $urlCollecting = $response->json('data.pdf_avatar_url');
        $this->participation->update(['is_collecting_points' => false]);
        $miss = $this->getJson("/api/v1/manager/event-lottery-luckies/{$lucky->id_lucky}");
        $miss->assertOk();
        $this->assertNull($miss->json('data.pdf_avatar_url'));
        $this->assertNotSame($urlCollecting, $miss->json('data.pdf_avatar_url'));
    }

    public function test_manager_can_view_specific_lucky(): void
    {
        Sanctum::actingAs($this->manager);

        $lucky = EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $this->lotteryRule->id_lottery_rule,
            'drawn_participation_id' => $this->participation->id_participation,
            'drawn_at' => time(),
            'available_at' => time(),
        ]);

        $response = $this->getJson("/api/v1/manager/event-lottery-luckies/{$lucky->id_lucky}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id_lucky' => $lucky->id_lucky,
                    'lottery_rule_id' => $this->lotteryRule->id_lottery_rule,
                    'drawn_participation_id' => $this->participation->id_participation,
                ],
            ]);
    }

    public function test_manager_can_create_lucky(): void
    {
        Sanctum::actingAs($this->manager);

        $payload = [
            'lottery_rule_id' => $this->lotteryRule->id_lottery_rule,
            'drawn_participation_id' => $this->participation->id_participation,
            'drawn_at' => time(),
            'available_at' => time(),
        ];

        $response = $this->postJson('/api/v1/manager/event-lottery-luckies', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'lottery_rule_id' => $this->lotteryRule->id_lottery_rule,
                    'drawn_participation_id' => $this->participation->id_participation,
                ],
            ]);

        $this->assertDatabaseHas('event_lottery_luckies', [
            'lottery_rule_id' => $this->lotteryRule->id_lottery_rule,
            'drawn_participation_id' => $this->participation->id_participation,
        ]);
    }

    public function test_manager_can_update_lucky(): void
    {
        Sanctum::actingAs($this->manager);

        $lucky = EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $this->lotteryRule->id_lottery_rule,
            'drawn_participation_id' => $this->participation->id_participation,
        ]);

        $announcedAt = time() + 100;
        $response = $this->putJson("/api/v1/manager/event-lottery-luckies/{$lucky->id_lucky}", [
            'lottery_rule_id' => $this->lotteryRule->id_lottery_rule,
            'drawn_participation_id' => $this->participation->id_participation,
            'drawn_at' => $lucky->drawn_at,
            'available_at' => $lucky->available_at,
            'announced_at' => $announcedAt,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id_lucky' => $lucky->id_lucky,
                    'announced_at' => $announcedAt,
                ],
            ]);
    }

    public function test_manager_can_patch_lucky(): void
    {
        Sanctum::actingAs($this->manager);

        $lucky = EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $this->lotteryRule->id_lottery_rule,
            'drawn_participation_id' => $this->participation->id_participation,
        ]);

        $acceptedAt = time() + 200;
        $response = $this->patchJson("/api/v1/manager/event-lottery-luckies/{$lucky->id_lucky}", [
            'accepted_at' => $acceptedAt,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'accepted_at' => $acceptedAt,
                ],
            ]);
    }

    public function test_manager_can_delete_lucky(): void
    {
        Sanctum::actingAs($this->manager);

        $lucky = EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $this->lotteryRule->id_lottery_rule,
        ]);

        $response = $this->deleteJson("/api/v1/manager/event-lottery-luckies/{$lucky->id_lucky}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('event_lottery_luckies', [
            'id_lucky' => $lucky->id_lucky,
        ]);
    }

    public function test_manager_can_bulk_delete_luckies(): void
    {
        Sanctum::actingAs($this->manager);

        $luckies = EventLotteryLucky::factory()->count(3)->create([
            'lottery_rule_id' => $this->lotteryRule->id_lottery_rule,
        ]);

        $response = $this->deleteJson('/api/v1/manager/event-lottery-luckies/bulk', [
            'lucky_ids' => $luckies->pluck('id_lucky')->toArray(),
        ]);

        $response->assertStatus(200);
        foreach ($luckies as $lucky) {
            $this->assertDatabaseMissing('event_lottery_luckies', ['id_lucky' => $lucky->id_lucky]);
        }
    }

    public function test_create_requires_drawn_participation_id(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/event-lottery-luckies', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['drawn_participation_id']);
    }

    public function test_unauthenticated_user_cannot_access_luckies(): void
    {
        $response = $this->getJson('/api/v1/manager/event-lottery-luckies');
        $response->assertStatus(401);
    }

    public function test_lucky_index_and_show_include_nested_lottery_prize_name(): void
    {
        Sanctum::actingAs($this->manager);

        $lucky = EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $this->lotteryRule->id_lottery_rule,
            'drawn_participation_id' => $this->participation->id_participation,
        ]);

        $index = $this->getJson('/api/v1/manager/event-lottery-luckies');
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lucky', $lucky->id_lucky);
        $this->assertSame('Towel', $row['lottery_rule']['lottery_prize']['prize']['name'] ?? null);
        $this->assertArrayNotHasKey('rules', $row['lottery_rule']['lottery_prize'] ?? []);

        $show = $this->getJson('/api/v1/manager/event-lottery-luckies/' . $lucky->id_lucky);
        $show->assertOk();
        $this->assertSame('Towel', $show->json('data.lottery_rule.lottery_prize.prize.name'));
        $this->assertArrayNotHasKey('rules', $show->json('data.lottery_rule.lottery_prize') ?? []);
    }
}
