<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\CachedPdfPlayerImage;
use App\Models\Club;
use App\Models\Court;
use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\EventDivisionReplacement;
use App\Models\EventLottery;
use App\Models\EventLotteryLucky;
use App\Models\EventLotteryPrize;
use App\Models\EventLotteryRule;
use App\Models\EventParticipation;
use App\Models\GameMatch;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\PlayerProfileImage;
use App\Models\Prize;
use App\Models\Sponsor;
use App\Models\SponsorPrize;
use App\Services\Lottery\LotteryDrawService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class EventLotteryLiveControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: EventLottery, 1: EventLotteryPrize, 2: EventLotteryRule}
     */
    private function createLotteryWithPrizeLineAndRule(
        PadelEvent $event,
        Prize $prize,
        ?EventDivision $division = null,
        int $prizeQuantity = 1,
        ?int $sponsorId = null,
    ): array {
        $lottery = EventLottery::factory()->create([
            'event_id' => $event->id_event,
            'name' => 'Live Lottery ' . uniqid(),
        ]);
        $lotteryPrize = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_id' => $prize->id_prize,
            'prize_quantity' => $prizeQuantity,
            'sponsor_id' => $sponsorId,
        ]);
        $rule = EventLotteryRule::factory()->create([
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
            'event_division_id' => $division?->id_event_division,
            'rule_prize_quantity' => $prizeQuantity,
            'orderliness' => 0,
        ]);

        return [$lottery, $lotteryPrize, $rule];
    }

    public function test_live_draw_skips_division_wide_replaced_and_absent(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
        ]);

        $absentPlayer = User::factory()->create();
        $replacedPlayer = User::factory()->create();
        $okPlayer = User::factory()->create();

        $absent = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'player_user_id' => $absentPlayer->id_user,
            'absence_reason' => 'SICK',
        ]);
        $replaced = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'player_user_id' => $replacedPlayer->id_user,
            'absence_reason' => null,
        ]);
        $ok = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'player_user_id' => $okPlayer->id_user,
            'absence_reason' => null,
        ]);

        EventDivisionReplacement::query()->create([
            'event_division_id' => $division->id_event_division,
            'original_participation_id' => $replaced->id_participation,
            'substitute_player_user_id' => User::factory()->create()->id_user,
            'match_id' => null,
            'inserted_at' => time(),
        ]);

        $prize = Prize::factory()->create([
            'box_1_text' => 'Next: {{edition.next.name}}',
            'box_2_text' => '{{edition.next.date.short}}',
        ]);
        [$lottery] = $this->createLotteryWithPrizeLineAndRule($event, $prize, $division);

        $response = $this->getJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}/live");
        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.lucky.player.id_user', $ok->player_user_id);

        $this->assertNotEquals($absent->player_user_id, $response->json('data.lucky.player.id_user'));
        $this->assertNotEquals($replaced->player_user_id, $response->json('data.lucky.player.id_user'));
    }

    public function test_prizes_board_keeps_placeholders_when_no_next_edition(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        // Past edition with no later Upcoming → placeholders stay literal.
        $tz = (string) config('app.timezone', 'Europe/Bucharest');
        $startsAt = Carbon::now($tz)->subDays(14)->setTime(12, 0)->timestamp;
        $event = PadelEvent::factory()->create([
            'event_starts_at' => $startsAt,
            'event_ends_at' => $startsAt + (4 * 3600),
        ]);
        $prize = Prize::factory()->create([
            'box_1_text' => 'Hello {{edition.next.name}}',
            'box_2_text' => 'On {{edition.next.date.short}}',
            'media_path' => 'prizes/1/media/demo.mp4',
        ]);
        [$lottery] = $this->createLotteryWithPrizeLineAndRule($event, $prize);

        $response = $this->getJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}/prizes-board");
        $response->assertStatus(200);

        $prizePayload = $response->json('data.intervals.0.prizes.0.prize');
        $this->assertSame('Hello {{edition.next.name}}', $prizePayload['box_1_text']);
        $this->assertSame('On {{edition.next.date.short}}', $prizePayload['box_2_text']);
        $this->assertTrue($prizePayload['is_video']);
    }

    public function test_eligible_query_excludes_replaced(): void
    {
        $event = PadelEvent::factory()->create();
        $division = EventDivision::factory()->create(['event_id' => $event->id_event]);
        $ok = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'absence_reason' => null,
        ]);
        $replaced = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'absence_reason' => null,
        ]);
        EventDivisionReplacement::query()->create([
            'event_division_id' => $division->id_event_division,
            'original_participation_id' => $replaced->id_participation,
            'match_id' => null,
            'inserted_at' => time(),
        ]);

        $ids = (new LotteryDrawService())
            ->eligibleParticipationsQuery((int) $event->id_event)
            ->pluck('id_participation')
            ->all();

        $this->assertContains((int) $ok->id_participation, $ids);
        $this->assertNotContains((int) $replaced->id_participation, $ids);
    }

    public function test_eligible_query_keeps_match_scoped_replaced(): void
    {
        $event = PadelEvent::factory()->create();
        $division = EventDivision::factory()->create(['event_id' => $event->id_event]);
        $ok = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'absence_reason' => null,
        ]);
        $replacedOnOneMatch = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'absence_reason' => null,
        ]);
        $court = Court::factory()->create();
        $edCourt = EventDivisionCourt::factory()->create([
            'event_division_id' => $division->id_event_division,
            'court_id' => $court->id_court,
            'display_order' => 0,
        ]);
        $match = GameMatch::create([
            'event_division_id' => $division->id_event_division,
            'event_division_court_id' => $edCourt->id_event_division_court,
            'match_kind' => 'REGULAR',
            'sequence_no' => 1,
            'scheduled_start' => '14:00:00',
            'scheduled_end' => '14:30:00',
            'points_target' => 21,
        ]);
        EventDivisionReplacement::query()->create([
            'event_division_id' => $division->id_event_division,
            'original_participation_id' => $replacedOnOneMatch->id_participation,
            'match_id' => $match->id_match,
            'inserted_at' => time(),
        ]);

        $ids = (new LotteryDrawService())
            ->eligibleParticipationsQuery((int) $event->id_event)
            ->pluck('id_participation')
            ->all();

        $this->assertContains((int) $ok->id_participation, $ids);
        $this->assertContains((int) $replacedOnOneMatch->id_participation, $ids);
    }

    public function test_live_avatar_url_uses_pdf_cache_not_profile_image(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
        ]);
        $player = User::factory()->create();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        PlayerProfileImage::query()->create([
            'player_user_id' => $player->id_user,
            'profile_path' => 'player-profile-images/' . $player->id_user . '/profile/x.png',
            'sort_order' => 0,
            'inserted_at' => time(),
        ]);

        $participation = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'player_user_id' => $player->id_user,
            'is_collecting_points' => true,
            'absence_reason' => null,
        ]);

        $prize = Prize::factory()->create();
        [$lottery] = $this->createLotteryWithPrizeLineAndRule($event, $prize, $division);

        $miss = $this->getJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}/live");
        $miss->assertOk()->assertJsonPath('data.lucky.player.avatar_url', null);

        $relative = 'cached-pdf-player-images/1/picture/avatar.png';
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))->put($relative, 'png');
        CachedPdfPlayerImage::query()->create([
            'player_user_id' => $player->id_user,
            'player_is_collecting_points' => true,
            'picture_path' => $relative,
            'inserted_at' => time(),
        ]);

        $hit = $this->getJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}/live");
        $hit->assertOk();
        $this->assertNotNull($hit->json('data.lucky.player.avatar_url'));
        $this->assertStringContainsString('cached-pdf-player-images', (string) $hit->json('data.lucky.player.avatar_url'));
        $this->assertSame($participation->player_user_id, $hit->json('data.lucky.player.id_user'));
    }

    public function test_two_lotteries_have_independent_drawn_queues(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
        ]);
        $onlyPlayer = User::factory()->create();
        $participation = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'player_user_id' => $onlyPlayer->id_user,
            'absence_reason' => null,
        ]);

        $prizeA = Prize::factory()->create(['name' => 'Prize A']);
        $prizeB = Prize::factory()->create(['name' => 'Prize B']);
        [$lotteryA] = $this->createLotteryWithPrizeLineAndRule($event, $prizeA, $division);
        [$lotteryB] = $this->createLotteryWithPrizeLineAndRule($event, $prizeB, $division);

        $liveA = $this->getJson("/api/v1/manager/event-lotteries/{$lotteryA->id_lottery}/live");
        $liveA->assertOk()
            ->assertJsonPath('data.lucky.player.id_user', $participation->player_user_id);

        $this->postJson("/api/v1/manager/event-lotteries/{$lotteryA->id_lottery}/accept", [
            'id_lucky' => $liveA->json('data.lucky.id_lucky'),
        ])->assertOk();

        $liveB = $this->getJson("/api/v1/manager/event-lotteries/{$lotteryB->id_lottery}/live");
        $liveB->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.lucky.player.id_user', $participation->player_user_id);
    }

    public function test_already_drawn_is_scoped_per_lottery(): void
    {
        $event = PadelEvent::factory()->create();
        $division = EventDivision::factory()->create(['event_id' => $event->id_event]);
        $p1 = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'absence_reason' => null,
        ]);
        $p2 = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'absence_reason' => null,
        ]);

        $prize = Prize::factory()->create();
        [$lotteryA, , $ruleA] = $this->createLotteryWithPrizeLineAndRule($event, $prize, $division);
        [$lotteryB] = $this->createLotteryWithPrizeLineAndRule($event, $prize, $division);

        EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $ruleA->id_lottery_rule,
            'drawn_participation_id' => $p1->id_participation,
            'accepted_at' => time(),
        ]);

        $service = new LotteryDrawService();
        $idsA = $service->eligibleParticipationsQuery((int) $event->id_event, null, null, (int) $lotteryA->id_lottery)
            ->pluck('id_participation')
            ->all();
        $idsB = $service->eligibleParticipationsQuery((int) $event->id_event, null, null, (int) $lotteryB->id_lottery)
            ->pluck('id_participation')
            ->all();

        $this->assertNotContains((int) $p1->id_participation, $idsA);
        $this->assertContains((int) $p2->id_participation, $idsA);
        $this->assertContains((int) $p1->id_participation, $idsB);
        $this->assertContains((int) $p2->id_participation, $idsB);
    }

    public function test_prize_index_increments_per_prize_line(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        // Upcoming edition: accept must still draw the next lucky for the same
        // prize line (gate by division playing_starts_at, not wall-clock now).
        $tz = (string) config('app.timezone', 'Europe/Bucharest');
        $startsAt = Carbon::now($tz)->addDays(7)->setTime(12, 0)->timestamp;
        $endsAt = $startsAt + (4 * 3600);
        $event = PadelEvent::factory()->create([
            'event_starts_at' => $startsAt,
            'event_ends_at' => $endsAt,
        ]);
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'playing_starts_at' => $startsAt,
            'playing_ends_at' => $endsAt,
        ]);
        $players = User::factory()->count(2)->create();
        foreach ($players as $player) {
            EventParticipation::factory()->create([
                'event_id' => $event->id_event,
                'event_division_id' => $division->id_event_division,
                'player_user_id' => $player->id_user,
                'absence_reason' => null,
            ]);
        }

        $prize = Prize::factory()->create();
        [$lottery] = $this->createLotteryWithPrizeLineAndRule($event, $prize, $division, 2);

        $first = $this->getJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}/live");
        $first->assertOk()->assertJsonPath('data.lucky.prize_index', 1);

        $this->postJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}/accept", [
            'id_lucky' => $first->json('data.lucky.id_lucky'),
        ])->assertOk();

        $second = $this->getJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}/live");
        $second->assertOk()->assertJsonPath('data.lucky.prize_index', 2);
    }

    public function test_organizer_prize_line_has_no_host_club_overlay(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
        ]);
        EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'absence_reason' => null,
        ]);

        $club = Club::factory()->create();
        Sponsor::factory()->forClub($club)->create();

        $prize = Prize::factory()->create([
            'box_1_text' => 'Catalog text',
            'box_1_bg_color' => '#111111',
        ]);
        [$lottery] = $this->createLotteryWithPrizeLineAndRule($event, $prize, $division, 1, null);

        $response = $this->getJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}/live");
        $response->assertOk();
        $prizePayload = $response->json('data.lucky.prize');
        $this->assertSame('Catalog text', $prizePayload['box_1_text']);
        $this->assertSame('#111111', $prizePayload['box_1_bg_color']);
        $this->assertArrayNotHasKey('sponsor', $prizePayload);
        $this->assertArrayNotHasKey('club', $prizePayload);
        $this->assertArrayNotHasKey('players', $prizePayload);
    }

    public function test_sponsor_overlay_merges_non_null_and_includes_sponsor_name_only(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
        ]);
        EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'absence_reason' => null,
        ]);

        $sponsor = Sponsor::factory()->create(['name' => 'Acme Sponsor']);

        $prize = Prize::factory()->create([
            'box_1_text' => 'Catalog box 1',
            'box_2_text' => 'Catalog box 2',
            'box_1_bg_color' => '#AAAAAA',
            'box_2_bg_color' => '#BBBBBB',
            'template' => 'IMAGE_LEFT',
        ]);
        SponsorPrize::factory()->create([
            'sponsor_id' => $sponsor->id_sponsor,
            'prize_id' => $prize->id_prize,
            'box_1_text' => 'Overlay box 1',
            'box_2_text' => null,
            'box_1_bg_color' => '#FF0000',
            'box_2_bg_color' => null,
            'template' => null,
        ]);

        [$lottery] = $this->createLotteryWithPrizeLineAndRule(
            $event,
            $prize,
            $division,
            1,
            (int) $sponsor->id_sponsor
        );

        $response = $this->getJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}/live");
        $response->assertOk();
        $prizePayload = $response->json('data.lucky.prize');

        $this->assertSame('Overlay box 1', $prizePayload['box_1_text']);
        $this->assertSame('Catalog box 2', $prizePayload['box_2_text']);
        $this->assertSame('#FF0000', $prizePayload['box_1_bg_color']);
        $this->assertSame('#BBBBBB', $prizePayload['box_2_bg_color']);
        $this->assertSame('IMAGE_LEFT', $prizePayload['template']);
        $this->assertSame($sponsor->id_sponsor, $prizePayload['sponsor']['id_sponsor'] ?? null);
        $this->assertSame('Acme Sponsor', $prizePayload['sponsor']['name'] ?? null);
        $this->assertArrayNotHasKey('club', $prizePayload['sponsor'] ?? []);
        $this->assertArrayNotHasKey('players', $prizePayload['sponsor'] ?? []);
        $this->assertArrayNotHasKey('club', $prizePayload);
        $this->assertArrayNotHasKey('players', $prizePayload);
    }

    public function test_live_draw_ignores_prize_line_display_order(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
        ]);
        EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'absence_reason' => null,
        ]);

        $lottery = EventLottery::factory()->create([
            'event_id' => $event->id_event,
        ]);
        $firstLine = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'display_order' => 0,
            'prize_quantity' => 1,
        ]);
        $laterLine = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'display_order' => 9,
            'prize_quantity' => 1,
        ]);
        EventLotteryRule::factory()->create([
            'lottery_prize_id' => $firstLine->id_lottery_prize,
            'event_division_id' => $division->id_event_division,
            'rule_prize_quantity' => 1,
            'orderliness' => 10,
        ]);
        $winningRule = EventLotteryRule::factory()->create([
            'lottery_prize_id' => $laterLine->id_lottery_prize,
            'event_division_id' => $division->id_event_division,
            'rule_prize_quantity' => 1,
            'orderliness' => 0,
        ]);

        $response = $this->getJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}/live");
        $response->assertOk()
            ->assertJsonPath('data.lucky.lottery_rule_id', $winningRule->id_lottery_rule);
    }

    public function test_live_draw_tie_break_uses_lower_rule_id(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $event = PadelEvent::factory()->create();
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
        ]);
        EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'absence_reason' => null,
        ]);

        $lottery = EventLottery::factory()->create([
            'event_id' => $event->id_event,
        ]);
        $firstLine = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'display_order' => 0,
            'prize_quantity' => 1,
        ]);
        $secondLine = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'display_order' => 0,
            'prize_quantity' => 1,
        ]);
        $winningRule = EventLotteryRule::factory()->create([
            'lottery_prize_id' => $secondLine->id_lottery_prize,
            'event_division_id' => $division->id_event_division,
            'rule_prize_quantity' => 1,
            'orderliness' => 0,
        ]);
        EventLotteryRule::factory()->create([
            'lottery_prize_id' => $firstLine->id_lottery_prize,
            'event_division_id' => $division->id_event_division,
            'rule_prize_quantity' => 1,
            'orderliness' => 0,
        ]);

        $response = $this->getJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}/live");
        $response->assertOk()
            ->assertJsonPath('data.lucky.lottery_rule_id', $winningRule->id_lottery_rule);
    }
}
