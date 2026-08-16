<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\EditionDivisionStanding;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\MatchSide;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScratchScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    /** @var list<int> */
    private array $playerIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        for ($i = 0; $i < 8; $i++) {
            $this->playerIds[] = Player::factory()->create()->id_player;
        }
    }

    public function test_preview_returns_schedule_without_db_writes(): void
    {
        Sanctum::actingAs($this->manager);

        $matchesBefore = GameMatch::query()->count();
        $sidesBefore = MatchSide::query()->count();
        $playersBefore = MatchPlayer::query()->count();
        $standingsBefore = EditionDivisionStanding::query()->count();

        $response = $this->postJson('/api/v1/manager/tools/schedule/preview', [
            'player_ids' => $this->playerIds,
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
                    'player_ids',
                    'opponents_per_player',
                    'repeat_partners',
                    'court_names',
                    'matches_by_court',
                    'points_per_match',
                    'matches_count',
                ],
            ]);

        $this->assertSame($this->playerIds, $response->json('data.player_ids'));
        $this->assertSame(4, (int) $response->json('data.opponents_per_player'));
        $this->assertGreaterThan(0, (int) $response->json('data.matches_count'));
        $this->assertNotEmpty($response->json('data.matches_by_court'));

        $this->assertSame($matchesBefore, GameMatch::query()->count());
        $this->assertSame($sidesBefore, MatchSide::query()->count());
        $this->assertSame($playersBefore, MatchPlayer::query()->count());
        $this->assertSame($standingsBefore, EditionDivisionStanding::query()->count());
    }

    public function test_preview_accepts_mode_b_alias_fields(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/tools/schedule/preview', [
            'player_ids' => $this->playerIds,
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
            'player_ids' => $this->playerIds,
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
            ->assertJsonValidationErrors(['player_ids', 'court_names']);

        $this->postJson('/api/v1/manager/tools/schedule/preview', [
            'player_ids' => $this->playerIds,
            'court_names' => ['Court A'],
            'player_opponents_nr' => 4,
        ])->assertStatus(422);
    }
}
