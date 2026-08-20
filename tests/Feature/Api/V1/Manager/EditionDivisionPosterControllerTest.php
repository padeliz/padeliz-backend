<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\CachedPdfPairImage;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionDivisionPoster;
use App\Models\EditionParticipation;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\MatchSide;
use App\Models\Player;
use App\Models\PlayerProfileImage;
use App\Services\Pdf\PosterPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class EditionDivisionPosterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_pdf_succeeds_without_profile_images(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));

        $editionDivision = EditionDivision::factory()->create([
            'include_scores' => true,
            'final_points_target' => 24,
            'player_opponents_nr' => 3,
            'player_matches_repeat' => 1,
        ]);

        $players = Player::factory()->count(4)->create();
        foreach ($players as $i => $player) {
            EditionParticipation::factory()->create([
                'edition_id' => $editionDivision->edition_id,
                'edition_division_id' => $editionDivision->id_edition_division,
                'player_id' => $player->id_player,
                'is_collecting_points' => true,
                'absence_reason' => null,
                'sort_order' => $i,
            ]);
        }

        $response = $this->postJson(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/ranking",
            ['organizer' => 1, 'partner' => 1]
        );

        $response->assertOk()
            ->assertJsonPath('data.poster_kind', 'RANKING');

        $cached = \App\Models\CachedPdfPlayerImage::query()->get();
        $this->assertCount(4, $cached);
        foreach ($cached as $row) {
            $this->assertNull($row->source_player_profile_image_id);
        }
    }

    public function test_post_ranking_returns_json_not_pdf(): void
    {
        [$manager, $editionDivision] = $this->actingManagerWithDivision();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        foreach (Player::factory()->count(4)->create() as $i => $player) {
            $this->attachProfilePng($player);
            EditionParticipation::factory()->create([
                'edition_id' => $editionDivision->edition_id,
                'edition_division_id' => $editionDivision->id_edition_division,
                'player_id' => $player->id_player,
                'is_collecting_points' => true,
                'absence_reason' => null,
                'sort_order' => $i,
            ]);
        }

        $response = $this->postJson(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/ranking",
            ['organizer' => 1, 'partner' => 1]
        );

        $response->assertOk()
            ->assertJsonPath('data.poster_kind', 'RANKING')
            ->assertJsonPath('message', 'Poster generated.');
        $this->assertNotNull($response->json('data.generated_path'));
        $this->assertStringNotContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
    }

    public function test_post_matches_requires_court(): void
    {
        [$manager, $editionDivision] = $this->actingManagerWithDivision();

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/matches",
            ['organizer' => 1, 'partner' => 1]
        )->assertStatus(422)
            ->assertJsonValidationErrors(['court']);
    }

    public function test_get_generate_paths_are_gone(): void
    {
        [$manager, $editionDivision] = $this->actingManagerWithDivision();

        $this->getJson(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/ranking"
        )->assertStatus(405);

        $this->getJson(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/matches?court=1"
        )->assertStatus(405);
    }

    public function test_get_generated_file_streams_without_regenerating(): void
    {
        [$manager, $editionDivision] = $this->actingManagerWithDivision();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $poster = $this->makePoster($editionDivision, 'RANKING', null, 'rank.pdf');
        $disk = $poster->storageDisk();
        Storage::disk($disk)->put((string) $poster->generated_path, '%PDF-stored');

        $first = $this->get(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/ranking/file"
        );
        $first->assertOk();
        $this->assertSame('%PDF-stored', $first->streamedContent());

        $second = $this->get(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/ranking/file"
        );
        $second->assertOk();
        $this->assertSame('%PDF-stored', $second->streamedContent());
    }

    public function test_get_generated_file_404_when_missing(): void
    {
        [$manager, $editionDivision] = $this->actingManagerWithDivision();

        $this->getJson(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/ranking/file"
        )->assertStatus(404);
    }

    public function test_get_generated_file_allowed_when_finalized(): void
    {
        [$manager, $editionDivision] = $this->actingManagerWithDivision();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $poster = $this->makePoster($editionDivision, 'RANKING', null, 'rank.pdf');
        Storage::disk($poster->storageDisk())->put((string) $poster->generated_path, '%PDF-final');
        $editionDivision->edition->forceFill(['finalized_at' => time()])->save();

        $this->get(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/ranking/file"
        )->assertOk();
    }

    public function test_post_generate_409_when_finalized(): void
    {
        [$manager, $editionDivision] = $this->actingManagerWithDivision();
        $editionDivision->edition->forceFill(['finalized_at' => time()])->save();

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/ranking",
            ['organizer' => 1]
        )->assertStatus(409);
    }

    public function test_printed_file_200_and_404(): void
    {
        [$manager, $editionDivision] = $this->actingManagerWithDivision();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $poster = $this->makePoster($editionDivision, 'RANKING', null, 'rank.pdf');

        $this->getJson(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/{$poster->id_edition_division_poster}/printed/file"
        )->assertStatus(404);

        $printed = 'edition-division-posters/' . $poster->id_edition_division_poster . '/printed/shot.jpg';
        Storage::disk($poster->storageDisk())->put($printed, 'JPEGDATA');
        $poster->printed_path = $printed;
        $poster->save();

        $this->get(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/{$poster->id_edition_division_poster}/printed/file"
        )->assertOk();
    }

    public function test_post_printed_409_when_finalized(): void
    {
        [$manager, $editionDivision] = $this->actingManagerWithDivision();
        $poster = $this->makePoster($editionDivision, 'RANKING', null, 'rank.pdf');
        $editionDivision->edition->forceFill(['finalized_at' => time()])->save();

        $this->post(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/{$poster->id_edition_division_poster}/printed"
        )->assertStatus(409);
    }

    public function test_nginx_conf_sets_fastcgi_read_timeout_180s(): void
    {
        $conf = (string) file_get_contents(base_path('docker/nginx/default.conf'));
        $this->assertStringContainsString('fastcgi_read_timeout 180s', $conf);
    }

    public function test_matches_generate_uses_participation_when_player_id_null(): void
    {
        [$manager, $editionDivision, $court] = $this->actingManagerWithDivisionAndCourt();
        $players = $this->seedMatchWithParticipations($editionDivision, $court, playerIdOnMatchPlayer: false);

        $response = $this->postJson(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/matches",
            ['court' => $court->id_edition_division_court, 'organizer' => 1, 'partner' => 1]
        );

        $response->assertOk()
            ->assertJsonPath('data.poster_kind', 'MATCHES');
        $this->assertNotNull($response->json('data.generated_path'));
    }

    public function test_matches_generate_422_when_participation_missing(): void
    {
        [$manager, $editionDivision, $court] = $this->actingManagerWithDivisionAndCourt();
        $this->seedMatchWithParticipations($editionDivision, $court, playerIdOnMatchPlayer: false, omitParticipation: true);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/matches",
            ['court' => $court->id_edition_division_court, 'organizer' => 1, 'partner' => 1]
        )->assertStatus(422);
    }

    public function test_matches_pair_uses_participation_collecting_flag(): void
    {
        [$manager, $editionDivision, $court] = $this->actingManagerWithDivisionAndCourt();
        $this->seedMatchWithParticipations(
            $editionDivision,
            $court,
            playerIdOnMatchPlayer: false,
            firstCollecting: false,
        );

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}/posters/matches",
            ['court' => $court->id_edition_division_court, 'organizer' => 1, 'partner' => 1]
        )->assertOk();

        $row = CachedPdfPairImage::query()->first();
        $this->assertNotNull($row);
        $this->assertTrue(
            ! (bool) $row->player_1_is_collecting_points
            || ! (bool) $row->player_2_is_collecting_points
        );
    }

    public function test_regenerate_overwrites_generated_and_keeps_printed(): void
    {
        [$manager, $editionDivision] = $this->actingManagerWithDivision();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $poster = $this->makePoster($editionDivision, 'RANKING', null, 'old.pdf');
        $disk = $poster->storageDisk();
        Storage::disk($disk)->put((string) $poster->generated_path, 'OLD-PDF');
        $printed = 'edition-division-posters/' . $poster->id_edition_division_poster . '/printed/shot.jpg';
        Storage::disk($disk)->put($printed, 'JPEG');
        $poster->printed_path = $printed;
        $poster->save();

        $service = new PosterPdfService();
        $method = new \ReflectionMethod($service, 'persistGeneratedPoster');
        $method->setAccessible(true);
        $method->invoke($service, $editionDivision, 'RANKING', null, 'NEW-PDF', 'new.pdf');

        $poster->refresh();
        $this->assertSame($printed, $poster->printed_path);
        $this->assertSame('NEW-PDF', Storage::disk($disk)->get((string) $poster->generated_path));
    }

    /**
     * @return array{0: Player, 1: EditionDivision}
     */
    private function actingManagerWithDivision(): array
    {
        $manager = Player::factory()->manager()->create(['user_types' => ['player', 'manager']]);
        Sanctum::actingAs($manager);
        $editionDivision = EditionDivision::factory()->create();

        return [$manager, $editionDivision];
    }

    /**
     * @return array{0: Player, 1: EditionDivision, 2: EditionDivisionCourt}
     */
    private function actingManagerWithDivisionAndCourt(): array
    {
        [$manager, $editionDivision] = $this->actingManagerWithDivision();
        $court = EditionDivisionCourt::factory()->create([
            'edition_division_id' => $editionDivision->id_edition_division,
        ]);

        return [$manager, $editionDivision, $court];
    }

    private function makePoster(
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

        return $poster;
    }

    /**
     * @return list<Player>
     */
    private function seedMatchWithParticipations(
        EditionDivision $editionDivision,
        EditionDivisionCourt $court,
        bool $playerIdOnMatchPlayer,
        bool $omitParticipation = false,
        bool $firstCollecting = true,
    ): array {
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $players = Player::factory()->count(4)->create()->all();
        $participations = [];
        foreach ($players as $i => $player) {
            $this->attachProfilePng($player);
            $participations[] = EditionParticipation::factory()->create([
                'edition_id' => $editionDivision->edition_id,
                'edition_division_id' => $editionDivision->id_edition_division,
                'player_id' => $player->id_player,
                'is_collecting_points' => $i === 0 ? $firstCollecting : true,
                'absence_reason' => null,
                'sort_order' => $i,
            ]);
        }

        $match = GameMatch::factory()->create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'edition_division_court_id' => $court->id_edition_division_court,
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
                'player_id' => $playerIdOnMatchPlayer ? $players[$idx]->id_player : null,
                'inserted_at' => time(),
            ]);
        }

        return $players;
    }

    private function attachProfilePng(Player $player): void
    {
        $img = imagecreatetruecolor(40, 40);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 0, 0));
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        $relative = 'player-profile-images/' . $player->id_player . '/profile/avatar.png';
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))->put($relative, $png);
        PlayerProfileImage::query()->create([
            'player_id' => $player->id_player,
            'profile_path' => $relative,
            'sort_order' => 0,
            'inserted_at' => time(),
        ]);
    }
}
