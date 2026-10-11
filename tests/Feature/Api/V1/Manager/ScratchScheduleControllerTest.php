<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\EventDivisionStanding;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\MatchSide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScratchScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    /** @var list<int> */
    private array $playerIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        for ($i = 0; $i < 8; $i++) {
            $this->playerIds[] = User::factory()->create()->id_user;
        }
    }

    public function test_preview_returns_schedule_without_db_writes(): void
    {
        Sanctum::actingAs($this->manager);

        $matchesBefore = GameMatch::query()->count();
        $sidesBefore = MatchSide::query()->count();
        $playersBefore = MatchPlayer::query()->count();
        $standingsBefore = EventDivisionStanding::query()->count();

        $response = $this->postJson('/api/v1/manager/tools/schedule/preview', [
            'player_user_ids' => $this->playerIds,
            'court_names' => ['Court A'],
            'player_opponents_nr' => 4,
            'player_matches_repeat' => 1,
            'playing_start_time' => '14:00:00',
            'playing_end_time' => '17:00:00',
            'include_final' => false,
            'has_demonstrative_match' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'player_user_ids',
                    'opponents_per_player',
                    'repeat_partners',
                    'court_names',
                    'matches_by_court',
                    'points_per_match',
                    'matches_count',
                    'template_file',
                    'template_version',
                    'average_playing_time_minutes',
                ],
            ]);

        $this->assertSame($this->playerIds, $response->json('data.player_user_ids'));
        $this->assertSame(4, (int) $response->json('data.opponents_per_player'));
        $this->assertGreaterThan(0, (int) $response->json('data.matches_count'));
        $this->assertNotEmpty($response->json('data.matches_by_court'));
        $this->assertIsString($response->json('data.template_file'));
        $this->assertNotSame('', $response->json('data.template_file'));
        $this->assertIsNumeric($response->json('data.average_playing_time_minutes'));
        $this->assertArrayHasKey('points_per_match_print', $response->json('data'));
        $this->assertSame(
            \App\Services\Schedule\PointsPerMatchCalculator::evenRoundForPrint(
                (int) $response->json('data.points_per_match')
            ),
            (int) $response->json('data.points_per_match_print')
        );

        $this->assertSame($matchesBefore, GameMatch::query()->count());
        $this->assertSame($sidesBefore, MatchSide::query()->count());
        $this->assertSame($playersBefore, MatchPlayer::query()->count());
        $this->assertSame($standingsBefore, EventDivisionStanding::query()->count());
    }

    public function test_preview_applies_allowlisted_adjust_and_print(): void
    {
        Sanctum::actingAs($this->manager);

        $base = $this->postJson('/api/v1/manager/tools/schedule/preview', [
            'player_user_ids' => $this->playerIds,
            'court_names' => ['Court A'],
            'opponents_per_player' => 4,
            'repeat_partners' => 1,
            'time_start' => '14:00',
            'time_end' => '17:00',
            'adjust' => 0,
        ])->assertOk();

        $basePpm = (int) $base->json('data.points_per_match');

        $adjusted = $this->postJson('/api/v1/manager/tools/schedule/preview', [
            'player_user_ids' => $this->playerIds,
            'court_names' => ['Court A'],
            'opponents_per_player' => 4,
            'repeat_partners' => 1,
            'time_start' => '14:00',
            'time_end' => '17:00',
            'adjust' => 2,
        ])->assertOk();

        $ppm = (int) $adjusted->json('data.points_per_match');
        $this->assertSame($basePpm + 2, $ppm);
        $this->assertSame(2, (int) $adjusted->json('data.adjust'));
        $this->assertSame(
            \App\Services\Schedule\PointsPerMatchCalculator::evenRoundForPrint($ppm),
            (int) $adjusted->json('data.points_per_match_print')
        );
    }

    public function test_preview_omitting_adjust_still_returns_print(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/tools/schedule/preview', [
            'player_user_ids' => $this->playerIds,
            'court_names' => ['Court A'],
            'opponents_per_player' => 4,
            'repeat_partners' => 1,
            'time_start' => '14:00',
            'time_end' => '17:00',
        ])->assertOk();

        $this->assertSame(0, (int) $response->json('data.adjust'));
        $this->assertArrayHasKey('points_per_match_print', $response->json('data'));
    }

    public function test_preview_rejects_non_allowlisted_adjust(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/tools/schedule/preview', [
            'player_user_ids' => $this->playerIds,
            'court_names' => ['Court A'],
            'opponents_per_player' => 4,
            'repeat_partners' => 1,
            'time_start' => '14:00',
            'time_end' => '17:00',
            'adjust' => 3,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['adjust']);
    }

    public function test_preview_accepts_mode_b_alias_fields(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/tools/schedule/preview', [
            'player_user_ids' => $this->playerIds,
            'court_names' => ['Court A'],
            'opponents_per_player' => 4,
            'repeat_partners' => 1,
            'time_start' => '14:00',
            'time_end' => '17:00',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.courts_count', 1)
            ->assertJsonPath('data.time_start', '14:00')
            ->assertJsonPath('data.time_end', '17:00')
            ->assertJsonPath('data.opponents_per_player', 4)
            ->assertJsonPath('data.repeat_partners', 1);

        $this->assertSame(0, GameMatch::query()->count());
    }

    public function test_preview_requires_authentication(): void
    {
        $this->postJson('/api/v1/manager/tools/schedule/preview', [
            'player_user_ids' => $this->playerIds,
            'court_names' => ['Court A'],
            'player_opponents_nr' => 4,
            'playing_start_time' => '14:00:00',
            'playing_end_time' => '17:00:00',
        ])->assertStatus(401);
    }

    public function test_preview_validates_required_inputs(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/tools/schedule/preview', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['player_user_ids', 'court_names']);

        $this->postJson('/api/v1/manager/tools/schedule/preview', [
            'player_user_ids' => $this->playerIds,
            'court_names' => ['Court A'],
            'player_opponents_nr' => 4,
        ])->assertStatus(422);
    }
}
