<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionDivisionStanding;
use App\Models\EditionParticipation;
use App\Models\GameMatch;
use App\Models\MatchSide;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditionDivisionScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private EditionDivision $editionDivision;

    /** @var list<EditionParticipation> */
    private array $participations = [];

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $edition = Edition::factory()->create();
        $division = Division::factory()->create();

        $this->editionDivision = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $division->id_division,
            'players_limit' => 8,
            'playing_start_time' => '14:00:00',
            'playing_end_time' => '17:00:00',
            'player_opponents_nr' => 4,
            'player_matches_repeat' => 1,
            'include_final' => false,
            'has_demonstrative_match' => false,
            'include_scores' => true,
            'allow_replacements' => true,
        ]);

        $this->court = Court::factory()->create(['name' => 'Court A']);
        EditionDivisionCourt::factory()->create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'court_id' => $this->court->id_court,
            'display_order' => 0,
        ]);

        for ($i = 0; $i < 8; $i++) {
            $this->participations[] = EditionParticipation::factory()->create([
                'edition_id' => $edition->id_edition,
                'edition_division_id' => $this->editionDivision->id_edition_division,
                'player_id' => Player::factory()->create()->id_player,
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
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/generate",
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

        $this->assertGreaterThan(0, GameMatch::query()
            ->where('edition_division_id', $this->editionDivision->id_edition_division)
            ->where('match_kind', 'REGULAR')
            ->count());

        $this->assertNotNull($this->editionDivision->fresh()->points_per_match);
    }

    public function test_generate_can_persist_points_above_signed_tinyint(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;

        $this->putJson("/api/v1/manager/edition-divisions/{$edId}/schedule/form", [
            'playing_start_time' => '10:00:00',
            'playing_end_time' => '24:00:00',
            'final_points_target' => 200,
            'participation_ids' => array_map(
                static fn (EditionParticipation $p): int => (int) $p->id_participation,
                $this->participations
            ),
            'court_ids' => [$this->court->id_court],
        ])->assertOk();

        $this->assertSame(200, (int) $this->editionDivision->fresh()->final_points_target);

        $response = $this->postJson(
            "/api/v1/manager/edition-divisions/{$edId}/schedule/generate",
            []
        );

        $response->assertStatus(200);
        $ppm = (int) $response->json('data.points_per_match');
        $this->assertGreaterThan(127, $ppm, '14h window should yield points_per_match > 127');

        $this->assertSame($ppm, (int) $this->editionDivision->fresh()->points_per_match);
        $this->assertSame(
            $ppm,
            (int) GameMatch::query()
                ->where('edition_division_id', $edId)
                ->where('match_kind', 'REGULAR')
                ->value('points_target')
        );
    }

    public function test_generate_requires_confirm_replace_when_schedule_exists(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/generate",
            []
        )->assertStatus(200);

        $response = $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/generate",
            []
        );

        $response->assertStatus(409)
            ->assertJsonPath('errors.confirm_replace_required', true);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/generate",
            ['confirm_replace' => true]
        )->assertStatus(200);
    }

    public function test_generate_blocked_when_scores_exist(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/generate",
            []
        )->assertStatus(200);

        $match = GameMatch::query()
            ->where('edition_division_id', $this->editionDivision->id_edition_division)
            ->firstOrFail();

        MatchSide::query()->where('match_id', $match->id_match)->where('side', 'LEFT')->update(['score' => 12]);
        MatchSide::query()->where('match_id', $match->id_match)->where('side', 'RIGHT')->update(['score' => 10]);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/generate",
            ['confirm_replace' => true]
        )->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_generate_blocked_when_standings_exist(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/generate",
            []
        )->assertStatus(200);

        EditionDivisionStanding::create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'participation_id' => $this->participations[0]->id_participation,
            'points_total' => 0,
            'wins' => 0,
            'rank' => 1,
        ]);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/generate",
            ['confirm_replace' => true]
        )->assertStatus(409);
    }

    public function test_generate_blocked_when_finalized(): void
    {
        Sanctum::actingAs($this->manager);

        $this->editionDivision->edition->update(['finalized_at' => time()]);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/generate",
            []
        )->assertStatus(409);
    }

    public function test_preview_does_not_persist_matches(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/preview",
            []
        )->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['matches_by_court', 'points_per_match']]);

        $this->assertSame(0, GameMatch::query()
            ->where('edition_division_id', $this->editionDivision->id_edition_division)
            ->count());
    }

    public function test_delete_schedule_blocked_when_scores_exist(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/generate",
            []
        )->assertStatus(200);

        $match = GameMatch::query()
            ->where('edition_division_id', $this->editionDivision->id_edition_division)
            ->firstOrFail();
        MatchSide::query()->where('match_id', $match->id_match)->update(['score' => 5]);

        $this->deleteJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule"
        )->assertStatus(409);
    }

    public function test_schedule_form_court_sync_stores_unix_updated_at(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $courtB = Court::factory()->create(['name' => 'Court B']);

        // First sync (may attach/update) — exercise updateExistingPivot path by syncing same court again with reordered ids
        $this->putJson("/api/v1/manager/edition-divisions/{$edId}/schedule/form", [
            'court_ids' => [$this->court->id_court, $courtB->id_court],
            'participation_ids' => array_map(
                static fn (EditionParticipation $p): int => (int) $p->id_participation,
                $this->participations
            ),
        ])->assertOk();

        $this->putJson("/api/v1/manager/edition-divisions/{$edId}/schedule/form", [
            'court_ids' => [$courtB->id_court, $this->court->id_court],
            'participation_ids' => array_map(
                static fn (EditionParticipation $p): int => (int) $p->id_participation,
                $this->participations
            ),
        ])->assertOk();

        $rows = EditionDivisionCourt::query()
            ->where('edition_division_id', $edId)
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

    public function test_schedule_form_persists_end_of_day_playing_end(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;

        $this->putJson("/api/v1/manager/edition-divisions/{$edId}/schedule/form", [
            'playing_start_time' => '18:00',
            'playing_end_time' => '24:00',
            'participation_ids' => array_map(
                static fn (EditionParticipation $p): int => (int) $p->id_participation,
                $this->participations
            ),
            'court_ids' => [$this->court->id_court],
        ])->assertOk();

        $fresh = $this->editionDivision->fresh();
        $this->assertSame('24:00:00', $fresh->getAttributes()['playing_end_time'] ?? null);
        $this->assertSame('24:00:00', $fresh->formatted_playing_end_time);

        $this->getJson("/api/v1/manager/edition-divisions/{$edId}")
            ->assertOk()
            ->assertJsonPath('data.playing_end_time', '24:00:00');
    }

    public function test_preview_succeeds_for_overnight_and_end_of_day_windows(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;

        $overnight = $this->postJson(
            "/api/v1/manager/edition-divisions/{$edId}/schedule/preview",
            [
                'playing_start_time' => '18:00',
                'playing_end_time' => '00:00',
            ]
        );

        $overnight->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['matches_by_court', 'points_per_match']]);
        $this->assertStringNotContainsString('PT-', (string) $overnight->json('message'));
        $this->assertStringNotContainsString('Unknown or bad format', (string) json_encode($overnight->json()));

        $endOfDay = $this->postJson(
            "/api/v1/manager/edition-divisions/{$edId}/schedule/preview",
            [
                'playing_start_time' => '18:00',
                'playing_end_time' => '24:00:00',
            ]
        );

        $endOfDay->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertIsArray($endOfDay->json('data.matches_by_court'));
    }

    public function test_generate_persists_template_version(): void
    {
        Sanctum::actingAs($this->manager);

        $this->assertNull($this->editionDivision->fresh()->template_version);

        $response = $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/generate",
            []
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $version = data_get($response->json(), 'data.edition_division.template_version');
        $this->assertNotNull($version);
        $this->assertSame((int) $version, (int) $this->editionDivision->fresh()->template_version);

        $this->getJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule"
        )->assertOk()
            ->assertJsonPath('data.edition_division.template_version', (int) $version);
    }

    public function test_destroy_schedule_nulls_template_version(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/generate",
            []
        )->assertStatus(200);

        $this->assertNotNull($this->editionDivision->fresh()->template_version);

        $this->deleteJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule"
        )->assertStatus(200);

        $this->assertNull($this->editionDivision->fresh()->template_version);
    }

    public function test_schedule_show_includes_board_metrics_without_players_board(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $this->getJson("/api/v1/manager/edition-divisions/{$edId}/schedule")
            ->assertOk()
            ->assertJsonPath('data.board_metrics', null)
            ->assertJsonMissingPath('data.players_board');

        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/schedule/generate", [])
            ->assertStatus(200);

        $show = $this->getJson("/api/v1/manager/edition-divisions/{$edId}/schedule");
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
    }

    public function test_schedule_show_scores_1_attaches_couple_image_url_from_cache_only(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/schedule/generate", [])
            ->assertStatus(200);

        $match = \App\Models\GameMatch::query()
            ->where('edition_division_id', $edId)
            ->where('match_kind', 'REGULAR')
            ->with(['sides.players.participation'])
            ->firstOrFail();
        $side = $match->sides->first();
        $this->assertNotNull($side);
        $players = $side->players->values();
        $this->assertGreaterThanOrEqual(2, $players->count());

        $idA = (int) $players[0]->player_id;
        $idB = (int) $players[1]->player_id;
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
            'player_1_id' => $p1,
            'player_2_id' => $p2,
            'player_1_is_collecting_points' => $c1,
            'player_2_is_collecting_points' => $c2,
            'picture_path' => 'cached-pdf-pair-images/1/picture/couple.png',
        ]);

        $without = $this->getJson("/api/v1/manager/edition-divisions/{$edId}/schedule");
        $without->assertOk();
        $sideWithout = collect($without->json('data.matches'))
            ->firstWhere('id_match', $match->id_match)['sides'][0] ?? null;
        $this->assertIsArray($sideWithout);
        $this->assertArrayNotHasKey('couple_image_url', $sideWithout);

        $with = $this->getJson("/api/v1/manager/edition-divisions/{$edId}/schedule?scores=1");
        $with->assertOk();
        $sideWith = collect($with->json('data.matches'))
            ->firstWhere('id_match', $match->id_match)['sides'] ?? [];
        $hit = collect($sideWith)->first(fn ($s) => ($s['couple_image_url'] ?? null) !== null);
        $this->assertNotNull($hit);

        // Wrong collecting flags → null
        \App\Models\CachedPdfPairImage::query()->delete();
        \App\Models\CachedPdfPairImage::query()->create([
            'player_1_id' => $p1,
            'player_2_id' => $p2,
            'player_1_is_collecting_points' => ! $c1,
            'player_2_is_collecting_points' => ! $c2,
            'picture_path' => 'cached-pdf-pair-images/2/picture/couple.png',
        ]);
        $miss = $this->getJson("/api/v1/manager/edition-divisions/{$edId}/schedule?scores=1");
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

        $edId = $this->editionDivision->id_edition_division;
        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/schedule/generate", [])
            ->assertStatus(200);

        $show = $this->getJson("/api/v1/manager/edition-divisions/{$edId}/schedule?board=1");
        $show->assertOk();
        $board = $show->json('data.players_board');
        $this->assertIsArray($board);
        $this->assertNotEmpty($board);
        $this->assertArrayHasKey('short_name', $board[0]);
        $this->assertArrayHasKey('pdf_avatar_url', $board[0]);
        $this->assertNull($board[0]['pdf_avatar_url']);
    }
}
