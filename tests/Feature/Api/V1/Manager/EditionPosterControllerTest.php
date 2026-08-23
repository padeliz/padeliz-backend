<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionDivisionPoster;
use App\Models\EditionParticipation;
use App\Models\Player;
use App\Services\Pdf\AttendancePdfLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\PdfDecompress;
use Tests\TestCase;

final class EditionPosterControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.user_uploads_disk', 'public'));

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $this->edition = Edition::factory()->create([
            'name' => 'Poster Edition',
            'date' => '2026-06-15',
        ]);
    }

    public function test_merged_file_returns_422_when_no_posters_exist(): void
    {
        Sanctum::actingAs($this->manager);
        EditionDivision::factory()->create(['edition_id' => $this->edition->id_edition]);

        $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/posters/merged/file")
            ->assertStatus(422)
            ->assertJsonPath('message', 'No generated division posters found for this edition.')
            ->assertJsonPath('edition_id', $this->edition->id_edition);
    }

    public function test_attendance_generate_and_download(): void
    {
        Sanctum::actingAs($this->manager);

        $this->seedEditionDivisionsWithParticipations(1);

        $generate = $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance");
        $generate->assertOk()
            ->assertJsonPath('message', 'Poster generated.')
            ->assertJsonPath('data.poster_kind', 'ATTENDANCE');
        $generatedPath = $generate->json('data.generated_path');
        $this->assertNotNull($generatedPath);

        $download = $this->get("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance/file");
        $download->assertOk();
        $this->assertStringStartsWith('%PDF', $download->streamedContent());
    }

    public function test_attendance_pdf_contains_grid_content(): void
    {
        Sanctum::actingAs($this->manager);

        $seed = $this->seedEditionDivisionsWithParticipations(4);

        $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance")->assertOk();

        $pdfBytes = $this->get("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance/file")
            ->streamedContent();

        foreach ($seed as $row) {
            $this->assertTrue(PdfDecompress::containsText($pdfBytes, $row['division_title']));
            $this->assertTrue(PdfDecompress::containsText($pdfBytes, $row['court_name']));
            $this->assertTrue(PdfDecompress::containsText($pdfBytes, $row['player_name']));
        }

        $this->assertGreaterThanOrEqual(1, PdfDecompress::countPages($pdfBytes));
        $this->assertStringStartsWith('%PDF', $pdfBytes);
        $this->assertTrue(PdfDecompress::containsImageOperator($pdfBytes));
        $this->assertTrue(PdfDecompress::containsText($pdfBytes, 'Poster Edition'));
        $this->assertTrue(PdfDecompress::containsText(
            $pdfBytes,
            AttendancePdfLayout::formatOrdinalHeaderDate('2026-06-15')
        ));
    }

    public function test_attendance_pdf_with_six_divisions_contains_content(): void
    {
        Sanctum::actingAs($this->manager);

        $seed = $this->seedEditionDivisionsWithParticipations(6);

        $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance")->assertOk();

        $pdfBytes = $this->get("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance/file")
            ->streamedContent();

        $this->assertGreaterThanOrEqual(1, PdfDecompress::countPages($pdfBytes));
        $this->assertStringStartsWith('%PDF', $pdfBytes);
        $this->assertTrue(PdfDecompress::containsText($pdfBytes, $seed[0]['division_title']));
        $this->assertTrue(PdfDecompress::containsText($pdfBytes, $seed[0]['player_name']));
    }

    public function test_attendance_pdf_with_ten_divisions_has_two_pages(): void
    {
        Sanctum::actingAs($this->manager);

        $this->seedEditionDivisionsWithParticipations(10);

        $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance")->assertOk();

        $pdfBytes = $this->get("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance/file")
            ->streamedContent();

        $this->assertSame(2, PdfDecompress::countPages($pdfBytes));
        $this->assertStringStartsWith('%PDF', $pdfBytes);
    }

    public function test_attendance_generate_persists_poster_file(): void
    {
        Sanctum::actingAs($this->manager);

        $this->seedEditionDivisionsWithParticipations(1);

        $response = $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance");
        $generatedPath = $response->json('data.generated_path');

        $this->assertNotNull($generatedPath);
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))->assertExists((string) $generatedPath);
    }

    public function test_attendance_preview_returns_html_with_content(): void
    {
        Sanctum::actingAs($this->manager);

        $seed = $this->seedEditionDivisionsWithParticipations(2);

        $response = $this->get("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance/preview");

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $html = $response->getContent();
        $this->assertStringContainsString($seed[0]['division_title'], $html);
        $this->assertStringContainsString($seed[0]['player_name'], $html);
        $this->assertStringContainsString('class="checkbox-box"', $html);
        $this->assertStringContainsString('class="color-swatch"', $html);
    }

    public function test_attendance_preview_uses_public_avatar_urls(): void
    {
        Sanctum::actingAs($this->manager);

        $this->seedEditionDivisionsWithParticipations(1);

        $html = $this->get("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance/preview")
            ->getContent();

        $this->assertStringContainsString('/storage/', $html);
        $this->assertStringNotContainsString('/workspaces/', $html);
        $this->assertStringNotContainsString('storage/app/public', $html);
    }

    public function test_attendance_preview_does_not_create_attendance_poster_row(): void
    {
        Sanctum::actingAs($this->manager);

        $this->seedEditionDivisionsWithParticipations(1);

        $before = EditionDivisionPoster::query()
            ->where('edition_id', $this->edition->id_edition)
            ->where('poster_kind', 'ATTENDANCE')
            ->count();

        $this->get("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance/preview")->assertOk();

        $after = EditionDivisionPoster::query()
            ->where('edition_id', $this->edition->id_edition)
            ->where('poster_kind', 'ATTENDANCE')
            ->count();

        $this->assertSame($before, $after);

        $poster = EditionDivisionPoster::query()
            ->where('edition_id', $this->edition->id_edition)
            ->where('poster_kind', 'ATTENDANCE')
            ->first();

        $this->assertNull($poster?->generated_path);
    }

    public function test_attendance_preview_allowed_when_finalized(): void
    {
        Sanctum::actingAs($this->manager);

        $this->seedEditionDivisionsWithParticipations(1);
        $this->edition->forceFill(['finalized_at' => time()])->save();

        $this->get("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance/preview")
            ->assertOk();
    }

    public function test_attendance_preview_requires_authentication(): void
    {
        $this->seedEditionDivisionsWithParticipations(1);

        $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance/preview")
            ->assertUnauthorized();
    }

    public function test_index_lists_edition_level_posters(): void
    {
        Sanctum::actingAs($this->manager);

        $attendance = new EditionDivisionPoster();
        $attendance->edition_id = $this->edition->id_edition;
        $attendance->edition_division_id = null;
        $attendance->poster_kind = 'ATTENDANCE';
        $attendance->generated_path = 'edition-division-posters/attendance/generated/attendance.pdf';
        $attendance->inserted_at = time();
        $attendance->save();

        $divisionPoster = new EditionDivisionPoster();
        $divisionPoster->edition_division_id = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
        ])->id_edition_division;
        $divisionPoster->poster_kind = 'RANKING';
        $divisionPoster->generated_path = 'edition-division-posters/division/generated/ranking.pdf';
        $divisionPoster->inserted_at = time();
        $divisionPoster->save();

        $response = $this->getJson("/api/v1/manager/editions/{$this->edition->id_edition}/posters");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.poster_kind', 'ATTENDANCE')
            ->assertJsonPath('data.0.id_edition_division_poster', $attendance->id_edition_division_poster);
    }

    public function test_finalized_edition_blocks_attendance_generate_but_allows_download(): void
    {
        Sanctum::actingAs($this->manager);

        $attendance = new EditionDivisionPoster();
        $attendance->edition_id = $this->edition->id_edition;
        $attendance->edition_division_id = null;
        $attendance->poster_kind = 'ATTENDANCE';
        $attendance->inserted_at = time();
        $attendance->save();
        $attendance->generated_path = 'edition-division-posters/' . $attendance->id_edition_division_poster . '/generated/attendance.pdf';
        $attendance->save();

        Storage::disk(config('filesystems.user_uploads_disk', 'public'))
            ->put((string) $attendance->generated_path, '%PDF-attendance');

        $this->edition->forceFill(['finalized_at' => time()])->save();

        $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Edition is finalized.');

        $download = $this->get("/api/v1/manager/editions/{$this->edition->id_edition}/posters/attendance/file");
        $download->assertOk();
        $this->assertSame('%PDF-attendance', $download->streamedContent());
    }

    /**
     * @return list<array{division_title: string, court_name: string, player_name: string}>
     */
    private function seedEditionDivisionsWithParticipations(int $count): array
    {
        $seed = [];

        for ($i = 0; $i < $count; $i++) {
            $division = Division::factory()->create();
            $divisionTitle = 'Division ' . ($i + 1);
            $courtName = 'Court ' . ($i + 1);
            $editionDivision = EditionDivision::factory()->create([
                'edition_id' => $this->edition->id_edition,
                'division_id' => $division->id_division,
                'division_title' => $divisionTitle,
                'skill_order' => $i,
            ]);

            $court = Court::factory()->create(['name' => $courtName]);
            EditionDivisionCourt::factory()->create([
                'edition_division_id' => $editionDivision->id_edition_division,
                'court_id' => $court->id_court,
                'display_order' => 0,
            ]);

            $player = Player::factory()->create([
                'first_name' => 'Player',
                'middle_name' => null,
                'last_name' => (string) ($i + 1),
            ]);

            EditionParticipation::factory()->create([
                'edition_id' => $this->edition->id_edition,
                'edition_division_id' => $editionDivision->id_edition_division,
                'player_id' => $player->id_player,
                'is_collecting_points' => true,
            ]);

            $seed[] = [
                'division_title' => $divisionTitle,
                'court_name' => $courtName,
                'player_name' => $player->name,
            ];
        }

        return $seed;
    }
}
