<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\EventDivision;
use App\Models\EventLottery;
use App\Models\EventLotteryPrize;
use App\Models\EventLotteryRule;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
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
        $event = PadelEvent::factory()->create([
            'date' => '2026-08-14',
        ]);

        $overnight = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'playing_start_time' => '18:00:00',
            'playing_end_time' => '00:00:00',
        ]);

        $participation = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $overnight->id_event_division,
            'player_id' => Player::factory()->create()->id_player,
        ]);
        $participation->setRelation('eventDivision', $overnight);

        $this->assertTrue(PlayingWindow::isActiveAt('18:00:00', '00:00:00', '19:00:00', '2026-08-14'));

        $service = new LotteryDrawService();
        $method = new ReflectionMethod(LotteryDrawService::class, 'computeNextAvailableAt');
        $method->setAccessible(true);

        $ts = $method->invoke($service, $event, $participation, '19:00:00');
        $this->assertIsInt($ts);
        $this->assertGreaterThan(0, $ts);
    }

    public function test_format_interval_label_uses_real_clocks(): void
    {
        $service = new LotteryDrawService();
        $method = new ReflectionMethod(LotteryDrawService::class, 'formatIntervalLabel');
        $method->setAccessible(true);

        $overnight = $method->invoke($service, '18:00:00', '00:00:00');
        $this->assertSame('18:00 – 00:00', $overnight);

        $sameDay = $method->invoke($service, '10:00:00', '13:00:00');
        $this->assertSame('10:00 – 13:00', $sameDay);
    }

    public function test_prizes_board_merges_identical_overnight_windows(): void
    {
        $event = PadelEvent::factory()->create(['date' => now()->toDateString()]);
        $divA = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'playing_start_time' => '18:00:00',
            'playing_end_time' => '00:00:00',
        ]);
        $divB = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'playing_start_time' => '18:00:00',
            'playing_end_time' => '00:00:00',
        ]);
        $divOther = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'playing_start_time' => '10:00:00',
            'playing_end_time' => '13:00:00',
        ]);

        $prize = Prize::factory()->create();
        $lottery = EventLottery::factory()->create([
            'event_id' => $event->id_event,
            'name' => 'Playing Window Lottery',
        ]);
        $lotteryPrize = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_id' => $prize->id_prize,
            'prize_quantity' => 3,
        ]);
        EventLotteryRule::factory()->create([
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'event_division_id' => $divA->id_event_division,
            'rule_prize_quantity' => 1,
        ]);
        EventLotteryRule::factory()->create([
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'event_division_id' => $divB->id_event_division,
            'rule_prize_quantity' => 1,
        ]);
        EventLotteryRule::factory()->create([
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'event_division_id' => $divOther->id_event_division,
            'rule_prize_quantity' => 1,
        ]);
        EventLotteryRule::factory()->create([
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'event_division_id' => null,
            'rule_prize_quantity' => 1,
        ]);

        $board = (new LotteryDrawService())->prizesBoard($lottery->fresh());
        $labels = array_map(static fn (array $i): string => $i['label'], $board['intervals']);
        $this->assertContains('18:00 – 00:00', $labels);
        $this->assertNotContains('18:00 – 24:00', $labels);
        $this->assertContains('10:00 – 13:00', $labels);
        $this->assertContains('Unscheduled', $labels);
        $this->assertCount(3, $board['intervals']);
    }
}
