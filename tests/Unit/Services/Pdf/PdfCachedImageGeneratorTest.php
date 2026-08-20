<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Models\CachedPdfPairImage;
use App\Models\CachedPdfPlayerImage;
use App\Models\Player;
use App\Services\Pdf\PdfCachedImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PdfCachedImageGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
    }

    public function test_player_avatar_url_persists_row_and_removes_initials_temp(): void
    {
        $player = Player::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pop']);

        $url = (new PdfCachedImageGenerator())->generatePlayerAvatarUrl($player, true);

        $this->assertNotNull($url);
        $this->assertSame(1, CachedPdfPlayerImage::query()->count());
        $this->assertNull(CachedPdfPlayerImage::query()->first()?->source_player_profile_image_id);
        $this->assertSame([], $this->initialsTempsFor($player));
    }

    public function test_pair_image_url_removes_initials_temps(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $url = (new PdfCachedImageGenerator())->generatePairImageUrl($p1, $p2, true, true);

        $this->assertNotNull($url);
        $this->assertSame(1, CachedPdfPairImage::query()->count());
        $this->assertSame([], $this->initialsTempsFor($p1));
        $this->assertSame([], $this->initialsTempsFor($p2));
    }

    public function test_pair_image_url_removes_mirrored_temp_for_reversed_ids(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $low = (int) $p1->id_player <= (int) $p2->id_player ? $p1 : $p2;
        $high = $low === $p1 ? $p2 : $p1;

        $dir = storage_path('app/tmp/cached-pdf-pair-images');
        $before = is_dir($dir) ? (glob($dir . '/pair_*.png') ?: []) : [];

        $url = (new PdfCachedImageGenerator())->generatePairImageUrl($high, $low, true, true);

        $this->assertNotNull($url);
        $after = is_dir($dir) ? (glob($dir . '/pair_*.png') ?: []) : [];
        $this->assertSame($before, $after);
    }

    /**
     * @return list<string>
     */
    private function initialsTempsFor(Player $player): array
    {
        $dir = storage_path('app/tmp/pdf-initials-placeholders');
        if (! is_dir($dir)) {
            return [];
        }

        return array_values(glob($dir . '/player_' . $player->id_player . '_*.png') ?: []);
    }
}
