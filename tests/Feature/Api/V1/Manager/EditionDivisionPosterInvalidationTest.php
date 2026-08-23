<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\Edition;
use App\Services\Pdf\EditionDivisionPosterInvalidator;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionDivisionPoster;
use App\Models\EditionDivisionSchedule;
use App\Models\EditionDivisionStanding;
use App\Models\EditionParticipation;
use App\Models\GameMatch;
use App\Models\MatchSide;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class EditionDivisionPosterInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private Edition $edition;

    private EditionDivision $editionDivision;

    private EditionDivisionCourt $edCourt;

    /** @var list<EditionParticipation> */
    private array $participations = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.user_uploads_disk', 'public'));

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $this->edition = Edition::factory()->create(['date' => '2000-01-01']);
        $division = Division::factory()->create();

        $this->editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $division->id_division,
            'players_limit' => 8,
            'playing_start_time' => '14:00:00',
            'playing_end_time' => '17:00:00',
        ]);

        EditionDivisionSchedule::factory()->create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'player_opponents_nr' => 4,
            'player_matches_repeat' => 1,
            'include_final' => false,
            'has_demonstrative_match' => false,
            'include_scores' => true,
            'allow_replacements' => true,
        ]);

        $court = Court::factory()->create(['name' => 'Court A']);
        $this->edCourt = EditionDivisionCourt::factory()->create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'court_id' => $court->id_court,
            'display_order' => 0,
        ]);

        for ($i = 0; $i < 8; $i++) {
            $this->participations[] = EditionParticipation::factory()->create([
                'edition_id' => $this->edition->id_edition,
                'edition_division_id' => $this->editionDivision->id_edition_division,
                'player_id' => Player::factory()->create()->id_player,
                'sort_order' => $i,
                'is_collecting_points' => true,
                'absence_reason' => null,
                'ban_reason' => null,
            ]);
        }
    }

    public function test_generate_clears_matches_and_ranking_generated_keeps_printed(): void
    {
        $this->markSetUpEditionAsLast();
        Sanctum::actingAs($this->manager);
        [$matches, $ranking] = $this->seedPosters();

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/generate",
            []
        )->assertOk();

        $this->assertGeneratedCleared($matches);
        $this->assertGeneratedCleared($ranking);
        $this->assertSame('printed/matches.pdf', $matches->fresh()->printed_path);
        $this->assertSame('printed/ranking.pdf', $ranking->fresh()->printed_path);
    }

    public function test_destroy_schedule_clears_matches_only(): void
    {
        $this->markSetUpEditionAsLast();
        Sanctum::actingAs($this->manager);
        $edId = $this->editionDivision->id_edition_division;
        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/schedule/generate", [])->assertOk();

        [$matches, $ranking] = $this->seedPosters();

        $this->deleteJson("/api/v1/manager/edition-divisions/{$edId}/schedule")->assertOk();

        $this->assertGeneratedCleared($matches);
        $this->assertNotNull($ranking->fresh()->generated_path);
        $this->assertSame('printed/ranking.pdf', $ranking->fresh()->printed_path);
    }

    public function test_form_pdf_relevant_field_clears_matches_and_ranking(): void
    {
        $this->markSetUpEditionAsLast();
        Sanctum::actingAs($this->manager);
        [$matches, $ranking] = $this->seedPosters();

        $this->putJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/form",
            ['include_scores' => false]
        )->assertOk();

        $this->assertGeneratedCleared($matches);
        $this->assertGeneratedCleared($ranking);
        $this->assertSame('printed/matches.pdf', $matches->fresh()->printed_path);
    }

    public function test_form_collecting_map_clears_matches_and_ranking(): void
    {
        $this->markSetUpEditionAsLast();
        Sanctum::actingAs($this->manager);
        [$matches, $ranking] = $this->seedPosters();
        $p = $this->participations[0];

        $this->putJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/form",
            [
                'is_collecting_points' => [
                    (string) $p->id_participation => false,
                ],
            ]
        )->assertOk();

        $this->assertGeneratedCleared($matches);
        $this->assertGeneratedCleared($ranking);
    }

    public function test_form_sort_order_only_does_not_clear_posters(): void
    {
        Sanctum::actingAs($this->manager);
        [$matches, $ranking] = $this->seedPosters();

        $ids = array_map(
            static fn (EditionParticipation $p): int => (int) $p->id_participation,
            array_reverse($this->participations)
        );

        $this->putJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/schedule/form",
            ['participation_ids' => $ids]
        )->assertOk();

        $this->assertNotNull($matches->fresh()->generated_path);
        $this->assertNotNull($ranking->fresh()->generated_path);
    }

    public function test_participation_create_clears_matches_and_ranking(): void
    {
        $this->markSetUpEditionAsLast();
        Sanctum::actingAs($this->manager);
        [$matches, $ranking] = $this->seedPosters();

        $this->postJson('/api/v1/manager/edition-participations', [
            'edition_id' => $this->editionDivision->edition_id,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'player_id' => Player::factory()->create()->id_player,
            'sort_order' => 99,
            'is_collecting_points' => true,
        ])->assertCreated();

        $this->assertGeneratedCleared($matches);
        $this->assertGeneratedCleared($ranking);
    }

    public function test_participation_sort_order_only_does_not_clear(): void
    {
        Sanctum::actingAs($this->manager);
        [$matches, $ranking] = $this->seedPosters();
        $p = $this->participations[0];

        $this->patchJson("/api/v1/manager/edition-participations/{$p->id_participation}", [
            'sort_order' => 50,
        ])->assertOk();

        $this->assertNotNull($matches->fresh()->generated_path);
        $this->assertNotNull($ranking->fresh()->generated_path);
    }

    public function test_scores_do_not_clear_posters(): void
    {
        Sanctum::actingAs($this->manager);
        $edId = $this->editionDivision->id_edition_division;
        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/schedule/generate", [])->assertOk();
        [$matches, $ranking] = $this->seedPosters();

        $match = GameMatch::query()
            ->where('edition_division_id', $edId)
            ->where('match_kind', 'REGULAR')
            ->firstOrFail();

        MatchSide::query()->where('match_id', $match->id_match)->where('side', 'LEFT')->update(['score' => 12]);
        MatchSide::query()->where('match_id', $match->id_match)->where('side', 'RIGHT')->update(['score' => 10]);

        $this->assertNotNull($matches->fresh()->generated_path);
        $this->assertNotNull($ranking->fresh()->generated_path);
    }

    public function test_playing_window_patch_clears_matches_and_ranking(): void
    {
        $this->markSetUpEditionAsLast();
        Sanctum::actingAs($this->manager);
        [$matches, $ranking] = $this->seedPosters();

        $this->patchJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}",
            ['playing_start_time' => '15:00:00']
        )->assertOk();

        $this->assertGeneratedCleared($matches);
        $this->assertGeneratedCleared($ranking);
        $this->assertSame('printed/matches.pdf', $matches->fresh()->printed_path);
        $this->assertSame('printed/ranking.pdf', $ranking->fresh()->printed_path);
    }

    public function test_identity_only_patch_does_not_clear_posters(): void
    {
        Sanctum::actingAs($this->manager);
        [$matches, $ranking] = $this->seedPosters();

        $this->patchJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}",
            [
                'division_title' => 'Renamed Division',
                'playing_start_time' => '14:00:00',
            ]
        )->assertOk();

        $this->assertNotNull($matches->fresh()->generated_path);
        $this->assertNotNull($ranking->fresh()->generated_path);
    }

    public function test_standings_do_not_clear_posters(): void
    {
        Sanctum::actingAs($this->manager);
        [$matches, $ranking] = $this->seedPosters();

        EditionDivisionStanding::create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'participation_id' => $this->participations[0]->id_participation,
            'points_total' => 0,
            'wins' => 0,
            'rank' => 1,
        ]);

        $this->assertNotNull($matches->fresh()->generated_path);
        $this->assertNotNull($ranking->fresh()->generated_path);
    }

    /**
     * @return array{0: EditionDivisionPoster, 1: EditionDivisionPoster}
     */
    private function seedPosters(): array
    {
        $disk = config('filesystems.user_uploads_disk', 'public');
        $matches = $this->makePoster('MATCHES', (int) $this->edCourt->id_edition_division_court, 'matches.pdf');
        $ranking = $this->makePoster('RANKING', null, 'ranking.pdf');

        Storage::disk($disk)->put((string) $matches->generated_path, '%PDF-matches');
        Storage::disk($disk)->put((string) $ranking->generated_path, '%PDF-ranking');
        Storage::disk($disk)->put('printed/matches.pdf', 'printed-matches');
        Storage::disk($disk)->put('printed/ranking.pdf', 'printed-ranking');

        $matches->printed_path = 'printed/matches.pdf';
        $matches->save();
        $ranking->printed_path = 'printed/ranking.pdf';
        $ranking->save();

        return [$matches, $ranking];
    }

    private function makePoster(string $kind, ?int $courtId, string $filename): EditionDivisionPoster
    {
        $poster = new EditionDivisionPoster();
        $poster->edition_id = (int) $this->editionDivision->edition_id;
        $poster->edition_division_id = $this->editionDivision->id_edition_division;
        $poster->edition_division_court_id = $courtId;
        $poster->poster_kind = $kind;
        $poster->inserted_at = time();
        $poster->save();
        $poster->generated_path = 'edition-division-posters/' . $poster->id_edition_division_poster . '/generated/' . $filename;
        $poster->save();

        return $poster;
    }

    private function assertGeneratedCleared(EditionDivisionPoster $poster): void
    {
        $fresh = $poster->fresh();
        $this->assertNull($fresh->generated_path);
        $this->assertFalse(
            Storage::disk(config('filesystems.user_uploads_disk', 'public'))
                ->exists('edition-division-posters/' . $poster->id_edition_division_poster . '/generated/' . basename((string) $poster->generated_path))
        );
    }

    public function test_guard_blocks_clear_generated_for_non_last_edition(): void
    {
        Sanctum::actingAs($this->manager);

        $olderEdition = Edition::factory()->create(['date' => '2100-01-01']);
        Edition::factory()->create(['date' => '2100-06-01']);

        $olderDivision = EditionDivision::factory()->create([
            'edition_id' => $olderEdition->id_edition,
            'division_id' => Division::factory()->create()->id_division,
        ]);

        [$matches, $ranking] = $this->seedPostersForDivision($olderDivision);

        $cleared = (new EditionDivisionPosterInvalidator())->clearGenerated($olderDivision, ['MATCHES', 'RANKING']);

        $this->assertSame(0, $cleared);
        $this->assertNotNull($matches->fresh()->generated_path);
        $this->assertNotNull($ranking->fresh()->generated_path);
    }

    public function test_clear_generated_clears_posters_for_last_edition(): void
    {
        Edition::factory()->create(['date' => '2100-01-01']);
        $lastEdition = Edition::factory()->create(['date' => '2100-06-01']);

        $lastDivision = EditionDivision::factory()->create([
            'edition_id' => $lastEdition->id_edition,
            'division_id' => Division::factory()->create()->id_division,
        ]);

        [$matches, $ranking] = $this->seedPostersForDivision($lastDivision);

        $cleared = (new EditionDivisionPosterInvalidator())->clearGenerated($lastDivision, ['MATCHES', 'RANKING']);

        $this->assertSame(2, $cleared);
        $this->assertGeneratedCleared($matches);
        $this->assertGeneratedCleared($ranking);
    }

    public function test_guard_blocks_clear_attendance_for_non_last_edition(): void
    {
        $olderEdition = Edition::factory()->create(['date' => '2100-01-01']);
        Edition::factory()->create(['date' => '2100-06-01']);

        $poster = $this->seedAttendancePoster((int) $olderEdition->id_edition);

        $cleared = (new EditionDivisionPosterInvalidator())->clearAttendanceForEdition((int) $olderEdition->id_edition);

        $this->assertSame(0, $cleared);
        $this->assertNotNull($poster->fresh()->generated_path);
    }

    public function test_clear_attendance_for_edition_clears_last_edition_poster(): void
    {
        Edition::factory()->create(['date' => '2100-01-01']);
        $lastEdition = Edition::factory()->create(['date' => '2100-06-01']);

        $poster = $this->seedAttendancePoster((int) $lastEdition->id_edition);

        $cleared = (new EditionDivisionPosterInvalidator())->clearAttendanceForEdition((int) $lastEdition->id_edition);

        $this->assertSame(1, $cleared);
        $this->assertNull($poster->fresh()->generated_path);
    }

    public function test_participation_create_on_non_last_edition_does_not_clear_posters(): void
    {
        Sanctum::actingAs($this->manager);

        $olderEdition = Edition::factory()->create(['date' => '2100-01-01']);
        Edition::factory()->create(['date' => '2100-06-01']);

        $olderDivision = EditionDivision::factory()->create([
            'edition_id' => $olderEdition->id_edition,
            'division_id' => Division::factory()->create()->id_division,
            'players_limit' => 8,
        ]);

        [$matches, $ranking] = $this->seedPostersForDivision($olderDivision);

        $this->postJson('/api/v1/manager/edition-participations', [
            'edition_id' => $olderEdition->id_edition,
            'edition_division_id' => $olderDivision->id_edition_division,
            'player_id' => Player::factory()->create()->id_player,
            'sort_order' => 0,
            'is_collecting_points' => true,
        ])->assertCreated();

        $this->assertNotNull($matches->fresh()->generated_path);
        $this->assertNotNull($ranking->fresh()->generated_path);
    }

    /**
     * @return array{0: EditionDivisionPoster, 1: EditionDivisionPoster}
     */
    private function seedPostersForDivision(EditionDivision $editionDivision): array
    {
        $court = Court::factory()->create(['name' => 'Court A']);
        $edCourt = EditionDivisionCourt::factory()->create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'court_id' => $court->id_court,
            'display_order' => 0,
        ]);

        $previousDivision = $this->editionDivision;
        $previousCourt = $this->edCourt;
        $this->editionDivision = $editionDivision;
        $this->edCourt = $edCourt;

        try {
            return $this->seedPosters();
        } finally {
            $this->editionDivision = $previousDivision;
            $this->edCourt = $previousCourt;
        }
    }

    private function seedAttendancePoster(int $editionId): EditionDivisionPoster
    {
        $poster = new EditionDivisionPoster();
        $poster->edition_id = $editionId;
        $poster->edition_division_id = null;
        $poster->poster_kind = 'ATTENDANCE';
        $poster->inserted_at = time();
        $poster->save();
        $poster->generated_path = 'edition-division-posters/' . $poster->id_edition_division_poster . '/generated/attendance.pdf';
        $poster->save();

        Storage::disk(config('filesystems.user_uploads_disk', 'public'))
            ->put((string) $poster->generated_path, '%PDF-attendance');

        return $poster;
    }

    private function markSetUpEditionAsLast(): void
    {
        $this->edition->forceFill(['date' => '2100-12-31'])->save();
    }
}
