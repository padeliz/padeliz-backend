<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Pdf;

use App\Models\CachedPdfPairImage;
use App\Models\CachedPdfPlayerImage;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PdfFixtures;
use Tests\TestCase;

/**
 * DB / uniqueness / fixture seams for cached PDF images — no GD compose.
 */
final class CachedPdfImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
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

    public function test_player_table_has_collecting_column(): void
    {
        $this->assertTrue(Schema::hasTable('cached_pdf_player_images'));
        $this->assertTrue(Schema::hasColumn('cached_pdf_player_images', 'player_is_collecting_points'));
    }

    public function test_seeded_fixture_png_can_be_stored_under_cache_prefix(): void
    {
        $disk = config('filesystems.user_uploads_disk', 'public');
        $relative = 'cached-pdf-player-images/1/picture/avatar.png';
        Storage::disk($disk)->put($relative, PdfFixtures::solidRed80Bytes());

        $this->assertTrue(Storage::disk($disk)->exists($relative));
        $this->assertSame(PdfFixtures::solidRed80Bytes(), Storage::disk($disk)->get($relative));
    }
}
