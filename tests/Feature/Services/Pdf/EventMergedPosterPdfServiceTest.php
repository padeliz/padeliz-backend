<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Pdf;

use App\Models\Court;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\EventDivisionPoster;
use App\Models\PadelEvent;
use App\Services\Pdf\EventMergedPosterPdfException;
use App\Services\Pdf\EventMergedPosterPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PdfFixtures;
use Tests\TestCase;

final class EventMergedPosterPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventMergedPosterPdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $this->service = new EventMergedPosterPdfService();
    }

    public function test_collect_merge_files_follows_division_and_court_order(): void
    {
        $event = PadelEvent::factory()->create();
        $divisionLow = Division::factory()->create(['name' => 'Low']);
        $divisionHigh = Division::factory()->create(['name' => 'High']);

        $edLow = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $divisionLow->id_division,
            'skill_order' => 1,
        ]);
        $edHigh = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $divisionHigh->id_division,
            'skill_order' => 2,
        ]);

        $courtB = Court::factory()->create(['name' => 'Court B']);
        $courtA = Court::factory()->create(['name' => 'Court A']);
        $edCourtLowB = EventDivisionCourt::factory()->create([
            'event_division_id' => $edLow->id_event_division,
            'court_id' => $courtB->id_court,
            'display_order' => 1,
        ]);
        $edCourtLowA = EventDivisionCourt::factory()->create([
            'event_division_id' => $edLow->id_event_division,
            'court_id' => $courtA->id_court,
            'display_order' => 0,
        ]);
        $edCourtHigh = EventDivisionCourt::factory()->create([
            'event_division_id' => $edHigh->id_event_division,
            'court_id' => $courtA->id_court,
            'display_order' => 0,
        ]);

        $this->seedPoster($edLow, 'RANKING', null, 'low-ranking.pdf');
        $this->seedPoster($edLow, 'MATCHES', (int) $edCourtLowA->id_event_division_court, 'low-a.pdf');
        $this->seedPoster($edLow, 'MATCHES', (int) $edCourtLowB->id_event_division_court, 'low-b.pdf');
        $this->seedPoster($edHigh, 'RANKING', null, 'high-ranking.pdf');
        $this->seedPoster($edHigh, 'MATCHES', (int) $edCourtHigh->id_event_division_court, 'high-a.pdf');

        $files = $this->service->collectMergeFiles($event->fresh());

        $this->assertSame(
            ['RANKING', 'MATCHES', 'MATCHES', 'RANKING', 'MATCHES'],
            array_column($files, 'poster_kind')
        );
        $this->assertSame(
            [
                (int) $edLow->id_event_division,
                (int) $edLow->id_event_division,
                (int) $edLow->id_event_division,
                (int) $edHigh->id_event_division,
                (int) $edHigh->id_event_division,
            ],
            array_column($files, 'event_division_id')
        );
        $this->assertSame(
            [null, (int) $edCourtLowA->id_event_division_court, (int) $edCourtLowB->id_event_division_court, null, (int) $edCourtHigh->id_event_division_court],
            array_column($files, 'court_id')
        );
    }

    public function test_collect_merge_files_skips_posters_without_generated_path(): void
    {
        $event = PadelEvent::factory()->create();
        $ed = EventDivision::factory()->create(['event_id' => $event->id_event]);

        $poster = new EventDivisionPoster();
        $poster->event_division_id = $ed->id_event_division;
        $poster->poster_kind = 'RANKING';
        $poster->inserted_at = time();
        $poster->save();

        $this->seedPoster($ed, 'RANKING', null, 'present.pdf');

        $files = $this->service->collectMergeFiles($event->fresh());

        $this->assertCount(1, $files);
        $this->assertStringContainsString('present.pdf', $files[0]['path']);
    }

    public function test_merge_for_edition_throws_when_no_files(): void
    {
        $event = PadelEvent::factory()->create();
        EventDivision::factory()->create(['event_id' => $event->id_event]);

        try {
            $this->service->mergeForEdition($event->fresh());
            $this->fail('Expected EventMergedPosterPdfException was not thrown.');
        } catch (EventMergedPosterPdfException $e) {
            $this->assertSame(422, $e->statusCode());
            $this->assertSame('No generated division posters found for this edition.', $e->getMessage());
            $this->assertSame((int) $event->id_event, $e->payload()['event_id']);
            $this->assertSame([], $e->payload()['files_attempted']);
        }
    }

    public function test_collect_merge_files_excludes_attendance_posters(): void
    {
        $event = PadelEvent::factory()->create();
        $ed = EventDivision::factory()->create(['event_id' => $event->id_event]);
        $this->seedPoster($ed, 'RANKING', null, 'ranking.pdf');

        $attendance = new EventDivisionPoster();
        $attendance->event_id = $event->id_event;
        $attendance->event_division_id = null;
        $attendance->poster_kind = 'ATTENDANCE';
        $attendance->generated_path = 'event-division-posters/attendance/generated/attendance.pdf';
        $attendance->inserted_at = time();
        $attendance->save();

        $disk = config('filesystems.user_uploads_disk', 'public');
        Storage::disk($disk)->put((string) $attendance->generated_path, PdfFixtures::minimalPdfBytes());

        $files = $this->service->collectMergeFiles($event->fresh());

        $this->assertCount(1, $files);
        $this->assertSame('RANKING', $files[0]['poster_kind']);
    }

    public function test_merge_for_edition_merges_valid_pdfs(): void
    {
        $event = PadelEvent::factory()->create(['name' => 'Merge Test']);
        $ed = EventDivision::factory()->create(['event_id' => $event->id_event]);
        $this->seedPoster($ed, 'RANKING', null, 'ranking.pdf');

        $result = $this->service->mergeForEdition($event->fresh());

        $this->assertSame(1, $result['files_merged']);
        $this->assertSame(1, $result['page_count']);
        $this->assertStringStartsWith('%PDF', $result['bytes']);
        $this->assertStringContainsString('Merge Test', $result['filename']);
    }

    private function seedPoster(
        EventDivision $eventDivision,
        string $kind,
        ?int $courtId,
        string $filename,
    ): EventDivisionPoster {
        $poster = new EventDivisionPoster();
        $poster->event_division_id = $eventDivision->id_event_division;
        $poster->event_division_court_id = $courtId;
        $poster->poster_kind = $kind;
        $poster->inserted_at = time();
        $poster->save();
        $poster->generated_path = 'event-division-posters/' . $poster->id_event_division_poster . '/generated/' . $filename;
        $poster->save();

        Storage::disk(config('filesystems.user_uploads_disk', 'public'))
            ->put((string) $poster->generated_path, PdfFixtures::minimalPdfBytes());

        return $poster;
    }
}
