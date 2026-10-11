<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\EventDivisionPoster;
use App\Models\EventDivisionSchedule;
use App\Models\EventParticipation;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\MatchSide;
use App\Models\User;
use App\Services\Pdf\MissingMatchParticipationException;
use App\Services\Pdf\PosterPdfResult;
use App\Services\Pdf\PosterPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\PdfFixtures;
use Tests\TestCase;

final class EventDivisionPosterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_pdf_succeeds_without_profile_images(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));

        $eventDivision = EventDivision::factory()->create();
        EventDivisionSchedule::factory()->create([
            'event_division_id' => $eventDivision->id_event_division,
            'include_scores' => true,
            'final_points_target' => 24,
            'player_opponents_nr' => 3,
            'player_matches_repeat' => 1,
        ]);

        $players = User::factory()->count(4)->create();
        foreach ($players as $i => $player) {
            EventParticipation::factory()->create([
                'event_id' => $eventDivision->event_id,
                'event_division_id' => $eventDivision->id_event_division,
                'player_user_id' => $player->id_user,
                'is_collecting_points' => true,
                'absence_reason' => null,
                'sort_order' => $i,
            ]);
        }

        $poster = $this->makePoster($eventDivision, 'RANKING', null, 'rank.pdf');
        Storage::disk($poster->storageDisk())->put((string) $poster->generated_path, '%PDF-fake');
        $this->mock(PosterPdfService::class, function ($mock) use ($poster): void {
            $mock->shouldReceive('generateRankingForEventDivision')
                ->once()
                ->andReturn(new PosterPdfResult('%PDF-fake', 'rank.pdf', $poster->fresh()));
        });

        $response = $this->postJson(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/ranking",
            []
        );

        $response->assertOk()
            ->assertJsonPath('data.poster_kind', 'RANKING');
    }

    public function test_post_ranking_returns_json_not_pdf(): void
    {
        [$manager, $eventDivision] = $this->actingManagerWithDivision();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        foreach (User::factory()->count(4)->create() as $i => $player) {
            EventParticipation::factory()->create([
                'event_id' => $eventDivision->event_id,
                'event_division_id' => $eventDivision->id_event_division,
                'player_user_id' => $player->id_user,
                'is_collecting_points' => true,
                'absence_reason' => null,
                'sort_order' => $i,
            ]);
        }

        $poster = $this->makePoster($eventDivision, 'RANKING', null, 'rank.pdf');
        Storage::disk($poster->storageDisk())->put((string) $poster->generated_path, '%PDF-fake');
        $this->mock(PosterPdfService::class, function ($mock) use ($poster): void {
            $mock->shouldReceive('generateRankingForEventDivision')
                ->once()
                ->andReturn(new PosterPdfResult('%PDF-fake', 'rank.pdf', $poster->fresh()));
        });

        $response = $this->postJson(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/ranking",
            []
        );

        $response->assertOk()
            ->assertJsonPath('data.poster_kind', 'RANKING')
            ->assertJsonPath('message', 'Poster generated.');
        $this->assertNotNull($response->json('data.generated_path'));
        $this->assertStringNotContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
    }

    public function test_post_matches_requires_court(): void
    {
        [$manager, $eventDivision] = $this->actingManagerWithDivision();

        $this->postJson(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/matches",
            []
        )->assertStatus(422)
            ->assertJsonValidationErrors(['court']);
    }

    public function test_get_generate_paths_are_gone(): void
    {
        [$manager, $eventDivision] = $this->actingManagerWithDivision();

        $this->getJson(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/ranking"
        )->assertStatus(405);

        $this->getJson(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/matches?court=1"
        )->assertStatus(405);
    }

    public function test_get_generated_file_streams_without_regenerating(): void
    {
        [$manager, $eventDivision] = $this->actingManagerWithDivision();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $poster = $this->makePoster($eventDivision, 'RANKING', null, 'rank.pdf');
        $disk = $poster->storageDisk();
        Storage::disk($disk)->put((string) $poster->generated_path, '%PDF-stored');

        $first = $this->get(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/ranking/file"
        );
        $first->assertOk();
        $this->assertSame('%PDF-stored', $first->streamedContent());

        $second = $this->get(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/ranking/file"
        );
        $second->assertOk();
        $this->assertSame('%PDF-stored', $second->streamedContent());
    }

    public function test_get_generated_file_404_when_missing(): void
    {
        [$manager, $eventDivision] = $this->actingManagerWithDivision();

        $this->getJson(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/ranking/file"
        )->assertStatus(404);
    }

    public function test_get_generated_file_allowed_when_finalized(): void
    {
        [$manager, $eventDivision] = $this->actingManagerWithDivision();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $poster = $this->makePoster($eventDivision, 'RANKING', null, 'rank.pdf');
        Storage::disk($poster->storageDisk())->put((string) $poster->generated_path, '%PDF-final');
        $eventDivision->event->forceFill(['finalized_at' => time()])->save();

        $this->get(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/ranking/file"
        )->assertOk();
    }

    public function test_post_generate_409_when_finalized(): void
    {
        [$manager, $eventDivision] = $this->actingManagerWithDivision();
        $eventDivision->event->forceFill(['finalized_at' => time()])->save();

        $this->postJson(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/ranking",
            []
        )->assertStatus(409);
    }

    public function test_printed_file_200_and_404(): void
    {
        [$manager, $eventDivision] = $this->actingManagerWithDivision();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $poster = $this->makePoster($eventDivision, 'RANKING', null, 'rank.pdf');

        $this->getJson(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/{$poster->id_event_division_poster}/printed/file"
        )->assertStatus(404);

        $printed = 'event-division-posters/' . $poster->id_event_division_poster . '/printed/shot.jpg';
        Storage::disk($poster->storageDisk())->put($printed, 'JPEGDATA');
        $poster->printed_path = $printed;
        $poster->save();

        $this->get(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/{$poster->id_event_division_poster}/printed/file"
        )->assertOk();
    }

    public function test_post_printed_409_when_finalized(): void
    {
        [$manager, $eventDivision] = $this->actingManagerWithDivision();
        $poster = $this->makePoster($eventDivision, 'RANKING', null, 'rank.pdf');
        $eventDivision->event->forceFill(['finalized_at' => time()])->save();

        $this->post(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/{$poster->id_event_division_poster}/printed"
        )->assertStatus(409);
    }

    public function test_nginx_conf_sets_fastcgi_read_timeout_180s(): void
    {
        $conf = (string) file_get_contents(base_path('docker/nginx/default.conf'));
        $this->assertStringContainsString('fastcgi_read_timeout 180s', $conf);
    }

    public function test_matches_generate_uses_participation_when_player_id_null(): void
    {
        [$manager, $eventDivision, $court] = $this->actingManagerWithDivisionAndCourt();
        $this->seedMatchWithParticipations($eventDivision, $court, playerIdOnMatchPlayer: false);

        $poster = $this->makePoster($eventDivision, 'MATCHES', (int) $court->id_event_division_court, 'matches.pdf');
        Storage::disk($poster->storageDisk())->put((string) $poster->generated_path, '%PDF-matches');
        $this->mock(PosterPdfService::class, function ($mock) use ($poster): void {
            $mock->shouldReceive('generateMatchesForEventDivision')
                ->once()
                ->andReturn(new PosterPdfResult('%PDF-matches', 'matches.pdf', $poster->fresh()));
        });

        $response = $this->postJson(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/matches",
            ['court' => $court->id_event_division_court]
        );

        $response->assertOk()
            ->assertJsonPath('data.poster_kind', 'MATCHES');
        $this->assertNotNull($response->json('data.generated_path'));
    }

    public function test_matches_generate_422_when_participation_missing(): void
    {
        [$manager, $eventDivision, $court] = $this->actingManagerWithDivisionAndCourt();
        $this->seedMatchWithParticipations($eventDivision, $court, playerIdOnMatchPlayer: false, omitParticipation: true);

        $this->mock(PosterPdfService::class, function ($mock): void {
            $mock->shouldReceive('generateMatchesForEventDivision')
                ->once()
                ->andThrow(new MissingMatchParticipationException());
        });

        $this->postJson(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/matches",
            ['court' => $court->id_event_division_court]
        )->assertStatus(422);
    }

    public function test_matches_generate_returns_json_ok(): void
    {
        [$manager, $eventDivision, $court] = $this->actingManagerWithDivisionAndCourt();
        $this->seedMatchWithParticipations(
            $eventDivision,
            $court,
            playerIdOnMatchPlayer: false,
            firstCollecting: false,
        );

        $poster = $this->makePoster($eventDivision, 'MATCHES', (int) $court->id_event_division_court, 'matches.pdf');
        Storage::disk($poster->storageDisk())->put((string) $poster->generated_path, '%PDF-matches');
        $this->mock(PosterPdfService::class, function ($mock) use ($poster): void {
            $mock->shouldReceive('generateMatchesForEventDivision')
                ->once()
                ->andReturn(new PosterPdfResult('%PDF-matches', 'matches.pdf', $poster->fresh()));
        });

        $this->postJson(
            "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}/posters/matches",
            ['court' => $court->id_event_division_court]
        )->assertOk()
            ->assertJsonPath('data.poster_kind', 'MATCHES');
    }

    public function test_regenerate_overwrites_generated_and_keeps_printed(): void
    {
        [$manager, $eventDivision] = $this->actingManagerWithDivision();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $poster = $this->makePoster($eventDivision, 'RANKING', null, 'old.pdf');
        $disk = $poster->storageDisk();
        Storage::disk($disk)->put((string) $poster->generated_path, 'OLD-PDF');
        $printed = 'event-division-posters/' . $poster->id_event_division_poster . '/printed/shot.jpg';
        Storage::disk($disk)->put($printed, 'JPEG');
        $poster->printed_path = $printed;
        $poster->save();

        $service = new PosterPdfService();
        $method = new \ReflectionMethod($service, 'persistGeneratedPoster');
        $method->setAccessible(true);
        $method->invoke($service, $eventDivision, 'RANKING', null, 'NEW-PDF', 'new.pdf');

        $poster->refresh();
        $this->assertSame($printed, $poster->printed_path);
        $this->assertSame('NEW-PDF', Storage::disk($disk)->get((string) $poster->generated_path));
    }

    /**
     * @return array{0: User, 1: EventDivision}
     */
    private function actingManagerWithDivision(): array
    {
        $manager = User::factory()->manager()->create(['user_types' => ['player', 'manager']]);
        Sanctum::actingAs($manager);
        $eventDivision = EventDivision::factory()->create();
        EventDivisionSchedule::factory()->create([
            'event_division_id' => $eventDivision->id_event_division,
        ]);

        return [$manager, $eventDivision];
    }

    /**
     * @return array{0: User, 1: EventDivision, 2: EventDivisionCourt}
     */
    private function actingManagerWithDivisionAndCourt(): array
    {
        [$manager, $eventDivision] = $this->actingManagerWithDivision();
        $court = EventDivisionCourt::factory()->create([
            'event_division_id' => $eventDivision->id_event_division,
        ]);

        return [$manager, $eventDivision, $court];
    }

    private function makePoster(
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

        return $poster;
    }

    /**
     * @return list<Player>
     */
    private function seedMatchWithParticipations(
        EventDivision $eventDivision,
        EventDivisionCourt $court,
        bool $playerIdOnMatchPlayer,
        bool $omitParticipation = false,
        bool $firstCollecting = true,
    ): array {
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $players = User::factory()->count(4)->create()->all();
        $participations = [];
        foreach ($players as $i => $player) {
            $this->attachProfilePng($player);
            $participations[] = EventParticipation::factory()->create([
                'event_id' => $eventDivision->event_id,
                'event_division_id' => $eventDivision->id_event_division,
                'player_user_id' => $player->id_user,
                'is_collecting_points' => $i === 0 ? $firstCollecting : true,
                'absence_reason' => null,
                'sort_order' => $i,
            ]);
        }

        $match = GameMatch::factory()->create([
            'event_division_id' => $eventDivision->id_event_division,
            'event_division_court_id' => $court->id_event_division_court,
            'match_kind' => 'REGULAR',
            'sequence_no' => 1,
        ]);
        $left = MatchSide::query()->create([
            'match_id' => $match->id_match,
            'side' => 'LEFT',
            'inserted_at' => time(),
        ]);
        $right = MatchSide::query()->create([
            'match_id' => $match->id_match,
            'side' => 'RIGHT',
            'inserted_at' => time(),
        ]);

        $slots = [
            [$left, 0, 0],
            [$left, 1, 1],
            [$right, 0, 2],
            [$right, 1, 3],
        ];
        foreach ($slots as [$side, $slot, $idx]) {
            MatchPlayer::query()->create([
                'match_side_id' => $side->id_match_side,
                'slot' => $slot,
                'participation_id' => $omitParticipation ? null : $participations[$idx]->id_participation,
                'player_user_id' => $playerIdOnMatchPlayer ? $players[$idx]->id_user : null,
                'inserted_at' => time(),
            ]);
        }

        return $players;
    }

    private function attachProfilePng(User $player): void
    {
        $relative = 'player-profile-images/' . $player->id_user . '/profile/avatar.png';
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))
            ->put($relative, PdfFixtures::solidRed40Bytes());
        \App\Models\PlayerProfileImage::query()->create([
            'player_user_id' => $player->id_user,
            'profile_path' => $relative,
            'sort_order' => 0,
            'inserted_at' => time(),
        ]);
    }
}
