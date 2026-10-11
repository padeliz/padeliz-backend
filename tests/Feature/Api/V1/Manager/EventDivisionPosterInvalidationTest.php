<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\EventDivisionPoster;
use App\Models\EventDivisionSchedule;
use App\Models\EventDivisionStanding;
use App\Models\EventParticipation;
use App\Models\GameMatch;
use App\Models\MatchSide;
use App\Models\PadelEvent;
use App\Models\User;
use App\Services\Pdf\EventDivisionPosterInvalidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

final class EventDivisionPosterInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private PadelEvent $event;

    private EventDivision $eventDivision;

    private EventDivisionCourt $edCourt;

    /** @var list<EventParticipation> */
    private array $participations = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.user_uploads_disk', 'public'));

        $this->manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $this->event = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2000-01-01'));
        $division = Division::factory()->create();

        $this->eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'players_limit' => 8,
            ...UnixEventFactory::playingBounds($this->event, '14:00:00', '17:00:00'),
        ]);

        EventDivisionSchedule::factory()->create([
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_opponents_nr' => 4,
            'player_matches_repeat' => 1,
            'include_final' => false,
            'has_demonstrative_match' => false,
            'include_scores' => true,
            'allow_replacements' => true,
        ]);

        $court = Court::factory()->create(['name' => 'Court A']);
        $this->edCourt = EventDivisionCourt::factory()->create([
            'event_division_id' => $this->eventDivision->id_event_division,
            'court_id' => $court->id_court,
            'display_order' => 0,
        ]);

        for ($i = 0; $i < 8; $i++) {
            $this->participations[] = EventParticipation::factory()->create([
                'event_id' => $this->event->id_event,
                'event_division_id' => $this->eventDivision->id_event_division,
                'player_user_id' => User::factory()->create()->id_user,
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
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/generate",
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
        $edId = $this->eventDivision->id_event_division;
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/schedule/generate", [])->assertOk();

        [$matches, $ranking] = $this->seedPosters();

        $this->deleteJson("/api/v1/manager/event-divisions/{$edId}/schedule")->assertOk();

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
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/form",
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
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/form",
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
            static fn (EventParticipation $p): int => (int) $p->id_participation,
            array_reverse($this->participations)
        );

        $this->putJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/form",
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

        $this->postJson('/api/v1/manager/event-participations', [
            'event_id' => $this->eventDivision->event_id,
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_user_id' => User::factory()->create()->id_user,
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

        $this->patchJson("/api/v1/manager/event-participations/{$p->id_participation}", [
            'sort_order' => 50,
        ])->assertOk();

        $this->assertNotNull($matches->fresh()->generated_path);
        $this->assertNotNull($ranking->fresh()->generated_path);
    }

    public function test_scores_do_not_clear_posters(): void
    {
        Sanctum::actingAs($this->manager);
        $edId = $this->eventDivision->id_event_division;
        $this->postJson("/api/v1/manager/event-divisions/{$edId}/schedule/generate", [])->assertOk();
        [$matches, $ranking] = $this->seedPosters();

        $match = GameMatch::query()
            ->where('event_division_id', $edId)
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

        $event = PadelEvent::query()->findOrFail($this->eventDivision->event_id);
        $window = \Tests\Support\UnixEventFactory::playingBounds($event, '15:00:00', '17:00:00');
        $this->patchJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}",
            $window
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
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}",
            [
                'division_title' => 'Renamed Division',
            ]
        )->assertOk();

        $this->assertNotNull($matches->fresh()->generated_path);
        $this->assertNotNull($ranking->fresh()->generated_path);
    }

    public function test_standings_do_not_clear_posters(): void
    {
        Sanctum::actingAs($this->manager);
        [$matches, $ranking] = $this->seedPosters();

        EventDivisionStanding::create([
            'event_division_id' => $this->eventDivision->id_event_division,
            'participation_id' => $this->participations[0]->id_participation,
            'points_total' => 0,
            'wins' => 0,
            'rank' => 1,
        ]);

        $this->assertNotNull($matches->fresh()->generated_path);
        $this->assertNotNull($ranking->fresh()->generated_path);
    }

    /**
     * @return array{0: EventDivisionPoster, 1: EventDivisionPoster}
     */
    private function seedPosters(): array
    {
        $disk = config('filesystems.user_uploads_disk', 'public');
        $matches = $this->makePoster('MATCHES', (int) $this->edCourt->id_event_division_court, 'matches.pdf');
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

    private function makePoster(string $kind, ?int $courtId, string $filename): EventDivisionPoster
    {
        $poster = new EventDivisionPoster();
        $poster->event_id = (int) $this->eventDivision->event_id;
        $poster->event_division_id = $this->eventDivision->id_event_division;
        $poster->event_division_court_id = $courtId;
        $poster->poster_kind = $kind;
        $poster->inserted_at = time();
        $poster->save();
        $poster->generated_path = 'event-division-posters/' . $poster->id_event_division_poster . '/generated/' . $filename;
        $poster->save();

        return $poster;
    }

    private function assertGeneratedCleared(EventDivisionPoster $poster): void
    {
        $fresh = $poster->fresh();
        $this->assertNull($fresh->generated_path);
        $this->assertFalse(
            Storage::disk(config('filesystems.user_uploads_disk', 'public'))
                ->exists('event-division-posters/' . $poster->id_event_division_poster . '/generated/' . basename((string) $poster->generated_path))
        );
    }

    public function test_guard_blocks_clear_generated_for_non_last_edition(): void
    {
        Sanctum::actingAs($this->manager);

        $olderEdition = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2100-01-01'));
        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2100-06-01'));

        $olderDivision = EventDivision::factory()->create([
            'event_id' => $olderEdition->id_event,
            'division_id' => Division::factory()->create()->id_division,
        ]);

        [$matches, $ranking] = $this->seedPostersForDivision($olderDivision);

        $cleared = (new EventDivisionPosterInvalidator())->clearGenerated($olderDivision, ['MATCHES', 'RANKING']);

        $this->assertSame(0, $cleared);
        $this->assertNotNull($matches->fresh()->generated_path);
        $this->assertNotNull($ranking->fresh()->generated_path);
    }

    public function test_clear_generated_clears_posters_for_last_edition(): void
    {
        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2100-01-01'));
        $lastEdition = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2100-06-01'));

        $lastDivision = EventDivision::factory()->create([
            'event_id' => $lastEdition->id_event,
            'division_id' => Division::factory()->create()->id_division,
        ]);

        [$matches, $ranking] = $this->seedPostersForDivision($lastDivision);

        $cleared = (new EventDivisionPosterInvalidator())->clearGenerated($lastDivision, ['MATCHES', 'RANKING']);

        $this->assertSame(2, $cleared);
        $this->assertGeneratedCleared($matches);
        $this->assertGeneratedCleared($ranking);
    }

    public function test_guard_blocks_clear_attendance_for_non_last_edition(): void
    {
        $olderEdition = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2100-01-01'));
        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2100-06-01'));

        $poster = $this->seedAttendancePoster((int) $olderEdition->id_event);

        $cleared = (new EventDivisionPosterInvalidator())->clearAttendanceForEvent((int) $olderEdition->id_event);

        $this->assertSame(0, $cleared);
        $this->assertNotNull($poster->fresh()->generated_path);
    }

    public function test_clear_attendance_for_edition_clears_last_edition_poster(): void
    {
        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2100-01-01'));
        $lastEdition = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2100-06-01'));

        $poster = $this->seedAttendancePoster((int) $lastEdition->id_event);

        $cleared = (new EventDivisionPosterInvalidator())->clearAttendanceForEvent((int) $lastEdition->id_event);

        $this->assertSame(1, $cleared);
        $this->assertNull($poster->fresh()->generated_path);
    }

    public function test_participation_create_on_non_last_edition_does_not_clear_posters(): void
    {
        Sanctum::actingAs($this->manager);

        $olderEdition = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2100-01-01'));
        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2100-06-01'));

        $olderDivision = EventDivision::factory()->create([
            'event_id' => $olderEdition->id_event,
            'division_id' => Division::factory()->create()->id_division,
            'players_limit' => 8,
        ]);

        [$matches, $ranking] = $this->seedPostersForDivision($olderDivision);

        $this->postJson('/api/v1/manager/event-participations', [
            'event_id' => $olderEdition->id_event,
            'event_division_id' => $olderDivision->id_event_division,
            'player_user_id' => User::factory()->create()->id_user,
            'sort_order' => 0,
            'is_collecting_points' => true,
        ])->assertCreated();

        $this->assertNotNull($matches->fresh()->generated_path);
        $this->assertNotNull($ranking->fresh()->generated_path);
    }

    /**
     * @return array{0: EventDivisionPoster, 1: EventDivisionPoster}
     */
    private function seedPostersForDivision(EventDivision $eventDivision): array
    {
        $court = Court::factory()->create(['name' => 'Court A']);
        $edCourt = EventDivisionCourt::factory()->create([
            'event_division_id' => $eventDivision->id_event_division,
            'court_id' => $court->id_court,
            'display_order' => 0,
        ]);

        $previousDivision = $this->eventDivision;
        $previousCourt = $this->edCourt;
        $this->eventDivision = $eventDivision;
        $this->edCourt = $edCourt;

        try {
            return $this->seedPosters();
        } finally {
            $this->eventDivision = $previousDivision;
            $this->edCourt = $previousCourt;
        }
    }

    private function seedAttendancePoster(int $editionId): EventDivisionPoster
    {
        $poster = new EventDivisionPoster();
        $poster->event_id = $editionId;
        $poster->event_division_id = null;
        $poster->poster_kind = 'ATTENDANCE';
        $poster->inserted_at = time();
        $poster->save();
        $poster->generated_path = 'event-division-posters/' . $poster->id_event_division_poster . '/generated/attendance.pdf';
        $poster->save();

        Storage::disk(config('filesystems.user_uploads_disk', 'public'))
            ->put((string) $poster->generated_path, '%PDF-attendance');

        return $poster;
    }

    private function markSetUpEditionAsLast(): void
    {
        $bounds = \Tests\Support\UnixEventFactory::eventBounds('2100-12-31');
        $this->event->forceFill([
            'event_starts_at' => $bounds['event_starts_at'],
            'event_ends_at' => $bounds['event_ends_at'],
            'registration_allowed_until' => $bounds['registration_allowed_until'],
            'cancellation_allowed_until' => $bounds['cancellation_allowed_until'],
        ])->save();
    }
}
