<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionLottery;
use App\Models\EditionLotteryPrize;
use App\Models\EditionLotteryRule;
use App\Models\EditionParticipation;
use App\Models\Player;
use App\Models\Prize;
use App\Services\Lottery\LotteryDrawService;
use App\Services\Schedule\PlayingWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

final class LotteryPlayingWindowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_compute_next_available_includes_overnight_active_edition_division(): void
    {
        $edition = Edition::factory()->create([
            'date' => '2026-08-14',
        ]);

        $overnight = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'playing_start_time' => '18:00:00',
            'playing_end_time' => '00:00:00',
        ]);

        $participation = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $overnight->id_edition_division,
            'player_id' => Player::factory()->create()->id_player,
        ]);
        $participation->setRelation('editionDivision', $overnight);

        $this->assertTrue(PlayingWindow::isActiveAt('18:00:00', '00:00:00', '19:00:00', '2026-08-14'));

        $service = new LotteryDrawService();
        $method = new ReflectionMethod(LotteryDrawService::class, 'computeNextAvailableAt');
        $method->setAccessible(true);

        $ts = $method->invoke($service, $edition, $participation, '19:00:00');
        $this->assertIsInt($ts);
        $this->assertGreaterThan(0, $ts);
    }

    public function test_format_interval_label_handles_24_00(): void
    {
        $service = new LotteryDrawService();
        $method = new ReflectionMethod(LotteryDrawService::class, 'formatIntervalLabel');
        $method->setAccessible(true);

        $label = $method->invoke($service, '18:00:00', '24:00:00');
        $this->assertSame('18:00 – 24:00', $label);

        $overnight = $method->invoke($service, '18:00:00', '00:00:00');
        $this->assertSame('18:00 – 24:00', $overnight);
    }

    public function test_prizes_board_merges_24_00_and_overnight_00_00_ends(): void
    {
        $edition = Edition::factory()->create(['date' => now()->toDateString()]);
        $div24 = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'playing_start_time' => '18:00:00',
            'playing_end_time' => '24:00:00',
        ]);
        $div00 = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'playing_start_time' => '18:00:00',
            'playing_end_time' => '00:00:00',
        ]);
        $divOther = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'playing_start_time' => '10:00:00',
            'playing_end_time' => '13:00:00',
        ]);

        $prize = Prize::factory()->create();
        $lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'name' => 'Playing Window Lottery',
        ]);
        $lotteryPrize = EditionLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_id' => $prize->id_prize,
            'prize_quantity' => 3,
        ]);
        EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'edition_division_id' => $div24->id_edition_division,
            'rule_prize_quantity' => 1,
        ]);
        EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'edition_division_id' => $div00->id_edition_division,
            'rule_prize_quantity' => 1,
        ]);
        EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'edition_division_id' => $divOther->id_edition_division,
            'rule_prize_quantity' => 1,
        ]);
        EditionLotteryRule::factory()->create([
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'edition_division_id' => null,
            'rule_prize_quantity' => 1,
        ]);

        $board = (new LotteryDrawService())->prizesBoard($lottery->fresh());
        $labels = array_map(static fn (array $i): string => $i['label'], $board['intervals']);
        $this->assertContains('18:00 – 24:00', $labels);
        $this->assertNotContains('18:00 – 00:00', $labels);
        $this->assertContains('10:00 – 13:00', $labels);
        $this->assertContains('Unscheduled', $labels);
        $this->assertCount(3, $board['intervals']);
    }
}
