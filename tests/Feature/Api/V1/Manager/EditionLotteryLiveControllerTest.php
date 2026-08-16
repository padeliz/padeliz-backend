<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\CachedPdfPlayerImage;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionReplacement;
use App\Models\EditionLottery;
use App\Models\EditionLotteryRule;
use App\Models\EditionParticipation;
use App\Models\Player;
use App\Models\PlayerProfileImage;
use App\Models\Prize;
use App\Services\Lottery\LotteryDrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class EditionLotteryLiveControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_draw_skips_division_wide_replaced_and_absent(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create(['date' => now()->toDateString()]);
        $division = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'playing_start_time' => '10:00:00',
            'playing_end_time' => '13:00:00',
        ]);

        $absentPlayer = Player::factory()->create();
        $replacedPlayer = Player::factory()->create();
        $okPlayer = Player::factory()->create();

        $absent = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $division->id_edition_division,
            'player_id' => $absentPlayer->id_player,
            'absence_reason' => 'SICK',
        ]);
        $replaced = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $division->id_edition_division,
            'player_id' => $replacedPlayer->id_player,
            'absence_reason' => null,
        ]);
        $ok = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $division->id_edition_division,
            'player_id' => $okPlayer->id_player,
            'absence_reason' => null,
        ]);

        EditionDivisionReplacement::query()->create([
            'edition_division_id' => $division->id_edition_division,
            'original_participation_id' => $replaced->id_participation,
            'substitute_player_id' => Player::factory()->create()->id_player,
            'match_id' => null,
            'inserted_at' => time(),
        ]);

        $prize = Prize::factory()->create([
            'box_1_text' => 'Next: {{edition.next.name}}',
            'box_2_text' => '{{edition.next.date.short}}',
        ]);
        $lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'prize_id' => $prize->id_prize,
            'prize_quantity' => 1,
        ]);
        EditionLotteryRule::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'edition_division_id' => $division->id_edition_division,
            'rule_prize_quantity' => 1,
            'orderliness' => 0,
        ]);

        $response = $this->getJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}/live");
        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.lucky.player.id_player', $ok->player_id);

        $this->assertNotEquals($absent->player_id, $response->json('data.lucky.player.id_player'));
        $this->assertNotEquals($replaced->player_id, $response->json('data.lucky.player.id_player'));
    }

    public function test_prizes_board_keeps_placeholders_when_no_next_edition(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create(['date' => now()->toDateString()]);
        $prize = Prize::factory()->create([
            'box_1_text' => 'Hello {{edition.next.name}}',
            'box_2_text' => 'On {{edition.next.date.short}}',
            'media_path' => 'prizes/1/media/demo.mp4',
        ]);
        $lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'prize_id' => $prize->id_prize,
            'prize_quantity' => 1,
        ]);
        EditionLotteryRule::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'edition_division_id' => null,
            'rule_prize_quantity' => 1,
        ]);

        $response = $this->getJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}/prizes-board");
        $response->assertStatus(200);

        $prizePayload = $response->json('data.intervals.0.prizes.0.prize');
        $this->assertSame('Hello {{edition.next.name}}', $prizePayload['box_1_text']);
        $this->assertSame('On {{edition.next.date.short}}', $prizePayload['box_2_text']);
        $this->assertTrue($prizePayload['is_video']);
    }

    public function test_eligible_query_excludes_replaced(): void
    {
        $edition = Edition::factory()->create();
        $division = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);
        $ok = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $division->id_edition_division,
            'absence_reason' => null,
        ]);
        $replaced = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $division->id_edition_division,
            'absence_reason' => null,
        ]);
        EditionDivisionReplacement::query()->create([
            'edition_division_id' => $division->id_edition_division,
            'original_participation_id' => $replaced->id_participation,
            'match_id' => null,
            'inserted_at' => time(),
        ]);

        $ids = (new LotteryDrawService())
            ->eligibleParticipationsQuery((int) $edition->id_edition)
            ->pluck('id_participation')
            ->all();

        $this->assertContains((int) $ok->id_participation, $ids);
        $this->assertNotContains((int) $replaced->id_participation, $ids);
    }

    public function test_live_avatar_url_uses_pdf_cache_not_profile_image(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create(['date' => now()->toDateString()]);
        $division = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'playing_start_time' => '10:00:00',
            'playing_end_time' => '13:00:00',
        ]);
        $player = Player::factory()->create();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        PlayerProfileImage::query()->create([
            'player_id' => $player->id_player,
            'profile_path' => 'player-profile-images/' . $player->id_player . '/profile/x.png',
            'sort_order' => 0,
            'inserted_at' => time(),
        ]);

        $participation = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $division->id_edition_division,
            'player_id' => $player->id_player,
            'is_collecting_points' => true,
            'absence_reason' => null,
        ]);

        $prize = Prize::factory()->create();
        $lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'prize_id' => $prize->id_prize,
            'prize_quantity' => 1,
        ]);
        EditionLotteryRule::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'edition_division_id' => $division->id_edition_division,
            'rule_prize_quantity' => 1,
            'orderliness' => 0,
        ]);

        $miss = $this->getJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}/live");
        $miss->assertOk()->assertJsonPath('data.lucky.player.avatar_url', null);

        $relative = 'cached-pdf-player-images/1/picture/avatar.png';
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))->put($relative, 'png');
        CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => $relative,
            'inserted_at' => time(),
        ]);

        $hit = $this->getJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}/live");
        $hit->assertOk();
        $this->assertNotNull($hit->json('data.lucky.player.avatar_url'));
        $this->assertStringContainsString('cached-pdf-player-images', (string) $hit->json('data.lucky.player.avatar_url'));
        $this->assertSame($participation->player_id, $hit->json('data.lucky.player.id_player'));
    }
}
