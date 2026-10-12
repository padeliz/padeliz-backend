<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Pdf;

use App\Models\EventParticipation;
use App\Models\User;
use App\Services\Pdf\PosterPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ManagerEventFixtures;
use Tests\Support\UnixEventFactory;
use Tests\TestCase;

/**
 * Thin real mPDF smoke coverage. HTTP Feature tests mock PosterPdfService.
 */
final class PosterPdfServiceSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_attendance_for_edition_returns_pdf_bytes(): void
    {
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));

        $event = ManagerEventFixtures::event([
            'name' => 'Smoke Attendance',
            ...UnixEventFactory::eventBounds('2026-06-15'),
        ]);
        $eventDivision = ManagerEventFixtures::eventDivisionFor($event, [
            'division_title' => 'Open',
            'skill_order' => 0,
        ]);

        foreach (User::factory()->count(2)->create() as $i => $player) {
            EventParticipation::factory()->forEventDivision($eventDivision)->create([
                'player_user_id' => $player->id_user,
                'is_collecting_points' => true,
                'absence_reason' => null,
                'sort_order' => $i,
            ]);
        }

        $result = app(PosterPdfService::class)->generateAttendanceForEdition($event->fresh());

        $this->assertNotSame('', $result->bytes);
        $this->assertStringStartsWith('%PDF', $result->bytes);
        $this->assertNotNull($result->poster);
        $this->assertSame('ATTENDANCE', $result->poster->poster_kind);
    }
}
