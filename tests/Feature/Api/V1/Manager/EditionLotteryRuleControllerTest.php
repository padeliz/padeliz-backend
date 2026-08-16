<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Edition;
use App\Models\EditionLottery;
use App\Models\EditionLotteryLucky;
use App\Models\EditionLotteryRule;
use App\Models\EditionParticipation;
use App\Models\Player;
use App\Models\PlayerPhone;
use App\Models\Prize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditionLotteryRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_index_and_show_include_lottery_prize_name_without_rules_cycle(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create();
        $prize = Prize::factory()->create(['name' => 'Towel']);
        $lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'prize_id' => $prize->id_prize,
            'prize_quantity' => 2,
        ]);
        $rule = EditionLotteryRule::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'rule_prize_quantity' => 2,
        ]);

        $index = $this->getJson('/api/v1/manager/edition-lottery-rules');
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lottery_rule', $rule->id_lottery_rule);
        $this->assertNotNull($row);
        $this->assertSame('Towel', $row['lottery']['prize']['name'] ?? null);
        $this->assertArrayNotHasKey('rules', $row['lottery'] ?? []);
        $this->assertArrayNotHasKey('luckies', $row);

        $show = $this->getJson('/api/v1/manager/edition-lottery-rules/' . $rule->id_lottery_rule);
        $show->assertOk();
        $this->assertSame('Towel', $show->json('data.lottery.prize.name'));
        $this->assertArrayNotHasKey('rules', $show->json('data.lottery') ?? []);
    }

    public function test_rule_index_with_luckies_returns_empty_array_when_none(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create();
        $prize = Prize::factory()->create(['name' => 'Towel']);
        $lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'prize_id' => $prize->id_prize,
        ]);
        $rule = EditionLotteryRule::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'rule_prize_quantity' => 1,
        ]);

        $index = $this->getJson('/api/v1/manager/edition-lottery-rules?with_luckies=1');
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lottery_rule', $rule->id_lottery_rule);
        $this->assertNotNull($row);
        $this->assertSame('Towel', $row['lottery']['prize']['name'] ?? null);
        $this->assertArrayHasKey('luckies', $row);
        $this->assertSame([], $row['luckies']);
    }

    public function test_rule_index_with_luckies_orders_asc_and_includes_short_name(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create();
        $prize = Prize::factory()->create(['name' => 'Towel']);
        $lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'prize_id' => $prize->id_prize,
        ]);
        $rule = EditionLotteryRule::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'rule_prize_quantity' => 2,
        ]);

        $playerLater = Player::factory()->create([
            'first_name' => 'Zoe',
            'last_name' => 'Later',
        ]);
        $playerEarlier = Player::factory()->create([
            'first_name' => 'Ann',
            'last_name' => 'Earlier',
        ]);
        $participationLater = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $playerLater->id_player,
        ]);
        $participationEarlier = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $playerEarlier->id_player,
        ]);

        $later = EditionLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participationLater->id_participation,
            'inserted_at' => 2000,
            'accepted_at' => null,
            'rejected_at' => null,
        ]);
        $earlier = EditionLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participationEarlier->id_participation,
            'inserted_at' => 1000,
            'accepted_at' => null,
            'rejected_at' => null,
        ]);

        $index = $this->getJson('/api/v1/manager/edition-lottery-rules?with_luckies=1');
        $index->assertOk();
        $row = collect($index->json('data'))->firstWhere('id_lottery_rule', $rule->id_lottery_rule);
        $this->assertNotNull($row);
        $this->assertCount(2, $row['luckies']);
        $this->assertSame($earlier->id_lucky, $row['luckies'][0]['id_lucky']);
        $this->assertSame($later->id_lucky, $row['luckies'][1]['id_lucky']);
        $this->assertNotNull($row['luckies'][0]['short_name']);
        $this->assertNotNull($row['luckies'][1]['short_name']);
        $this->assertArrayHasKey('drawn_participation', $row['luckies'][0]);
    }

    public function test_rule_show_nests_luckies_ordered_with_participation(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create();
        $prize = Prize::factory()->create(['name' => 'Towel']);
        $lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'prize_id' => $prize->id_prize,
        ]);
        $rule = EditionLotteryRule::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'rule_prize_quantity' => 2,
        ]);

        $playerA = Player::factory()->create([
            'first_name' => 'Player',
            'last_name' => 'A',
        ]);
        $playerB = Player::factory()->create([
            'first_name' => 'Player',
            'last_name' => 'B',
        ]);
        $participationA = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $playerA->id_player,
        ]);
        $participationB = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $playerB->id_player,
        ]);

        $second = EditionLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participationB->id_participation,
            'inserted_at' => 3000,
            'accepted_at' => null,
            'rejected_at' => null,
        ]);
        $first = EditionLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
            'drawn_participation_id' => $participationA->id_participation,
            'inserted_at' => 1000,
            'accepted_at' => null,
            'rejected_at' => null,
        ]);

        $show = $this->getJson('/api/v1/manager/edition-lottery-rules/' . $rule->id_lottery_rule);
        $show->assertOk();
        $luckies = $show->json('data.luckies');
        $this->assertIsArray($luckies);
        $this->assertCount(2, $luckies);
        $this->assertSame($first->id_lucky, $luckies[0]['id_lucky']);
        $this->assertSame($second->id_lucky, $luckies[1]['id_lucky']);
        $this->assertNotNull($luckies[0]['short_name']);
        $this->assertArrayHasKey('drawn_participation', $luckies[0]);
    }

    public function test_rule_index_with_luckies_includes_player_phones(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create();
        $prize = Prize::factory()->create(['name' => 'Towel']);
        $lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'prize_id' => $prize->id_prize,
        ]);
        $rule = EditionLotteryRule::factory()->create([
            'lottery_id' => $lottery->id_lottery,
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
        $phones = $row['luckies'][0]['drawn_participation']['player']['phones'] ?? null;
        $this->assertIsArray($phones);
        $this->assertCount(2, $phones);
        $numbers = collect($phones)->pluck('phone')->all();
        $this->assertContains('+40111111111', $numbers);
        $this->assertContains('+40222222222', $numbers);
    }
}
