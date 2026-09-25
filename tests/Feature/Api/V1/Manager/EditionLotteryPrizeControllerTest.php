<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\CachedPdfPlayerImage;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionLottery;
use App\Models\EditionLotteryLucky;
use App\Models\EditionLotteryPrize;
use App\Models\EditionLotteryRule;
use App\Models\EditionParticipation;
use App\Models\Player;
use App\Models\PlayerPhone;
use App\Models\Prize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditionLotteryPrizeControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private EditionLottery $lottery;

    private Prize $prize;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $edition = Edition::factory()->create();
        $this->lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'name' => 'Prize Lines Lottery',
        ]);
        $this->prize = Prize::factory()->create(['name' => 'Bottle']);
    }

    public function test_store_requires_prize_id_and_quantity(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/edition-lottery-prizes', [
            'lottery_id' => $this->lottery->id_lottery,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prize_id', 'prize_quantity']);
    }

    public function test_same_prize_twice_on_one_lottery_is_allowed(): void
    {
        Sanctum::actingAs($this->manager);

        $first = $this->postJson('/api/v1/manager/edition-lottery-prizes', [
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
            'prize_quantity' => 2,
            'display_order' => 0,
        ]);
        $first->assertStatus(201);

        $second = $this->postJson('/api/v1/manager/edition-lottery-prizes', [
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
            'prize_quantity' => 1,
            'display_order' => 1,
        ]);
        $second->assertStatus(201);

        $this->assertDatabaseCount('edition_lottery_prizes', 2);
        $this->assertNotSame(
            $first->json('data.id_lottery_prize'),
            $second->json('data.id_lottery_prize')
        );
    }

    public function test_delete_blocked_by_rules(): void
    {
        Sanctum::actingAs($this->manager);

        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
            'prize_quantity' => 1,
        ]);
        EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);

        $response = $this->deleteJson("/api/v1/manager/edition-lottery-prizes/{$line->id_lottery_prize}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('edition_lottery_prizes', [
            'id_lottery_prize' => $line->id_lottery_prize,
        ]);
    }

    public function test_index_filters_by_lottery_id(): void
    {
        Sanctum::actingAs($this->manager);

        $otherLottery = EditionLottery::factory()->create([
            'edition_id' => $this->lottery->edition_id,
            'name' => 'Other Lottery',
        ]);
        $match = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);
        EditionLotteryPrize::factory()->create([
            'lottery_id' => $otherLottery->id_lottery,
            'prize_id' => Prize::factory()->create()->id_prize,
        ]);

        $response = $this->getJson('/api/v1/manager/edition-lottery-prizes?filters[lottery_id]=' . $this->lottery->id_lottery);
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id_lottery_prize')->all();
        $this->assertContains($match->id_lottery_prize, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_manager_can_delete_empty_prize_line(): void
    {
        Sanctum::actingAs($this->manager);

        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);

        $response = $this->deleteJson("/api/v1/manager/edition-lottery-prizes/{$line->id_lottery_prize}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('edition_lottery_prizes', [
            'id_lottery_prize' => $line->id_lottery_prize,
        ]);
    }

    public function test_index_without_include_omits_luckies_fields_and_pending_meta(): void
    {
        Sanctum::actingAs($this->manager);

        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);
        EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 2,
        ]);
        EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);

        $response = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
        ]);
        $response->assertOk();
        $row = $this->lineById($response->json('data'), $line->id_lottery_prize);
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('luckies', $row);
        $this->assertArrayNotHasKey('activity_at', $row);
        $this->assertSame(2, $row['rules_count']);
        $this->assertSame(3, $row['rule_prize_quantity_total']);
        $this->assertArrayHasKey('current_page', $response->json('meta'));
        $this->assertArrayNotHasKey('has_unresolved_pending', $response->json('meta'));
    }

    public function test_index_and_show_expose_zero_counts_when_no_rules(): void
    {
        Sanctum::actingAs($this->manager);

        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);

        $index = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
        ]);
        $index->assertOk();
        $row = $this->lineById($index->json('data'), $line->id_lottery_prize);
        $this->assertSame(0, $row['rules_count']);
        $this->assertSame(0, $row['rule_prize_quantity_total']);

        $show = $this->getJson('/api/v1/manager/edition-lottery-prizes/' . $line->id_lottery_prize);
        $show->assertOk();
        $this->assertSame(0, $show->json('data.rules_count'));
        $this->assertSame(0, $show->json('data.rule_prize_quantity_total'));
    }

    public function test_cud_returns_409_when_edition_finalized(): void
    {
        Sanctum::actingAs($this->manager);

        $this->lottery->edition->update(['finalized_at' => time()]);

        $store = $this->postJson('/api/v1/manager/edition-lottery-prizes', [
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
            'prize_quantity' => 1,
        ]);
        $store->assertStatus(409)->assertJson(['message' => 'Edition is finalized.']);

        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
            'prize_quantity' => 1,
        ]);

        $this->putJson('/api/v1/manager/edition-lottery-prizes/' . $line->id_lottery_prize, [
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
            'prize_quantity' => 2,
        ])->assertStatus(409)->assertJson(['message' => 'Edition is finalized.']);

        $this->patchJson('/api/v1/manager/edition-lottery-prizes/' . $line->id_lottery_prize, [
            'prize_quantity' => 3,
        ])->assertStatus(409)->assertJson(['message' => 'Edition is finalized.']);

        $this->deleteJson('/api/v1/manager/edition-lottery-prizes/' . $line->id_lottery_prize)
            ->assertStatus(409)
            ->assertJson(['message' => 'Edition is finalized.']);

        $this->deleteJson('/api/v1/manager/edition-lottery-prizes/bulk', [
            'lottery_prize_ids' => [$line->id_lottery_prize],
        ])->assertStatus(409)->assertJson(['message' => 'Edition is finalized.']);
    }

    public function test_reorder_rewrites_display_order(): void
    {
        Sanctum::actingAs($this->manager);

        $a = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
            'display_order' => 5,
        ]);
        $b = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => Prize::factory()->create()->id_prize,
            'display_order' => 1,
        ]);

        $response = $this->postJson('/api/v1/manager/edition-lottery-prizes/reorder', [
            'lottery_id' => $this->lottery->id_lottery,
            'lottery_prize_ids' => [$b->id_lottery_prize, $a->id_lottery_prize],
        ]);
        $response->assertOk();

        $this->assertSame(0, $b->fresh()->display_order);
        $this->assertSame(1, $a->fresh()->display_order);
        $ids = collect($response->json('data'))->pluck('id_lottery_prize')->all();
        $this->assertSame([$b->id_lottery_prize, $a->id_lottery_prize], $ids);
    }

    public function test_reorder_rejects_incomplete_and_extra_ids(): void
    {
        Sanctum::actingAs($this->manager);

        $a = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);
        $b = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => Prize::factory()->create()->id_prize,
        ]);
        $other = EditionLotteryPrize::factory()->create([
            'lottery_id' => EditionLottery::factory()->create([
                'edition_id' => $this->lottery->edition_id,
                'name' => 'Other Reorder Lottery',
            ])->id_lottery,
            'prize_id' => Prize::factory()->create()->id_prize,
        ]);

        $this->postJson('/api/v1/manager/edition-lottery-prizes/reorder', [
            'lottery_id' => $this->lottery->id_lottery,
            'lottery_prize_ids' => [$a->id_lottery_prize],
        ])->assertStatus(422);

        $this->postJson('/api/v1/manager/edition-lottery-prizes/reorder', [
            'lottery_id' => $this->lottery->id_lottery,
            'lottery_prize_ids' => [$a->id_lottery_prize, $b->id_lottery_prize, $other->id_lottery_prize],
        ])->assertStatus(422);
    }

    public function test_reorder_returns_409_when_finalized_or_has_luckies(): void
    {
        Sanctum::actingAs($this->manager);

        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);

        $this->lottery->edition->update(['finalized_at' => time()]);
        $this->postJson('/api/v1/manager/edition-lottery-prizes/reorder', [
            'lottery_id' => $this->lottery->id_lottery,
            'lottery_prize_ids' => [$line->id_lottery_prize],
        ])->assertStatus(409)->assertJson(['message' => 'Edition is finalized.']);

        $this->lottery->edition->update(['finalized_at' => null]);

        $rule = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);
        $participation = EditionParticipation::factory()->create([
            'edition_id' => $this->lottery->edition_id,
        ]);
        EditionLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
        ]);

        $this->postJson('/api/v1/manager/edition-lottery-prizes/reorder', [
            'lottery_id' => $this->lottery->id_lottery,
            'lottery_prize_ids' => [$line->id_lottery_prize],
        ])->assertStatus(409)->assertJson(['message' => 'Lottery already has luckies.']);
    }

    public function test_index_ignores_top_level_edition_division_id_without_include(): void
    {
        Sanctum::actingAs($this->manager);

        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);

        $plain = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
        ]);
        $withEd = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
            'edition_division_id' => 99,
        ]);
        $plain->assertOk();
        $withEd->assertOk();
        $this->assertSame(
            collect($plain->json('data'))->pluck('id_lottery_prize')->all(),
            collect($withEd->json('data'))->pluck('id_lottery_prize')->all()
        );
        $this->assertContains($line->id_lottery_prize, collect($withEd->json('data'))->pluck('id_lottery_prize')->all());
    }

    public function test_index_ignores_filters_edition_division_id(): void
    {
        Sanctum::actingAs($this->manager);

        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);

        $plain = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
        ]);
        $filtered = $this->prizesIndex([
            'filters' => [
                'lottery_id' => $this->lottery->id_lottery,
                'edition_division_id' => 99,
            ],
        ]);
        $plain->assertOk();
        $filtered->assertOk();
        $this->assertSame(
            collect($plain->json('data'))->pluck('id_lottery_prize')->sort()->values()->all(),
            collect($filtered->json('data'))->pluck('id_lottery_prize')->sort()->values()->all()
        );
        $this->assertContains($line->id_lottery_prize, collect($filtered->json('data'))->pluck('id_lottery_prize')->all());
    }

    public function test_include_luckies_empty_array_when_rules_have_none(): void
    {
        Sanctum::actingAs($this->manager);

        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);
        EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 2,
        ]);

        $response = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
            'include' => 'luckies',
        ]);
        $response->assertOk();
        $row = $this->lineById($response->json('data'), $line->id_lottery_prize);
        $this->assertSame([], $row['luckies']);
        $this->assertSame(2, $row['rule_prize_quantity_total']);
        $this->assertNull($row['activity_at']);
    }

    public function test_include_luckies_empty_prize_line_without_rules_still_returned(): void
    {
        Sanctum::actingAs($this->manager);

        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);

        $response = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
            'include' => 'luckies',
        ]);
        $response->assertOk();
        $row = $this->lineById($response->json('data'), $line->id_lottery_prize);
        $this->assertNotNull($row);
        $this->assertSame([], $row['luckies']);
        $this->assertSame(0, $row['rule_prize_quantity_total']);
        $this->assertNull($row['activity_at']);
    }

    public function test_include_luckies_merges_two_rules_orders_and_short_name(): void
    {
        Sanctum::actingAs($this->manager);

        $edition = Edition::query()->findOrFail($this->lottery->edition_id);
        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);
        $ruleA = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 2,
        ]);
        $ruleB = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 3,
        ]);

        $later = $this->makeLucky($edition, $ruleB, ['inserted_at' => 2000]);
        $earlier = $this->makeLucky($edition, $ruleA, [
            'first_name' => 'Ann',
            'last_name' => 'Earlier',
            'inserted_at' => 1000,
        ]);

        $response = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
            'include' => 'luckies',
        ]);
        $response->assertOk();
        $row = $this->lineById($response->json('data'), $line->id_lottery_prize);
        $this->assertCount(2, $row['luckies']);
        $this->assertSame($earlier->id_lucky, $row['luckies'][0]['id_lucky']);
        $this->assertSame($later->id_lucky, $row['luckies'][1]['id_lucky']);
        $this->assertNotNull($row['luckies'][0]['short_name']);
        $this->assertSame(5, $row['rule_prize_quantity_total']);
    }

    public function test_include_luckies_nests_phones_and_slim_lottery_rule(): void
    {
        Sanctum::actingAs($this->manager);

        $edition = Edition::query()->findOrFail($this->lottery->edition_id);
        $division = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
        ]);
        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);
        $rule = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'edition_division_id' => $division->id_edition_division,
            'rule_prize_quantity' => 1,
        ]);

        $player = Player::factory()->create();
        PlayerPhone::query()->where('player_id', $player->id_player)->delete();
        PlayerPhone::factory()->create([
            'player_id' => $player->id_player,
            'phone' => '+40111111111',
            'is_primary' => true,
        ]);
        PlayerPhone::factory()->create([
            'player_id' => $player->id_player,
            'phone' => '+40222222222',
            'is_primary' => false,
        ]);
        $participation = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $player->id_player,
        ]);
        $this->makeLucky($edition, $rule, [
            'player' => $player,
            'participation' => $participation,
        ]);

        $response = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
            'include' => 'luckies',
        ]);
        $response->assertOk();
        $row = $this->lineById($response->json('data'), $line->id_lottery_prize);
        $lucky = $row['luckies'][0];
        $phones = $lucky['drawn_participation']['player']['phones'] ?? null;
        $this->assertIsArray($phones);
        $this->assertCount(2, $phones);
        $numbers = collect($phones)->pluck('phone')->all();
        $this->assertContains('+40111111111', $numbers);
        $this->assertContains('+40222222222', $numbers);
        $this->assertSame($division->id_edition_division, $lucky['lottery_rule']['edition_division_id']);
        $this->assertArrayNotHasKey('lottery_prize', $lucky['lottery_rule']);
    }

    public function test_include_luckies_pdf_avatar_uses_collecting_points_cache(): void
    {
        Sanctum::actingAs($this->manager);

        $edition = Edition::query()->findOrFail($this->lottery->edition_id);
        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);
        $rule = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);
        $player = Player::factory()->create();
        $participation = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $player->id_player,
            'is_collecting_points' => true,
        ]);
        $this->makeLucky($edition, $rule, [
            'player' => $player,
            'participation' => $participation,
        ]);

        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $miss = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
            'include' => 'luckies',
        ]);
        $miss->assertOk();
        $this->assertNull($this->lineById($miss->json('data'), $line->id_lottery_prize)['luckies'][0]['pdf_avatar_url']);

        $relative = 'cached-pdf-player-images/1/picture/avatar.png';
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))->put($relative, 'png');
        CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => false,
            'picture_path' => 'cached-pdf-player-images/1/picture/wrong.png',
            'inserted_at' => time(),
        ]);
        $hitImage = CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => $relative,
            'inserted_at' => time(),
        ]);

        $hit = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
            'include' => 'luckies',
        ]);
        $hit->assertOk();
        $url = $this->lineById($hit->json('data'), $line->id_lottery_prize)['luckies'][0]['pdf_avatar_url'];
        $this->assertSame($hitImage->url, $url);
        $this->assertStringContainsString('cached-pdf-player-images', (string) $url);
    }

    public function test_include_luckies_activity_at_is_max_of_timestamp_fields(): void
    {
        Sanctum::actingAs($this->manager);

        $edition = Edition::query()->findOrFail($this->lottery->edition_id);
        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);
        $rule = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);
        $this->makeLucky($edition, $rule, [
            'inserted_at' => 100,
            'drawn_at' => 200,
            'available_at' => 300,
            'announced_at' => 400,
            'accepted_at' => 500,
            'rejected_at' => null,
            'updated_at' => 9000,
        ]);
        $this->makeLucky($edition, $rule, [
            'inserted_at' => 50,
            'drawn_at' => 60,
            'available_at' => 70,
            'announced_at' => null,
            'accepted_at' => null,
            'rejected_at' => 80,
            'updated_at' => 90,
        ]);

        $response = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
            'include' => 'luckies',
        ]);
        $response->assertOk();
        $row = $this->lineById($response->json('data'), $line->id_lottery_prize);
        $this->assertSame(9000, $row['activity_at']);
    }

    public function test_include_luckies_reorders_page_by_activity_then_display_order(): void
    {
        Sanctum::actingAs($this->manager);

        $edition = Edition::query()->findOrFail($this->lottery->edition_id);
        $inactive = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
            'display_order' => 0,
        ]);
        $active = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => Prize::factory()->create()->id_prize,
            'display_order' => 1,
        ]);
        $tieA = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => Prize::factory()->create()->id_prize,
            'display_order' => 3,
        ]);
        $tieB = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => Prize::factory()->create()->id_prize,
            'display_order' => 2,
        ]);
        $ruleActive = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $active->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);
        $ruleTieA = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $tieA->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);
        $ruleTieB = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $tieB->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);
        $this->makeLucky($edition, $ruleActive, ['updated_at' => 5000, 'inserted_at' => 1]);
        $this->makeLucky($edition, $ruleTieA, ['updated_at' => 4000, 'inserted_at' => 1]);
        $this->makeLucky($edition, $ruleTieB, ['updated_at' => 4000, 'inserted_at' => 1]);

        $response = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
            'include' => 'luckies',
            'sort_by' => ['display_order' => 'asc'],
            'per_page' => 100,
        ]);
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id_lottery_prize')->all();
        $this->assertSame(
            [
                $active->id_lottery_prize,
                $tieB->id_lottery_prize,
                $tieA->id_lottery_prize,
                $inactive->id_lottery_prize,
            ],
            $ids
        );
    }

    public function test_include_luckies_pending_meta_is_lottery_wide_and_keeps_pagination(): void
    {
        Sanctum::actingAs($this->manager);

        $edition = Edition::query()->findOrFail($this->lottery->edition_id);
        $divA = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);
        $divB = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);
        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);
        $ruleA = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'edition_division_id' => $divA->id_edition_division,
            'rule_prize_quantity' => 1,
        ]);
        $ruleB = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'edition_division_id' => $divB->id_edition_division,
            'rule_prize_quantity' => 1,
        ]);
        $this->makeLucky($edition, $ruleA, [
            'accepted_at' => null,
            'rejected_at' => null,
        ]);
        $this->makeLucky($edition, $ruleB, [
            'accepted_at' => 10,
            'rejected_at' => null,
        ]);

        $response = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
            'include' => 'luckies',
            'edition_division_id' => $divB->id_edition_division,
        ]);
        $response->assertOk();
        $this->assertTrue($response->json('meta.has_unresolved_pending'));
        $this->assertArrayHasKey('current_page', $response->json('meta'));
    }

    public function test_include_luckies_pending_meta_false_when_all_resolved(): void
    {
        Sanctum::actingAs($this->manager);

        $edition = Edition::query()->findOrFail($this->lottery->edition_id);
        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);
        $rule = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'rule_prize_quantity' => 1,
        ]);
        $this->makeLucky($edition, $rule, [
            'accepted_at' => 10,
            'rejected_at' => null,
        ]);
        $this->makeLucky($edition, $rule, [
            'accepted_at' => null,
            'rejected_at' => 20,
        ]);

        $response = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
            'include' => 'luckies',
        ]);
        $response->assertOk();
        $this->assertFalse($response->json('meta.has_unresolved_pending'));
        $this->assertArrayHasKey('current_page', $response->json('meta'));
    }

    public function test_include_luckies_edition_division_id_excludes_general_rules_but_keeps_lines(): void
    {
        Sanctum::actingAs($this->manager);

        $edition = Edition::query()->findOrFail($this->lottery->edition_id);
        $division = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);
        $line = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
        ]);
        $general = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'edition_division_id' => null,
            'rule_prize_quantity' => 4,
        ]);
        $scoped = EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $line->id_lottery_prize,
            'edition_division_id' => $division->id_edition_division,
            'rule_prize_quantity' => 2,
        ]);
        $generalLucky = $this->makeLucky($edition, $general);
        $scopedLucky = $this->makeLucky($edition, $scoped);

        $response = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
            'include' => 'luckies',
            'edition_division_id' => $division->id_edition_division,
        ]);
        $response->assertOk();
        $row = $this->lineById($response->json('data'), $line->id_lottery_prize);
        $this->assertNotNull($row);
        $this->assertSame(2, $row['rule_prize_quantity_total']);
        $ids = collect($row['luckies'])->pluck('id_lucky')->all();
        $this->assertContains($scopedLucky->id_lucky, $ids);
        $this->assertNotContains($generalLucky->id_lucky, $ids);
    }

    public function test_include_luckies_keeps_two_catalog_identical_prize_lines(): void
    {
        Sanctum::actingAs($this->manager);

        $first = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
            'display_order' => 0,
        ]);
        $second = EditionLotteryPrize::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'prize_id' => $this->prize->id_prize,
            'display_order' => 1,
        ]);

        $response = $this->prizesIndex([
            'filters' => ['lottery_id' => $this->lottery->id_lottery],
            'include' => 'luckies',
            'sort_by' => ['display_order' => 'asc'],
        ]);
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id_lottery_prize')->all();
        $this->assertContains($first->id_lottery_prize, $ids);
        $this->assertContains($second->id_lottery_prize, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_metadata_swagger_omits_luckies_assembly_fields(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/metadata/EditionLotteryPrize');
        $response->assertOk();
        $props = $response->json('data.swagger_schema.properties');
        $this->assertIsArray($props);
        $this->assertArrayNotHasKey('luckies', $props);
        $this->assertArrayNotHasKey('rule_prize_quantity_total', $props);
        $this->assertArrayNotHasKey('activity_at', $props);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function prizesIndex(array $query): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/v1/manager/edition-lottery-prizes?' . http_build_query($query));
    }

    /**
     * @param  null|list<array<string, mixed>>  $data
     * @return null|array<string, mixed>
     */
    private function lineById(?array $data, int $id): ?array
    {
        return collect($data ?? [])->firstWhere('id_lottery_prize', $id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLucky(Edition $edition, EditionLotteryRule $rule, array $overrides = []): EditionLotteryLucky
    {
        $player = $overrides['player'] ?? Player::factory()->create([
            'first_name' => $overrides['first_name'] ?? 'Pat',
            'last_name' => $overrides['last_name'] ?? 'Lucky',
        ]);
        unset($overrides['player'], $overrides['first_name'], $overrides['last_name']);

        $participation = $overrides['participation'] ?? EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $player->id_player,
        ]);
        unset($overrides['participation']);

        $lucky = EditionLotteryLucky::factory()->create(array_merge([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participation->id_participation,
            'drawn_at' => 10,
            'available_at' => 10,
            'accepted_at' => null,
            'rejected_at' => null,
            'announced_at' => null,
        ], $overrides));

        $timestamps = array_intersect_key($overrides, array_flip(['inserted_at', 'updated_at']));
        if ($timestamps !== []) {
            EditionLotteryLucky::query()->whereKey($lucky->id_lucky)->update($timestamps);
            $lucky->refresh();
        }

        return $lucky;
    }
}
