<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Pdf;

use App\Models\CachedPdfPairImage;
use App\Models\CachedPdfPlayerImage;
use App\Models\Player;
use App\Models\PlayerProfileImage;
use App\Services\Pdf\CachedPdfPairImageService;
use App\Services\Pdf\CachedPdfPlayerImageService;
use App\Services\Pdf\PdfRoundMask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CachedPdfImagesTest extends TestCase
{
    use RefreshDatabase;

    private string $photoA;

    private string $photoB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $this->photoA = $this->solidPng(220, 180, 255, 0, 0);
        $this->photoB = $this->solidPng(180, 220, 0, 0, 255);
    }

    protected function tearDown(): void
    {
        @unlink($this->photoA);
        @unlink($this->photoB);
        parent::tearDown();
    }

    public function test_pair_four_field_unique_allows_different_collecting_combos(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        CachedPdfPairImage::query()->create([
            'player_1_id' => min($p1->id_player, $p2->id_player),
            'player_2_id' => max($p1->id_player, $p2->id_player),
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
            'picture_path' => null,
            'inserted_at' => time(),
        ]);
        CachedPdfPairImage::query()->create([
            'player_1_id' => min($p1->id_player, $p2->id_player),
            'player_2_id' => max($p1->id_player, $p2->id_player),
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => false,
            'picture_path' => null,
            'inserted_at' => time(),
        ]);

        $this->assertSame(2, CachedPdfPairImage::query()->count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        CachedPdfPairImage::query()->create([
            'player_1_id' => min($p1->id_player, $p2->id_player),
            'player_2_id' => max($p1->id_player, $p2->id_player),
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
            'picture_path' => null,
            'inserted_at' => time(),
        ]);
    }

    public function test_pair_mode_a_persists_circular_under_new_prefix(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $service = new CachedPdfPairImageService();

        $path = $service->resolvePath(
            (int) $p1->id_player,
            (int) $p2->id_player,
            $this->photoA,
            $this->photoB,
            true,
            true,
            true,
        );

        $this->assertFileExists($path);
        $row = CachedPdfPairImage::query()->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->picture_path);
        $this->assertStringStartsWith('cached-pdf-pair-images/', $row->picture_path);
        $this->assertPngCornerTransparent($path);
    }

    public function test_pair_mode_b_temp_creates_no_row(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $before = CachedPdfPairImage::query()->count();
        $service = new CachedPdfPairImageService();

        $path = $service->resolvePath(
            (int) $p1->id_player,
            (int) $p2->id_player,
            $this->photoA,
            $this->photoB,
            true,
            false,
            false,
        );

        $this->assertFileExists($path);
        $this->assertSame($before, CachedPdfPairImage::query()->count());
        $this->assertPngCornerTransparent($path);
        @unlink($path);
    }

    public function test_pair_first_or_new_reuses_same_collecting_combo(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $service = new CachedPdfPairImageService();

        $service->resolvePath((int) $p1->id_player, (int) $p2->id_player, $this->photoA, $this->photoB, true, true, true);
        $service->resolvePath((int) $p1->id_player, (int) $p2->id_player, $this->photoA, $this->photoB, true, true, true);
        $service->resolvePath((int) $p1->id_player, (int) $p2->id_player, $this->photoA, $this->photoB, false, true, true);

        $this->assertSame(2, CachedPdfPairImage::query()->count());
    }

    public function test_pair_missing_picture_file_regenerates(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $left = min((int) $p1->id_player, (int) $p2->id_player);
        $right = max((int) $p1->id_player, (int) $p2->id_player);

        $disk = config('filesystems.user_uploads_disk', 'public');
        $missingRelative = 'cached-pdf-pair-images/999/picture/couple.png';

        $row = CachedPdfPairImage::query()->create([
            'player_1_id' => $left,
            'player_2_id' => $right,
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
            'picture_path' => $missingRelative,
            'inserted_at' => time(),
        ]);

        $this->assertFalse(Storage::disk($disk)->exists($missingRelative));

        $service = new CachedPdfPairImageService();
        $path = $service->resolvePath($left, $right, $this->photoA, $this->photoB, true, true, true);

        $row->refresh();
        $this->assertStringStartsWith('cached-pdf-pair-images/', (string) $row->picture_path);
        $this->assertTrue(Storage::disk($disk)->exists((string) $row->picture_path));
        $this->assertPngCornerTransparent($path);
    }

    public function test_pair_null_picture_path_regenerates(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $left = min((int) $p1->id_player, (int) $p2->id_player);
        $right = max((int) $p1->id_player, (int) $p2->id_player);

        $row = CachedPdfPairImage::query()->create([
            'player_1_id' => $left,
            'player_2_id' => $right,
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
            'picture_path' => null,
            'inserted_at' => time(),
        ]);

        $service = new CachedPdfPairImageService();
        $path = $service->resolvePath($left, $right, $this->photoA, $this->photoB, true, true, true);

        $row->refresh();
        $this->assertNotNull($row->picture_path);
        $this->assertStringStartsWith('cached-pdf-pair-images/', (string) $row->picture_path);
        $this->assertFileExists($path);
        $this->assertPngCornerTransparent($path);
    }

    public function test_pair_compose_dims_non_collecting_half(): void
    {
        $service = new CachedPdfPairImageService();
        $pngCollecting = $service->composePng($this->photoA, $this->photoB, true, true);
        $pngDimmed = $service->composePng($this->photoA, $this->photoB, false, true);

        $imgA = imagecreatefromstring($pngCollecting);
        $imgB = imagecreatefromstring($pngDimmed);
        $this->assertNotFalse($imgA);
        $this->assertNotFalse($imgB);

        $leftCollecting = PdfRoundMask::sampleRgba($imgA, 40, 100);
        $leftDimmed = PdfRoundMask::sampleRgba($imgB, 40, 100);
        // White opacity overlay brightens non-red channels on solid red halves.
        $this->assertGreaterThan($leftCollecting['green'], $leftDimmed['green']);
        $this->assertGreaterThan($leftCollecting['blue'], $leftDimmed['blue']);

        imagedestroy($imgA);
        imagedestroy($imgB);
    }

    public function test_pair_normalize_flip_still_resolves(): void
    {
        $first = Player::factory()->create();
        $second = Player::factory()->create();
        $lowId = min((int) $first->id_player, (int) $second->id_player);
        $highId = max((int) $first->id_player, (int) $second->id_player);

        $service = new CachedPdfPairImageService();
        $path = $service->resolvePath(
            $highId,
            $lowId,
            $this->photoA,
            $this->photoB,
            true,
            true,
            false,
        );
        $this->assertFileExists($path);
        $this->assertPngCornerTransparent($path);
        @unlink($path);
    }

    public function test_pair_source_newer_forces_rewrite(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $image1 = PlayerProfileImage::factory()->create(['player_id' => $p1->id_player]);
        $image2 = PlayerProfileImage::factory()->create(['player_id' => $p2->id_player]);
        $service = new CachedPdfPairImageService();
        $service->resolvePath(
            (int) $p1->id_player,
            (int) $p2->id_player,
            $this->photoA,
            $this->photoB,
            true,
            true,
            true,
            (int) $image1->id,
            (int) $image2->id,
        );

        $row = CachedPdfPairImage::query()->firstOrFail();
        $row->forceFill(['inserted_at' => 1, 'updated_at' => 1])->save();
        touch($this->photoA, time());

        $service->resolvePath(
            (int) $p1->id_player,
            (int) $p2->id_player,
            $this->photoA,
            $this->photoB,
            true,
            true,
            true,
            (int) $image1->id,
            (int) $image2->id,
        );
        $row->refresh();
        $this->assertGreaterThan(1, (int) $row->updated_at);
    }

    public function test_pair_without_source_ids_rewrites_only_when_player_is_newer(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $service = new CachedPdfPairImageService();
        $service->resolvePath((int) $p1->id_player, (int) $p2->id_player, $this->photoA, $this->photoB, true, true, true);

        $row = CachedPdfPairImage::query()->firstOrFail();
        $row->forceFill(['inserted_at' => 1000, 'updated_at' => 1000])->save();

        // An untouched player keeps the initials composite as-is.
        $service->resolvePath((int) $p1->id_player, (int) $p2->id_player, $this->photoA, $this->photoB, true, true, true);
        $row->refresh();
        $this->assertSame(1000, (int) $row->updated_at);

        // A renamed player must get fresh initials.
        $service->resolvePath(
            (int) $p1->id_player,
            (int) $p2->id_player,
            $this->photoA,
            $this->photoB,
            true,
            true,
            true,
            null,
            null,
            2000,
            null,
        );
        $row->refresh();
        $this->assertGreaterThan(1000, (int) $row->updated_at);
    }

    public function test_player_table_and_mode_a_persist_circular(): void
    {
        $this->assertTrue(Schema::hasTable('cached_pdf_player_images'));
        $this->assertTrue(Schema::hasColumn('cached_pdf_player_images', 'player_is_collecting_points'));
        $player = Player::factory()->create();
        $service = new CachedPdfPlayerImageService();

        $path = $service->resolvePath((int) $player->id_player, $this->photoA, true, true);
        $this->assertFileExists($path);
        $row = CachedPdfPlayerImage::query()->where('player_id', $player->id_player)->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->player_is_collecting_points);
        $this->assertStringStartsWith('cached-pdf-player-images/', (string) $row->picture_path);
        $this->assertPngCornerTransparent($path);
    }

    public function test_player_four_field_unique_allows_different_collecting(): void
    {
        $player = Player::factory()->create();
        CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => null,
            'inserted_at' => time(),
        ]);
        CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => false,
            'picture_path' => null,
            'inserted_at' => time(),
        ]);
        $this->assertSame(2, CachedPdfPlayerImage::query()->count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => null,
            'inserted_at' => time(),
        ]);
    }

    public function test_player_first_or_new_reuses_same_collecting(): void
    {
        $player = Player::factory()->create();
        $service = new CachedPdfPlayerImageService();
        $service->resolvePath((int) $player->id_player, $this->photoA, true, true);
        $service->resolvePath((int) $player->id_player, $this->photoA, true, true);
        $service->resolvePath((int) $player->id_player, $this->photoA, false, true);
        $this->assertSame(2, CachedPdfPlayerImage::query()->where('player_id', $player->id_player)->count());
    }

    public function test_player_mode_b_temp_no_row(): void
    {
        $player = Player::factory()->create();
        $before = CachedPdfPlayerImage::query()->count();
        $service = new CachedPdfPlayerImageService();
        $path = $service->resolvePath((int) $player->id_player, $this->photoA, true, false);
        $this->assertFileExists($path);
        $this->assertSame($before, CachedPdfPlayerImage::query()->count());
        $this->assertPngCornerTransparent($path);
        @unlink($path);
    }

    public function test_player_missing_picture_file_regenerates(): void
    {
        $player = Player::factory()->create();
        $disk = config('filesystems.user_uploads_disk', 'public');
        $missingRelative = 'cached-pdf-player-images/999/picture/avatar.png';

        $row = CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => $missingRelative,
            'inserted_at' => time(),
        ]);

        $this->assertFalse(Storage::disk($disk)->exists($missingRelative));

        $service = new CachedPdfPlayerImageService();
        $path = $service->resolvePath((int) $player->id_player, $this->photoA, true, true);

        $row->refresh();
        $this->assertStringStartsWith('cached-pdf-player-images/', (string) $row->picture_path);
        $this->assertTrue(Storage::disk($disk)->exists((string) $row->picture_path));
        $this->assertPngCornerTransparent($path);
    }

    public function test_player_null_picture_path_regenerates(): void
    {
        $player = Player::factory()->create();

        $row = CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => null,
            'inserted_at' => time(),
        ]);

        $service = new CachedPdfPlayerImageService();
        $path = $service->resolvePath((int) $player->id_player, $this->photoA, true, true);

        $row->refresh();
        $this->assertNotNull($row->picture_path);
        $this->assertStringStartsWith('cached-pdf-player-images/', (string) $row->picture_path);
        $this->assertFileExists($path);
        $this->assertPngCornerTransparent($path);
    }

    public function test_player_compose_dims_when_not_collecting(): void
    {
        $service = new CachedPdfPlayerImageService();
        $pngCollecting = $service->composePng($this->photoA, true);
        $pngDimmed = $service->composePng($this->photoA, false);

        $imgA = imagecreatefromstring($pngCollecting);
        $imgB = imagecreatefromstring($pngDimmed);
        $this->assertNotFalse($imgA);
        $this->assertNotFalse($imgB);

        $collecting = PdfRoundMask::sampleRgba($imgA, 100, 100);
        $dimmed = PdfRoundMask::sampleRgba($imgB, 100, 100);
        $this->assertGreaterThan($collecting['green'], $dimmed['green']);
        $this->assertGreaterThan($collecting['blue'], $dimmed['blue']);

        imagedestroy($imgA);
        imagedestroy($imgB);
    }

    public function test_player_source_newer_forces_rewrite(): void
    {
        $player = Player::factory()->create();
        $image = PlayerProfileImage::factory()->create(['player_id' => $player->id_player]);
        $service = new CachedPdfPlayerImageService();
        $service->resolvePath((int) $player->id_player, $this->photoA, true, true, (int) $image->id);

        $row = CachedPdfPlayerImage::query()->where('player_id', $player->id_player)->firstOrFail();
        $row->forceFill(['inserted_at' => 1, 'updated_at' => 1])->save();
        touch($this->photoA, time());

        $service->resolvePath((int) $player->id_player, $this->photoA, true, true, (int) $image->id);
        $row->refresh();
        $this->assertGreaterThan(1, (int) $row->updated_at);
    }

    public function test_player_without_source_id_rewrites_only_when_player_is_newer(): void
    {
        $player = Player::factory()->create();
        $service = new CachedPdfPlayerImageService();
        $service->resolvePath((int) $player->id_player, $this->photoA, true, true);

        $row = CachedPdfPlayerImage::query()->where('player_id', $player->id_player)->firstOrFail();
        $row->forceFill(['inserted_at' => 1000, 'updated_at' => 1000])->save();

        $service->resolvePath((int) $player->id_player, $this->photoA, true, true);
        $row->refresh();
        $this->assertSame(1000, (int) $row->updated_at);

        $service->resolvePath((int) $player->id_player, $this->photoA, true, true, null, 2000);
        $row->refresh();
        $this->assertGreaterThan(1000, (int) $row->updated_at);
    }

    public function test_poster_ranking_avatar_path_is_cached_not_raw_profile(): void
    {
        $player = Player::factory()->create();
        $players = [[
            'id' => (int) $player->id_player,
            'name' => 'Mara Lovelace',
            'is_collecting_points' => true,
            'profile_abs_path' => $this->photoA,
        ]];

        $payload = $this->invokePosterBuildRanking($players, persist: true);
        $avatar = $payload['players'][0]['avatar_path'];
        $this->assertNotSame($this->photoA, $avatar);
        $this->assertFileExists($avatar);
        $this->assertPngCornerTransparent($avatar);
        $this->assertSame(1, CachedPdfPlayerImage::query()->count());
        $this->assertTrue((bool) CachedPdfPlayerImage::query()->first()?->player_is_collecting_points);
    }

    public function test_poster_mode_a_persists_pair_and_player_mode_b_persists_neither(): void
    {
        $players = Player::factory()->count(4)->create()->values();
        $ids = $players->map(fn (Player $p) => (int) $p->id_player)->all();

        $matchRows = [[
            'side_a' => [
                ['id' => $ids[0], 'name' => 'A', 'is_collecting_points' => true, 'profile_abs_path' => $this->photoA],
                ['id' => $ids[1], 'name' => 'B', 'is_collecting_points' => true, 'profile_abs_path' => $this->photoB],
            ],
            'side_b' => [
                ['id' => $ids[2], 'name' => 'C', 'is_collecting_points' => true, 'profile_abs_path' => $this->photoA],
                ['id' => $ids[3], 'name' => 'D', 'is_collecting_points' => true, 'profile_abs_path' => $this->photoB],
            ],
            'starts_at' => '10:00',
            'ends_at' => '10:45',
        ]];

        $this->invokePosterBuildMatches($matchRows, persist: true);
        $this->invokePosterBuildRanking([[
            'id' => $ids[0],
            'name' => 'A',
            'is_collecting_points' => false,
            'profile_abs_path' => $this->photoA,
        ]], persist: true);

        $this->assertGreaterThan(0, CachedPdfPairImage::query()->count());
        $this->assertGreaterThan(0, CachedPdfPlayerImage::query()->count());
        $this->assertFalse(
            (bool) CachedPdfPlayerImage::query()->where('player_id', $ids[0])->first()?->player_is_collecting_points
        );

        CachedPdfPairImage::query()->delete();
        CachedPdfPlayerImage::query()->delete();

        $matchesB = $this->invokePosterBuildMatches($matchRows, persist: false);
        $rankingB = $this->invokePosterBuildRanking([[
            'id' => $ids[0],
            'name' => 'A',
            'is_collecting_points' => false,
            'profile_abs_path' => $this->photoA,
        ]], persist: false);

        $this->assertSame(0, CachedPdfPairImage::query()->count());
        $this->assertSame(0, CachedPdfPlayerImage::query()->count());
        $this->assertFileExists($matchesB['matches'][0]['team_a_image']);
        $this->assertFileExists($rankingB['players'][0]['avatar_path']);
        $this->assertPngCornerTransparent($matchesB['matches'][0]['team_a_image']);
        $this->assertPngCornerTransparent($rankingB['players'][0]['avatar_path']);
    }

    /**
     * @param  list<array<string, mixed>>  $players
     * @return array<string, mixed>
     */
    private function invokePosterBuildRanking(array $players, bool $persist): array
    {
        $service = new \App\Services\Pdf\PosterPdfService();
        $method = new \ReflectionMethod($service, 'buildRankingViewData');
        $method->setAccessible(true);

        return $method->invoke(
            $service,
            'Div',
            '#7ab857',
            'Ed',
            null,
            null,
            true,
            'Court',
            10,
            true,
            true,
            true,
            $players,
            5,
            $persist,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $matchRows
     * @return array<string, mixed>
     */
    private function invokePosterBuildMatches(array $matchRows, bool $persist): array
    {
        $service = new \App\Services\Pdf\PosterPdfService();
        $method = new \ReflectionMethod($service, 'buildMatchesViewData');
        $method->setAccessible(true);

        return $method->invoke(
            $service,
            'Div',
            '#7ab857',
            'Ed',
            null,
            null,
            true,
            'Court',
            10,
            true,
            true,
            false,
            true,
            24,
            '18:00',
            $matchRows,
            $persist,
        );
    }

    private function solidPng(int $w, int $h, int $r, int $g, int $b): string
    {
        $img = imagecreatetruecolor($w, $h);
        $color = imagecolorallocate($img, $r, $g, $b);
        imagefill($img, 0, 0, $color);
        $path = tempnam(sys_get_temp_dir(), 'pdfimg') . '.png';
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    private function assertPngCornerTransparent(string $path): void
    {
        $img = imagecreatefrompng($path);
        $this->assertNotFalse($img);
        imagesavealpha($img, true);
        $corner = PdfRoundMask::sampleRgba($img, 0, 0);
        $this->assertGreaterThan(0, $corner['alpha']);
        imagedestroy($img);
    }
}
