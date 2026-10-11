<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\EventDivision;
use App\Models\EventLottery;
use App\Models\EventLotteryPrize;
use App\Models\EventLotteryRule;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\Prize;
use App\Services\EventCalendarTiming;
use App\Services\Lottery\LotteryDrawService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

final class LotteryPlayingWindowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_compute_next_available_includes_overnight_active_edition_division(): void
    {
        $tz = EventCalendarTiming::timezone();
        $starts = Carbon::parse('2026-08-14 18:00:00', $tz)->timestamp;
        $ends = Carbon::parse('2026-08-15 00:00:00', $tz)->timestamp;

        $event = PadelEvent::factory()->create([
            'event_starts_at' => Carbon::parse('2026-08-14 12:00:00', $tz)->timestamp,
            'event_ends_at' => $ends,
        ]);

        $overnight = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'playing_starts_at' => $starts,
            'playing_ends_at' => $ends,
        ]);

        $participation = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $overnight->id_event_division,
            'player_user_id' => User::factory()->create()->id_user,
        ]);
        $participation->setRelation('eventDivision', $overnight);

        $now = Carbon::parse('2026-08-14 19:00:00', $tz)->timestamp;
        $this->assertTrue($now >= $starts && $now < $ends);

        $service = new LotteryDrawService();
        $method = new ReflectionMethod(LotteryDrawService::class, 'computeNextAvailableAt');
        $method->setAccessible(true);

        $ts = $method->invoke($service, $event, $participation, $now);
        $this->assertIsInt($ts);
        $this->assertGreaterThan(0, $ts);
    }

    public function test_format_interval_label_uses_bucharest_clocks_from_unix(): void
    {
        $tz = EventCalendarTiming::timezone();
        $service = new LotteryDrawService();
        $method = new ReflectionMethod(LotteryDrawService::class, 'formatIntervalLabel');
        $method->setAccessible(true);

        $overnight = $method->invoke(
            $service,
            Carbon::parse('2026-08-14 18:00:00', $tz)->timestamp,
            Carbon::parse('2026-08-15 00:00:00', $tz)->timestamp,
        );
        $this->assertSame('18:00 – 00:00', $overnight);

        $sameDay = $method->invoke(
            $service,
            Carbon::parse('2026-08-14 10:00:00', $tz)->timestamp,
            Carbon::parse('2026-08-14 13:00:00', $tz)->timestamp,
        );
        $this->assertSame('10:00 – 13:00', $sameDay);
    }

    public function test_prizes_board_merges_identical_overnight_windows(): void
    {
        $tz = EventCalendarTiming::timezone();
        $dayStart = Carbon::parse(now($tz)->toDateString() . ' 12:00:00', $tz)->timestamp;
        $overnightStart = Carbon::parse(now($tz)->toDateString() . ' 18:00:00', $tz)->timestamp;
        $overnightEnd = Carbon::parse(now($tz)->toDateString() . ' 18:00:00', $tz)->addDay()->startOfDay()->timestamp;
        $morningStart = Carbon::parse(now($tz)->toDateString() . ' 10:00:00', $tz)->timestamp;
        $morningEnd = Carbon::parse(now($tz)->toDateString() . ' 13:00:00', $tz)->timestamp;

        $event = PadelEvent::factory()->create([
            'event_starts_at' => $dayStart,
            'event_ends_at' => $overnightEnd,
        ]);
        $divA = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'playing_starts_at' => $overnightStart,
            'playing_ends_at' => $overnightEnd,
        ]);
        $divB = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'playing_starts_at' => $overnightStart,
            'playing_ends_at' => $overnightEnd,
        ]);
        $divOther = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'playing_starts_at' => $morningStart,
            'playing_ends_at' => $morningEnd,
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
