<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\EventDivisionSchedule;
use App\Models\EventDivisionStanding;
use App\Models\EventParticipation;
use App\Models\GameMatch;
use App\Models\MatchSide;
use App\Models\PadelEvent;
use App\Models\User;
use App\Services\EventCalendarTiming;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\UnixEventFactory;
use Tests\TestCase;

class EventDivisionScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private EventDivision $eventDivision;

    private EventDivisionSchedule $schedule;

    /** @var list<EventParticipation> */
    private array $participations = [];

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $event = PadelEvent::factory()->create();
        $division = Division::factory()->create();
        $playingWindow = UnixEventFactory::playingBounds($event, '14:00:00', '17:00:00');

        $this->eventDivision = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $division->id_division,
            'players_limit' => 8,
            ...$playingWindow,
            'check_in_at' => $playingWindow['playing_starts_at'],
        ]);

        $this->schedule = EventDivisionSchedule::factory()->create([
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_opponents_nr' => 4,
            'player_matches_repeat' => 1,
            'include_final' => false,
            'has_demonstrative_match' => false,
            'include_scores' => true,
            'allow_replacements' => true,
        ]);

        $this->court = Court::factory()->create(['name' => 'Court A']);
        EventDivisionCourt::factory()->create([
            'event_division_id' => $this->eventDivision->id_event_division,
            'court_id' => $this->court->id_court,
            'display_order' => 0,
        ]);

        for ($i = 0; $i < 8; $i++) {
            $this->participations[] = EventParticipation::factory()->create([
                'event_id' => $event->id_event,
                'event_division_id' => $this->eventDivision->id_event_division,
                'player_user_id' => User::factory()->create()->id_user,
                'sort_order' => $i,
                'is_collecting_points' => true,
                'absence_reason' => null,
                'ban_reason' => null,
            ]);
        }
    }

    public function test_generate_persists_regular_matches(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/generate",
            []
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $firstPlayer = data_get($response->json(), 'data.matches.0.sides.0.players.0.player');
        $this->assertIsArray($firstPlayer);
        $this->assertTrue(
            isset($firstPlayer['name']) || isset($firstPlayer['first_name']),
            'Generated matches must include nested player on side players'
        );
        $slot = data_get($response->json(), 'data.matches.0.sides.0.players.0');
        $this->assertIsArray($slot);
        $this->assertArrayNotHasKey('got_replaced', $slot);

        $this->assertGreaterThan(0, GameMatch::query()
            ->where('event_division_id', $this->eventDivision->id_event_division)
            ->where('match_kind', 'REGULAR')
            ->count());

        $this->assertNotNull($this->schedule->fresh()->points_per_match);
    }

    public function test_generate_can_persist_points_above_signed_tinyint(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;

        $event = PadelEvent::query()->findOrFail($this->eventDivision->event_id);
        $this->eventDivision->update(
            UnixEventFactory::playingBounds($event, '10:00:00', '00:00:00')
        );

        $this->putJson("/api/v1/manager/event-divisions/{$edId}/schedule/form", [
            'final_points_target' => 200,
            'participation_ids' => array_map(
                static fn (EventParticipation $p): int => (int) $p->id_participation,
                $this->participations
            ),
            'court_ids' => [$this->court->id_court],
        ])->assertOk();

        $this->assertSame(200, (int) $this->schedule->fresh()->final_points_target);

        $response = $this->postJson(
            "/api/v1/manager/event-divisions/{$edId}/schedule/generate",
            []
        );

        $response->assertStatus(200);
        $ppm = (int) $response->json('data.points_per_match');
        $this->assertGreaterThan(127, $ppm, '14h window should yield points_per_match > 127');

        $this->assertSame($ppm, (int) $this->schedule->fresh()->points_per_match);
        $this->assertSame(
            $ppm,
            (int) GameMatch::query()
                ->where('event_division_id', $edId)
                ->where('match_kind', 'REGULAR')
                ->value('points_target')
        );
    }

    public function test_generate_persists_adjusted_raw_points_from_preview(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;

        $this->putJson("/api/v1/manager/event-divisions/{$edId}/schedule/form", [
            'adjust' => 2,
            'participation_ids' => array_map(
                static fn (EventParticipation $p): int => (int) $p->id_participation,
                $this->participations
            ),
            'court_ids' => [$this->court->id_court],
        ])->assertOk();

        $preview = $this->postJson(
            "/api/v1/manager/event-divisions/{$edId}/schedule/preview",
            []
        )->assertOk();

        $previewPpm = (int) $preview->json('data.points_per_match');
        $previewPrint = (int) $preview->json('data.points_per_match_print');
        $this->assertSame(2, (int) $preview->json('data.adjust'));
        $this->assertSame(
            \App\Services\Schedule\PointsPerMatchCalculator::evenRoundForPrint($previewPpm),
            $previewPrint
        );

        $this->postJson(
            "/api/v1/manager/event-divisions/{$edId}/schedule/generate",
            []
        )->assertOk();

        $stored = (int) $this->schedule->fresh()->points_per_match;
        $this->assertSame($previewPpm, $stored);
        if ($previewPpm % 2 === 1) {
            $this->assertNotSame($previewPrint, $stored);
        }
    }

    public function test_generate_requires_confirm_replace_when_schedule_exists(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/generate",
            []
        )->assertStatus(200);

        $response = $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/generate",
            []
        );

        $response->assertStatus(409)
            ->assertJsonPath('errors.confirm_replace_required', true);

        $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/generate",
            ['confirm_replace' => true]
        )->assertStatus(200);
    }

    public function test_generate_blocked_when_scores_exist(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/generate",
            []
        )->assertStatus(200);

        $match = GameMatch::query()
            ->where('event_division_id', $this->eventDivision->id_event_division)
            ->firstOrFail();

        MatchSide::query()->where('match_id', $match->id_match)->where('side', 'LEFT')->update(['score' => 12]);
        MatchSide::query()->where('match_id', $match->id_match)->where('side', 'RIGHT')->update(['score' => 10]);

        $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/generate",
            ['confirm_replace' => true]
        )->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_generate_blocked_when_standings_exist(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/generate",
            []
        )->assertStatus(200);

        EventDivisionStanding::create([
            'event_division_id' => $this->eventDivision->id_event_division,
            'participation_id' => $this->participations[0]->id_participation,
            'points_total' => 0,
            'wins' => 0,
            'rank' => 1,
        ]);

        $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/generate",
            ['confirm_replace' => true]
        )->assertStatus(409);
    }

    public function test_generate_blocked_when_finalized(): void
    {
        Sanctum::actingAs($this->manager);

        $this->eventDivision->event->update(['finalized_at' => time()]);

        $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/generate",
            []
        )->assertStatus(409);
    }

    public function test_preview_does_not_persist_matches(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/preview",
            []
        )->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['matches_by_court', 'points_per_match']]);

        $this->assertSame(0, GameMatch::query()
            ->where('event_division_id', $this->eventDivision->id_event_division)
            ->count());
    }

    public function test_delete_schedule_blocked_when_scores_exist(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/generate",
            []
        )->assertStatus(200);

        $match = GameMatch::query()
            ->where('event_division_id', $this->eventDivision->id_event_division)
            ->firstOrFail();
        MatchSide::query()->where('match_id', $match->id_match)->update(['score' => 5]);

        $this->deleteJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule"
        )->assertStatus(409);
    }

    public function test_schedule_form_court_sync_stores_unix_updated_at(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $courtB = Court::factory()->create(['name' => 'Court B']);

        // First sync (may attach/update) — exercise updateExistingPivot path by syncing same court again with reordered ids
        $this->putJson("/api/v1/manager/event-divisions/{$edId}/schedule/form", [
            'court_ids' => [$this->court->id_court, $courtB->id_court],
            'participation_ids' => array_map(
                static fn (EventParticipation $p): int => (int) $p->id_participation,
                $this->participations
            ),
        ])->assertOk();

        $this->putJson("/api/v1/manager/event-divisions/{$edId}/schedule/form", [
            'court_ids' => [$courtB->id_court, $this->court->id_court],
            'participation_ids' => array_map(
                static fn (EventParticipation $p): int => (int) $p->id_participation,
                $this->participations
            ),
        ])->assertOk();

        $rows = EventDivisionCourt::query()
            ->where('event_division_id', $edId)
            ->orderBy('display_order')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame((int) $courtB->id_court, (int) $rows[0]->court_id);
        $this->assertSame((int) $this->court->id_court, (int) $rows[1]->court_id);

        foreach ($rows as $row) {
            $inserted = $row->getAttributes()['inserted_at'] ?? null;
            $updated = $row->getAttributes()['updated_at'] ?? null;
            $this->assertTrue(is_numeric($inserted), 'inserted_at should be numeric unix');
            $this->assertTrue(is_numeric($updated), 'updated_at should be numeric unix');
            $this->assertDoesNotMatchRegularExpression(
                '/^\d{4}-\d{2}-\d{2}/',
                (string) $updated
            );
        }
    }

    public function test_edition_division_persists_overnight_playing_end(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $event = PadelEvent::query()->findOrFail($this->eventDivision->event_id);
        $window = UnixEventFactory::playingBounds($event, '18:00', '00:00');
        $window['check_in_at'] = $window['playing_starts_at'];

        $this->patchJson("/api/v1/manager/event-divisions/{$edId}", $window)->assertOk();

        $fresh = $this->eventDivision->fresh();
        $this->assertSame(
            '00:00',
            EventCalendarTiming::formatHi((int) $fresh->playing_ends_at)
        );

        $this->getJson("/api/v1/manager/event-divisions/{$edId}")
            ->assertOk()
            ->assertJsonPath('data.playing_ends_at', $window['playing_ends_at']);
    }

    public function test_schedule_form_rejects_playing_times(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;

        $this->putJson("/api/v1/manager/event-divisions/{$edId}/schedule/form", [
            'playing_start_time' => '18:00',
        ])->assertStatus(422);

        $this->putJson("/api/v1/manager/event-divisions/{$edId}/schedule/form", [
            'playing_end_time' => '00:00',
        ])->assertStatus(422);
    }

    public function test_schedule_form_rejects_adjust_outside_allowed_values(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;

        $this->putJson("/api/v1/manager/event-divisions/{$edId}/schedule/form", [
            'adjust' => 3,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['adjust']);

        $this->putJson("/api/v1/manager/event-divisions/{$edId}/schedule/form", [
            'adjust' => -4,
        ])->assertOk();

        $this->assertSame(-4, (int) $this->schedule->fresh()->adjust);
    }

    public function test_schedule_form_persists_knobs_on_schedule_row(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;

        $this->putJson("/api/v1/manager/event-divisions/{$edId}/schedule/form", [
            'include_scores' => false,
            'allow_replacements' => false,
            'final_points_target' => 32,
        ])->assertOk()
            ->assertJsonPath('data.schedule.include_scores', false)
            ->assertJsonPath('data.schedule.allow_replacements', false)
            ->assertJsonPath('data.schedule.final_points_target', 32);

        $fresh = $this->schedule->fresh();
        $this->assertFalse((bool) $fresh->include_scores);
        $this->assertFalse((bool) $fresh->allow_replacements);
        $this->assertSame(32, (int) $fresh->final_points_target);
    }

    public function test_schedule_form_without_participation_keys_leaves_order_and_collecting(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $first = $this->participations[0];
        $second = $this->participations[1];
        $first->update([
            'sort_order' => 50,
            'is_collecting_points' => false,
        ]);
        $second->update([
            'sort_order' => 7,
            'is_collecting_points' => true,
        ]);

        $this->putJson("/api/v1/manager/event-divisions/{$edId}/schedule/form", [
            'court_ids' => [$this->court->id_court],
        ])->assertOk();

        $first->refresh();
        $second->refresh();
        $this->assertSame(50, (int) $first->sort_order);
        $this->assertFalse((bool) $first->is_collecting_points);
        $this->assertSame(7, (int) $second->sort_order);
        $this->assertTrue((bool) $second->is_collecting_points);
    }

    public function test_preview_succeeds_for_overnight_and_end_of_day_windows(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;

        $event = PadelEvent::query()->findOrFail($this->eventDivision->event_id);
        $this->eventDivision->update(UnixEventFactory::playingBounds($event, '18:00:00', '00:00:00'));

        $overnight = $this->postJson(
            "/api/v1/manager/event-divisions/{$edId}/schedule/preview",
            []
        );

        $overnight->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['matches_by_court', 'points_per_match']]);
        $this->assertStringNotContainsString('PT-', (string) $overnight->json('message'));
        $this->assertStringNotContainsString('Unknown or bad format', (string) json_encode($overnight->json()));

        $this->eventDivision->update(UnixEventFactory::playingBounds($event, '18:00:00', '00:00:00'));

        $toMidnight = $this->postJson(
            "/api/v1/manager/event-divisions/{$edId}/schedule/preview",
            []
        );

        $toMidnight->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertIsArray($toMidnight->json('data.matches_by_court'));
    }

    public function test_generate_persists_template_version(): void
    {
        Sanctum::actingAs($this->manager);

        $this->assertNull($this->schedule->fresh()->template_version);

        $response = $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/generate",
            []
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $version = data_get($response->json(), 'data.schedule.template_version');
        $this->assertNotNull($version);
        $this->assertSame((int) $version, (int) $this->schedule->fresh()->template_version);

        $this->getJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule"
        )->assertOk()
            ->assertJsonPath('data.schedule.template_version', (int) $version);
    }

    public function test_destroy_schedule_nulls_template_version(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/generate",
            []
        )->assertStatus(200);

        $this->assertNotNull($this->schedule->fresh()->template_version);

        $this->deleteJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule"
        )->assertStatus(200);

        $this->assertNull($this->schedule->fresh()->template_version);
        $this->assertNotNull($this->eventDivision->fresh()->schedule);
    }

    public function test_schedule_show_includes_board_metrics_without_players_board(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $this->getJson("/api/v1/manager/event-divisions/{$edId}/schedule")
            ->assertOk()
            ->assertJsonPath('data.board_metrics', null)
            ->assertJsonPath('data.schedule.points_per_match', null)
            ->assertJsonPath('data.schedule.points_per_match_print', null)
            ->assertJsonMissingPath('data.players_board');

        $this->postJson("/api/v1/manager/event-divisions/{$edId}/schedule/generate", [])
            ->assertStatus(200);

        $show = $this->getJson("/api/v1/manager/event-divisions/{$edId}/schedule");
        $show->assertOk()
            ->assertJsonPath('data.board_metrics.players_count', 8)
            ->assertJsonMissingPath('data.players_board');
        $distribution = $show->json('data.board_metrics.playing_distribution');
        $this->assertIsArray($distribution);
        $this->assertArrayHasKey('avg', $distribution);
        $this->assertArrayNotHasKey('average', $distribution);
        $this->assertArrayHasKey('percentage', $distribution);
        $this->assertArrayHasKey('css_class', $distribution);
        $this->assertContains($distribution['css_class'], ['excellent', 'good', 'fair', 'poor']);
        $perPlayer = $distribution['per_player'] ?? null;
        $this->assertIsArray($perPlayer);
        $this->assertNotEmpty($perPlayer);
        $first = $perPlayer[array_key_first($perPlayer)];
        $this->assertArrayHasKey('score', $first);
        $this->assertArrayHasKey('percentage', $first);
        $this->assertArrayHasKey('css_class', $first);
        $this->assertArrayHasKey('version', $show->json('data.board_metrics.template_identity'));

        $storedPpm = (int) $this->schedule->fresh()->points_per_match;
        $this->assertSame($storedPpm, (int) $show->json('data.board_metrics.points_per_match'));
        $this->assertSame(
            \App\Services\Schedule\PointsPerMatchCalculator::evenRoundForPrint($storedPpm),
            (int) $show->json('data.board_metrics.points_per_match_print')
        );
        $this->assertSame(
            \App\Services\Schedule\PointsPerMatchCalculator::evenRoundForPrint($storedPpm),
            (int) $show->json('data.schedule.points_per_match_print')
        );
    }

    public function test_schedule_show_scores_1_attaches_couple_image_url_from_cache_only(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/schedule/generate", [])
            ->assertStatus(200);

        $match = \App\Models\GameMatch::query()
            ->where('event_division_id', $edId)
            ->where('match_kind', 'REGULAR')
            ->with(['sides.players.participation'])
            ->firstOrFail();
        $side = $match->sides->first();
        $this->assertNotNull($side);
        $players = $side->players->values();
        $this->assertGreaterThanOrEqual(2, $players->count());

        $idA = (int) $players[0]->player_user_id;
        $idB = (int) $players[1]->player_user_id;
        $cA = (bool) ($players[0]->participation?->is_collecting_points ?? true);
        $cB = (bool) ($players[1]->participation?->is_collecting_points ?? true);
        if ($idA <= $idB) {
            $p1 = $idA;
            $p2 = $idB;
            $c1 = $cA;
            $c2 = $cB;
        } else {
            $p1 = $idB;
            $p2 = $idA;
            $c1 = $cB;
            $c2 = $cA;
        }

        \App\Models\CachedPdfPairImage::query()->create([
            'player_1_user_id' => $p1,
            'player_2_user_id' => $p2,
            'player_1_is_collecting_points' => $c1,
            'player_2_is_collecting_points' => $c2,
            'picture_path' => 'cached-pdf-pair-images/1/picture/couple.png',
        ]);

        $without = $this->getJson("/api/v1/manager/event-divisions/{$edId}/schedule");
        $without->assertOk();
        $sideWithout = collect($without->json('data.matches'))
            ->firstWhere('id_match', $match->id_match)['sides'][0] ?? null;
        $this->assertIsArray($sideWithout);
        $this->assertArrayNotHasKey('couple_image_url', $sideWithout);

        $with = $this->getJson("/api/v1/manager/event-divisions/{$edId}/schedule?scores=1");
        $with->assertOk();
        $sideWith = collect($with->json('data.matches'))
            ->firstWhere('id_match', $match->id_match)['sides'] ?? [];
        $hit = collect($sideWith)->first(fn ($s) => ($s['couple_image_url'] ?? null) !== null);
        $this->assertNotNull($hit);

        // Wrong collecting flags → null
        \App\Models\CachedPdfPairImage::query()->delete();
        \App\Models\CachedPdfPairImage::query()->create([
            'player_1_user_id' => $p1,
            'player_2_user_id' => $p2,
            'player_1_is_collecting_points' => ! $c1,
            'player_2_is_collecting_points' => ! $c2,
            'picture_path' => 'cached-pdf-pair-images/2/picture/couple.png',
        ]);
        $miss = $this->getJson("/api/v1/manager/event-divisions/{$edId}/schedule?scores=1");
        $sideMiss = collect($miss->json('data.matches'))
            ->firstWhere('id_match', $match->id_match)['sides'] ?? [];
        foreach ($sideMiss as $s) {
            $this->assertNull($s['couple_image_url']);
        }

        $this->assertSame(1, \App\Models\CachedPdfPairImage::query()->count());
    }

    public function test_schedule_show_board_1_includes_players_board_short_names(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/schedule/generate", [])
            ->assertStatus(200);

        $show = $this->getJson("/api/v1/manager/event-divisions/{$edId}/schedule?board=1");
        $show->assertOk();
        $board = $show->json('data.players_board');
        $this->assertIsArray($board);
        $this->assertNotEmpty($board);
        $this->assertArrayHasKey('short_name', $board[0]);
        $this->assertArrayHasKey('pdf_avatar_url', $board[0]);
        $this->assertNull($board[0]['pdf_avatar_url']);
    }

    public function test_schedule_show_board_never_generates_cached_pdf_player_images(): void
    {
        Sanctum::actingAs($this->manager);

        $disk = config('filesystems.user_uploads_disk', 'public');
        \Illuminate\Support\Facades\Storage::fake($disk);
        foreach ($this->participations as $participation) {
            $relative = 'player-profile-images/' . $participation->player_user_id . '/profile/avatar.png';
            \Illuminate\Support\Facades\Storage::disk($disk)->put($relative, $this->solidPngBytes());
            \App\Models\PlayerProfileImage::query()->create([
                'player_user_id' => $participation->player_user_id,
                'profile_path' => $relative,
                'sort_order' => 0,
                'inserted_at' => time(),
            ]);
        }

        $edId = $this->eventDivision->id_event_division;
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/schedule/generate", [])
            ->assertStatus(200);

        $this->getJson("/api/v1/manager/event-divisions/{$edId}/schedule?board=1")->assertOk();

        $this->assertSame(0, \App\Models\CachedPdfPlayerImage::query()->count());
    }

    public function test_schedule_show_board_returns_existing_cached_avatar_url(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/schedule/generate", [])
            ->assertStatus(200);

        $playerId = (int) $this->participations[0]->player_user_id;
        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $playerId,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/1/picture/avatar.png',
            'inserted_at' => time(),
        ]);

        $board = $this->getJson("/api/v1/manager/event-divisions/{$edId}/schedule?board=1")
            ->assertOk()
            ->json('data.players_board');

        $entry = collect($board)->firstWhere('player_user_id', $playerId);
        $this->assertNotNull($entry);
        $this->assertNotNull($entry['pdf_avatar_url']);
        $this->assertSame(1, \App\Models\CachedPdfPlayerImage::query()->count());
    }

    public function test_schedule_show_participations_include_collecting_aware_pdf_avatar_url(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $playerId = (int) $this->participations[0]->player_user_id;
        $this->participations[0]->update(['is_collecting_points' => true]);

        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $playerId,
            'player_is_collecting_points' => false,
            'picture_path' => 'cached-pdf-player-images/' . $playerId . '/false.png',
            'inserted_at' => time(),
        ]);

        $missRows = $this->getJson("/api/v1/manager/event-divisions/{$edId}/schedule")
            ->assertOk()
            ->json('data.participations');
        $miss = collect($missRows)->firstWhere('player_user_id', $playerId);
        $this->assertNull($miss['pdf_avatar_url']);

        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $playerId,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $playerId . '/true.png',
            'inserted_at' => time(),
        ]);

        $rows = $this->getJson("/api/v1/manager/event-divisions/{$edId}/schedule")
            ->assertOk()
            ->json('data.participations');
        $row = collect($rows)->firstWhere('player_user_id', $playerId);
        $this->assertNotNull($row);
        $this->assertNotNull($row['pdf_avatar_url']);
    }

    public function test_schedule_show_participation_pdf_avatar_fresh_after_cache_warm(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $playerId = (int) $this->participations[0]->player_user_id;
        $this->participations[0]->update(['is_collecting_points' => true]);

        $warm = $this->getJson("/api/v1/manager/event-divisions/{$edId}/schedule")->assertOk();
        $warmRow = collect($warm->json('data.participations'))->firstWhere('player_user_id', $playerId);
        $this->assertNull($warmRow['pdf_avatar_url']);

        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $playerId,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $playerId . '/fresh.png',
            'inserted_at' => time(),
        ]);

        $again = $this->getJson("/api/v1/manager/event-divisions/{$edId}/schedule")->assertOk();
        $row = collect($again->json('data.participations'))->firstWhere('player_user_id', $playerId);
        $this->assertNotNull($row['pdf_avatar_url']);
    }

    public function test_schedule_form_save_hydrates_participation_pdf_avatar_url(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;
        $playerId = (int) $this->participations[0]->player_user_id;
        $this->participations[0]->update(['is_collecting_points' => true]);
        \App\Models\CachedPdfPlayerImage::query()->create([
            'player_user_id' => $playerId,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $playerId . '/form.png',
            'inserted_at' => time(),
        ]);

        $response = $this->putJson("/api/v1/manager/event-divisions/{$edId}/schedule/form", [
            'court_ids' => [$this->court->id_court],
            'participation_ids' => array_map(
                static fn (EventParticipation $p): int => (int) $p->id_participation,
                $this->participations
            ),
        ]);

        $response->assertOk();
        $row = collect($response->json('data.participations'))->firstWhere('player_user_id', $playerId);
        $this->assertNotNull($row);
        $this->assertNotNull($row['pdf_avatar_url']);
    }

    private function solidPngBytes(): string
    {
        return \Tests\Support\PdfFixtures::solidRed40Bytes();
    }
}
