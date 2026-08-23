<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Models\Court;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionDivisionPoster;
use App\Models\Player;
use App\Services\Pdf\EditionMergedPosterPdfException;
use App\Services\Pdf\EditionMergedPosterPdfService;
use App\Services\Pdf\PosterPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class EditionMergedPosterPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    private EditionMergedPosterPdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $this->service = new EditionMergedPosterPdfService();
    }

    public function test_collect_merge_files_follows_division_and_court_order(): void
    {
        $edition = Edition::factory()->create();
        $divisionLow = Division::factory()->create(['name' => 'Low']);
        $divisionHigh = Division::factory()->create(['name' => 'High']);

        $edLow = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $divisionLow->id_division,
            'skill_order' => 1,
        ]);
        $edHigh = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $divisionHigh->id_division,
            'skill_order' => 2,
        ]);

        $courtB = Court::factory()->create(['name' => 'Court B']);
        $courtA = Court::factory()->create(['name' => 'Court A']);
        $edCourtLowB = EditionDivisionCourt::factory()->create([
            'edition_division_id' => $edLow->id_edition_division,
            'court_id' => $courtB->id_court,
            'display_order' => 1,
        ]);
        $edCourtLowA = EditionDivisionCourt::factory()->create([
            'edition_division_id' => $edLow->id_edition_division,
            'court_id' => $courtA->id_court,
            'display_order' => 0,
        ]);
        $edCourtHigh = EditionDivisionCourt::factory()->create([
            'edition_division_id' => $edHigh->id_edition_division,
            'court_id' => $courtA->id_court,
            'display_order' => 0,
        ]);

        $this->seedPoster($edLow, 'RANKING', null, 'low-ranking.pdf');
        $this->seedPoster($edLow, 'MATCHES', (int) $edCourtLowA->id_edition_division_court, 'low-a.pdf');
        $this->seedPoster($edLow, 'MATCHES', (int) $edCourtLowB->id_edition_division_court, 'low-b.pdf');
        $this->seedPoster($edHigh, 'RANKING', null, 'high-ranking.pdf');
        $this->seedPoster($edHigh, 'MATCHES', (int) $edCourtHigh->id_edition_division_court, 'high-a.pdf');

        $files = $this->service->collectMergeFiles($edition->fresh());

        $this->assertSame(
            ['RANKING', 'MATCHES', 'MATCHES', 'RANKING', 'MATCHES'],
            array_column($files, 'poster_kind')
        );
        $this->assertSame(
            [
                (int) $edLow->id_edition_division,
                (int) $edLow->id_edition_division,
                (int) $edLow->id_edition_division,
                (int) $edHigh->id_edition_division,
                (int) $edHigh->id_edition_division,
            ],
            array_column($files, 'edition_division_id')
        );
        $this->assertSame(
            [null, (int) $edCourtLowA->id_edition_division_court, (int) $edCourtLowB->id_edition_division_court, null, (int) $edCourtHigh->id_edition_division_court],
            array_column($files, 'court_id')
        );
    }

    public function test_collect_merge_files_skips_posters_without_generated_path(): void
    {
        $edition = Edition::factory()->create();
        $ed = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);

        $poster = new EditionDivisionPoster();
        $poster->edition_division_id = $ed->id_edition_division;
        $poster->poster_kind = 'RANKING';
        $poster->inserted_at = time();
        $poster->save();

        $this->seedPoster($ed, 'RANKING', null, 'present.pdf');

        $files = $this->service->collectMergeFiles($edition->fresh());

        $this->assertCount(1, $files);
        $this->assertStringContainsString('present.pdf', $files[0]['path']);
    }

    public function test_merge_for_edition_throws_when_no_files(): void
    {
        $edition = Edition::factory()->create();
        EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);

        try {
            $this->service->mergeForEdition($edition->fresh());
            $this->fail('Expected EditionMergedPosterPdfException was not thrown.');
        } catch (EditionMergedPosterPdfException $e) {
            $this->assertSame(422, $e->statusCode());
            $this->assertSame('No generated division posters found for this edition.', $e->getMessage());
            $this->assertSame((int) $edition->id_edition, $e->payload()['edition_id']);
            $this->assertSame([], $e->payload()['files_attempted']);
        }
    }

    public function test_collect_merge_files_excludes_attendance_posters(): void
    {
        $edition = Edition::factory()->create();
        $ed = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);
        $this->seedPoster($ed, 'RANKING', null, 'ranking.pdf');

        $attendance = new EditionDivisionPoster();
        $attendance->edition_id = $edition->id_edition;
        $attendance->edition_division_id = null;
        $attendance->poster_kind = 'ATTENDANCE';
        $attendance->generated_path = 'edition-division-posters/attendance/generated/attendance.pdf';
        $attendance->inserted_at = time();
        $attendance->save();

        $disk = config('filesystems.user_uploads_disk', 'public');
        Storage::disk($disk)->put((string) $attendance->generated_path, $this->minimalPdfBytes());

        $files = $this->service->collectMergeFiles($edition->fresh());

        $this->assertCount(1, $files);
        $this->assertSame('RANKING', $files[0]['poster_kind']);
    }

    public function test_merge_for_edition_merges_valid_pdfs(): void
    {
        $edition = Edition::factory()->create(['name' => 'Merge Test']);
        $ed = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);
        $this->seedPoster($ed, 'RANKING', null, 'ranking.pdf');

        $result = $this->service->mergeForEdition($edition->fresh());

        $this->assertSame(1, $result['files_merged']);
        $this->assertSame(1, $result['page_count']);
        $this->assertStringStartsWith('%PDF', $result['bytes']);
        $this->assertStringContainsString('Merge Test', $result['filename']);
    }

    private function seedPoster(
        EditionDivision $editionDivision,
        string $kind,
        ?int $courtId,
        string $filename,
    ): EditionDivisionPoster {
        $poster = new EditionDivisionPoster();
        $poster->edition_division_id = $editionDivision->id_edition_division;
        $poster->edition_division_court_id = $courtId;
        $poster->poster_kind = $kind;
        $poster->inserted_at = time();
        $poster->save();
        $poster->generated_path = 'edition-division-posters/' . $poster->id_edition_division_poster . '/generated/' . $filename;
        $poster->save();

        Storage::disk(config('filesystems.user_uploads_disk', 'public'))
            ->put((string) $poster->generated_path, $this->minimalPdfBytes());

        return $poster;
    }

    private function minimalPdfBytes(): string
    {
        static $bytes = null;
        if ($bytes !== null) {
            return $bytes;
        }

        $players = Player::factory()->count(4)->create();
        $bytes = (new PosterPdfService())->generateRankingFromScratch([
            'player_ids' => $players->pluck('id_player')->all(),
            'court_names' => ['Court 1'],
            'time_start' => '14:00',
            'time_end' => '17:00',
            'opponents_per_player' => 3,
            'repeat_partners' => 1,
            'title' => 'Merge Fixture',
            'organizer_id' => null,
            'club_id' => null,
        ])->bytes;

        return $bytes;
    }
}
