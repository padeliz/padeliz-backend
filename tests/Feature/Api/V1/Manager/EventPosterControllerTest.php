<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\PadelEvent;
use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\EventDivisionPoster;
use App\Models\EventParticipation;
use App\Models\Player;
use App\Services\Pdf\AttendancePdfLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\PdfDecompress;
use Tests\TestCase;

final class EventPosterControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private PadelEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.user_uploads_disk', 'public'));

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $this->event = PadelEvent::factory()->create([
            'name' => 'Poster PadelEvent',
            'date' => '2026-06-15',
        ]);
    }

    public function test_merged_file_returns_422_when_no_posters_exist(): void
    {
        Sanctum::actingAs($this->manager);
        EventDivision::factory()->create(['event_id' => $this->event->id_event]);

        $this->getJson("/api/v1/manager/events/{$this->event->id_event}/posters/merged/file")
            ->assertStatus(422)
            ->assertJsonPath('message', 'No generated division posters found for this edition.')
            ->assertJsonPath('event_id', $this->event->id_event);
    }

    public function test_attendance_generate_and_download(): void
    {
        Sanctum::actingAs($this->manager);

        $this->seedEventDivisionsWithParticipations(1);

        $generate = $this->postJson("/api/v1/manager/events/{$this->event->id_event}/posters/attendance");
        $generate->assertOk()
            ->assertJsonPath('message', 'Poster generated.')
            ->assertJsonPath('data.poster_kind', 'ATTENDANCE');
        $generatedPath = $generate->json('data.generated_path');
        $this->assertNotNull($generatedPath);

        $download = $this->get("/api/v1/manager/events/{$this->event->id_event}/posters/attendance/file");
        $download->assertOk();
        $this->assertStringStartsWith('%PDF', $download->streamedContent());
    }

    public function test_attendance_pdf_contains_grid_content(): void
    {
        Sanctum::actingAs($this->manager);

        $seed = $this->seedEventDivisionsWithParticipations(4);

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/posters/attendance")->assertOk();

        $pdfBytes = $this->get("/api/v1/manager/events/{$this->event->id_event}/posters/attendance/file")
            ->streamedContent();

        foreach ($seed as $row) {
            $this->assertTrue(PdfDecompress::containsText($pdfBytes, $row['division_title']));
            $this->assertTrue(PdfDecompress::containsText($pdfBytes, $row['court_name']));
            $this->assertTrue(PdfDecompress::containsText($pdfBytes, $row['player_name']));
        }

        $this->assertGreaterThanOrEqual(1, PdfDecompress::countPages($pdfBytes));
        $this->assertStringStartsWith('%PDF', $pdfBytes);
        $this->assertTrue(PdfDecompress::containsImageOperator($pdfBytes));
        $this->assertTrue(PdfDecompress::containsText($pdfBytes, 'Poster PadelEvent'));
        $this->assertTrue(PdfDecompress::containsText(
            $pdfBytes,
            AttendancePdfLayout::formatOrdinalHeaderDate('2026-06-15')
        ));
    }

    public function test_attendance_pdf_with_six_divisions_contains_content(): void
    {
        Sanctum::actingAs($this->manager);

        $seed = $this->seedEventDivisionsWithParticipations(6);

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/posters/attendance")->assertOk();

        $pdfBytes = $this->get("/api/v1/manager/events/{$this->event->id_event}/posters/attendance/file")
            ->streamedContent();

        $this->assertGreaterThanOrEqual(1, PdfDecompress::countPages($pdfBytes));
        $this->assertStringStartsWith('%PDF', $pdfBytes);
        $this->assertTrue(PdfDecompress::containsText($pdfBytes, $seed[0]['division_title']));
        $this->assertTrue(PdfDecompress::containsText($pdfBytes, $seed[0]['player_name']));
    }

    public function test_attendance_pdf_with_ten_divisions_has_two_pages(): void
    {
        Sanctum::actingAs($this->manager);

        $this->seedEventDivisionsWithParticipations(10);

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/posters/attendance")->assertOk();

        $pdfBytes = $this->get("/api/v1/manager/events/{$this->event->id_event}/posters/attendance/file")
            ->streamedContent();

        $this->assertSame(2, PdfDecompress::countPages($pdfBytes));
        $this->assertStringStartsWith('%PDF', $pdfBytes);
    }

    public function test_attendance_generate_persists_poster_file(): void
    {
        Sanctum::actingAs($this->manager);

        $this->seedEventDivisionsWithParticipations(1);

        $response = $this->postJson("/api/v1/manager/events/{$this->event->id_event}/posters/attendance");
        $generatedPath = $response->json('data.generated_path');

        $this->assertNotNull($generatedPath);
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))->assertExists((string) $generatedPath);
    }

    public function test_attendance_preview_returns_html_with_content(): void
    {
        Sanctum::actingAs($this->manager);

        $seed = $this->seedEventDivisionsWithParticipations(2);

        $response = $this->get("/api/v1/manager/events/{$this->event->id_event}/posters/attendance/preview");

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

        $this->seedEventDivisionsWithParticipations(1);

        $html = $this->get("/api/v1/manager/events/{$this->event->id_event}/posters/attendance/preview")
            ->getContent();

        $this->assertStringContainsString('/storage/', $html);
        $this->assertStringNotContainsString('/workspaces/', $html);
        $this->assertStringNotContainsString('storage/app/public', $html);
    }

    public function test_attendance_preview_does_not_create_attendance_poster_row(): void
    {
        Sanctum::actingAs($this->manager);

        $this->seedEventDivisionsWithParticipations(1);

        $before = EventDivisionPoster::query()
            ->where('event_id', $this->event->id_event)
            ->where('poster_kind', 'ATTENDANCE')
            ->count();

        $this->get("/api/v1/manager/events/{$this->event->id_event}/posters/attendance/preview")->assertOk();

        $after = EventDivisionPoster::query()
            ->where('event_id', $this->event->id_event)
            ->where('poster_kind', 'ATTENDANCE')
            ->count();

        $this->assertSame($before, $after);

        $poster = EventDivisionPoster::query()
            ->where('event_id', $this->event->id_event)
            ->where('poster_kind', 'ATTENDANCE')
            ->first();

        $this->assertNull($poster?->generated_path);
    }

    public function test_attendance_preview_allowed_when_finalized(): void
    {
        Sanctum::actingAs($this->manager);

        $this->seedEventDivisionsWithParticipations(1);
        $this->event->forceFill(['finalized_at' => time()])->save();

        $this->get("/api/v1/manager/events/{$this->event->id_event}/posters/attendance/preview")
            ->assertOk();
    }

    public function test_attendance_preview_requires_authentication(): void
    {
        $this->seedEventDivisionsWithParticipations(1);

        $this->getJson("/api/v1/manager/events/{$this->event->id_event}/posters/attendance/preview")
            ->assertUnauthorized();
    }

    public function test_index_lists_edition_level_posters(): void
    {
        Sanctum::actingAs($this->manager);

        $attendance = new EventDivisionPoster();
        $attendance->event_id = $this->event->id_event;
        $attendance->event_division_id = null;
        $attendance->poster_kind = 'ATTENDANCE';
        $attendance->generated_path = 'event-division-posters/attendance/generated/attendance.pdf';
        $attendance->inserted_at = time();
        $attendance->save();

        $divisionPoster = new EventDivisionPoster();
        $divisionPoster->event_division_id = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
        ])->id_event_division;
        $divisionPoster->poster_kind = 'RANKING';
        $divisionPoster->generated_path = 'event-division-posters/division/generated/ranking.pdf';
        $divisionPoster->inserted_at = time();
        $divisionPoster->save();

        $response = $this->getJson("/api/v1/manager/events/{$this->event->id_event}/posters");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.poster_kind', 'ATTENDANCE')
            ->assertJsonPath('data.0.id_event_division_poster', $attendance->id_event_division_poster);
    }

    public function test_finalized_edition_blocks_attendance_generate_but_allows_download(): void
    {
        Sanctum::actingAs($this->manager);

        $attendance = new EventDivisionPoster();
        $attendance->event_id = $this->event->id_event;
        $attendance->event_division_id = null;
        $attendance->poster_kind = 'ATTENDANCE';
        $attendance->inserted_at = time();
        $attendance->save();
        $attendance->generated_path = 'event-division-posters/' . $attendance->id_event_division_poster . '/generated/attendance.pdf';
        $attendance->save();

        Storage::disk(config('filesystems.user_uploads_disk', 'public'))
            ->put((string) $attendance->generated_path, '%PDF-attendance');

        $this->event->forceFill(['finalized_at' => time()])->save();

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/posters/attendance")
            ->assertStatus(409)
            ->assertJsonPath('message', 'PadelEvent is finalized.');

        $download = $this->get("/api/v1/manager/events/{$this->event->id_event}/posters/attendance/file");
        $download->assertOk();
        $this->assertSame('%PDF-attendance', $download->streamedContent());
    }

    /**
     * @return list<array{division_title: string, court_name: string, player_name: string}>
     */
    private function seedEventDivisionsWithParticipations(int $count): array
    {
        $seed = [];

        for ($i = 0; $i < $count; $i++) {
            $division = Division::factory()->create();
            $divisionTitle = 'Division ' . ($i + 1);
            $courtName = 'Court ' . ($i + 1);
            $eventDivision = EventDivision::factory()->create([
                'event_id' => $this->event->id_event,
                'division_id' => $division->id_division,
                'division_title' => $divisionTitle,
                'skill_order' => $i,
            ]);

            $court = Court::factory()->create(['name' => $courtName]);
            EventDivisionCourt::factory()->create([
                'event_division_id' => $eventDivision->id_event_division,
                'court_id' => $court->id_court,
                'display_order' => 0,
            ]);

            $player = Player::factory()->create([
                'first_name' => 'Player',
                'middle_name' => null,
                'last_name' => (string) ($i + 1),
            ]);

            EventParticipation::factory()->create([
                'event_id' => $this->event->id_event,
                'event_division_id' => $eventDivision->id_event_division,
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
