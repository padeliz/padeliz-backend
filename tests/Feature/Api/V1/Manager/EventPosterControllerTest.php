<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\EventDivisionPoster;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
use App\Models\User;
use App\Services\Pdf\PosterPdfResult;
use App\Services\Pdf\PosterPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

final class EventPosterControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private PadelEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.user_uploads_disk', 'public'));

        $this->manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $this->event = PadelEvent::factory()->create([
            'name' => 'Poster PadelEvent',
            ...UnixEventFactory::eventBounds('2026-06-15'),
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
        $poster = $this->mockAttendanceGenerate('%PDF-attendance-gen');

        $generate = $this->postJson("/api/v1/manager/events/{$this->event->id_event}/posters/attendance");
        $generate->assertOk()
            ->assertJsonPath('message', 'Poster generated.')
            ->assertJsonPath('data.poster_kind', 'ATTENDANCE')
            ->assertJsonPath('data.id_event_division_poster', $poster->id_event_division_poster);
        $this->assertNotNull($generate->json('data.generated_path'));

        $download = $this->get("/api/v1/manager/events/{$this->event->id_event}/posters/attendance/file");
        $download->assertOk();
        $this->assertSame('%PDF-attendance-gen', $download->streamedContent());
    }

    public function test_attendance_generate_persists_poster_file(): void
    {
        Sanctum::actingAs($this->manager);

        $this->seedEventDivisionsWithParticipations(1);
        $poster = $this->mockAttendanceGenerate('%PDF-persisted');

        $response = $this->postJson("/api/v1/manager/events/{$this->event->id_event}/posters/attendance");
        $generatedPath = $response->json('data.generated_path');

        $this->assertNotNull($generatedPath);
        $this->assertSame($poster->generated_path, $generatedPath);
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))->assertExists((string) $generatedPath);
    }

    public function test_attendance_preview_returns_html_with_content(): void
    {
        Sanctum::actingAs($this->manager);

        $seed = $this->seedEventDivisionsWithParticipations(2);
        $html = '<html><body>'
            . '<div class="checkbox-box"></div><div class="color-swatch"></div>'
            . e($seed[0]['division_title']) . ' ' . e($seed[0]['player_name'])
            . '</body></html>';

        $this->mock(PosterPdfService::class, function ($mock) use ($html): void {
            $mock->shouldReceive('renderAttendanceHtmlForEdition')
                ->once()
                ->andReturn($html);
        });

        $response = $this->get("/api/v1/manager/events/{$this->event->id_event}/posters/attendance/preview");

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertStringContainsString($seed[0]['division_title'], $body);
        $this->assertStringContainsString($seed[0]['player_name'], $body);
        $this->assertStringContainsString('class="checkbox-box"', $body);
        $this->assertStringContainsString('class="color-swatch"', $body);
    }

    public function test_attendance_preview_uses_public_avatar_urls(): void
    {
        Sanctum::actingAs($this->manager);

        $this->seedEventDivisionsWithParticipations(1);
        $this->mock(PosterPdfService::class, function ($mock): void {
            $mock->shouldReceive('renderAttendanceHtmlForEdition')
                ->once()
                ->andReturn('<img src="/storage/cached-pdf-player-images/1/picture/avatar.png"/>');
        });

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
        $this->mock(PosterPdfService::class, function ($mock): void {
            $mock->shouldReceive('renderAttendanceHtmlForEdition')
                ->once()
                ->andReturn('<html></html>');
        });

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
        $this->mock(PosterPdfService::class, function ($mock): void {
            $mock->shouldReceive('renderAttendanceHtmlForEdition')
                ->once()
                ->andReturn('<html>preview</html>');
        });

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

    private function mockAttendanceGenerate(string $pdfBytes): EventDivisionPoster
    {
        $poster = new EventDivisionPoster();
        $poster->event_id = $this->event->id_event;
        $poster->event_division_id = null;
        $poster->poster_kind = 'ATTENDANCE';
        $poster->inserted_at = time();
        $poster->save();
        $poster->generated_path = 'event-division-posters/' . $poster->id_event_division_poster . '/generated/attendance.pdf';
        $poster->save();

        Storage::disk(config('filesystems.user_uploads_disk', 'public'))
            ->put((string) $poster->generated_path, $pdfBytes);

        $this->mock(PosterPdfService::class, function ($mock) use ($poster, $pdfBytes): void {
            $mock->shouldReceive('generateAttendanceForEdition')
                ->once()
                ->andReturn(new PosterPdfResult($pdfBytes, 'attendance.pdf', $poster->fresh()));
        });

        return $poster->fresh();
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

            $player = User::factory()->create([
                'first_name' => 'Player',
                'middle_name' => null,
                'last_name' => (string) ($i + 1),
            ]);

            EventParticipation::factory()->create([
                'event_id' => $this->event->id_event,
                'event_division_id' => $eventDivision->id_event_division,
                'player_user_id' => $player->id_user,
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
