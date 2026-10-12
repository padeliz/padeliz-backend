<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Pdf;

use App\Models\CachedPdfPairImage;
use App\Models\CachedPdfPlayerImage;
use App\Models\User;
use App\Services\Pdf\CachedPdfPairImageService;
use App\Services\Pdf\CachedPdfPlayerImageService;
use App\Services\Pdf\PdfCachedImageGenerator;
use App\Services\Pdf\PdfPlayerPhotoForCache;
use App\Services\Pdf\PdfPlayerPhotoForCacheResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\PdfFixtures;
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
        $player = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pop']);
        // Non-null source id so the fixture path is not treated as ephemeral and deleted.
        $resolved = new PdfPlayerPhotoForCache(PdfFixtures::solidRed200Path(), 1);

        $photoResolver = Mockery::mock(PdfPlayerPhotoForCacheResolver::class);
        $photoResolver->shouldReceive('resolve')
            ->once()
            ->andReturn($resolved);

        $playerImages = Mockery::mock(CachedPdfPlayerImageService::class);
        $playerImages->shouldReceive('resolvePath')
            ->once()
            ->andReturnUsing(function (int $playerId): string {
                $row = CachedPdfPlayerImage::query()->create([
                    'player_user_id' => $playerId,
                    'player_is_collecting_points' => true,
                    'picture_path' => null,
                    'source_player_profile_image_id' => null,
                    'inserted_at' => time(),
                ]);
                $relative = 'cached-pdf-player-images/' . $row->id_cached_pdf_player_image . '/picture/avatar.png';
                Storage::disk(config('filesystems.user_uploads_disk', 'public'))
                    ->put($relative, PdfFixtures::circularRed200Bytes());
                $row->picture_path = $relative;
                $row->save();

                return Storage::disk(config('filesystems.user_uploads_disk', 'public'))->path($relative);
            });

        $url = (new PdfCachedImageGenerator($photoResolver, $playerImages))->generatePlayerAvatarUrl($player, true);

        $this->assertNotNull($url);
        $this->assertSame(1, CachedPdfPlayerImage::query()->count());
        $this->assertNull(CachedPdfPlayerImage::query()->first()?->source_player_profile_image_id);
        $this->assertSame([], $this->initialsTempsFor($player));
    }

    public function test_pair_image_url_removes_initials_temps(): void
    {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $resolved = new PdfPlayerPhotoForCache(PdfFixtures::solidRed200Path(), 1);

        $photoResolver = Mockery::mock(PdfPlayerPhotoForCacheResolver::class);
        $photoResolver->shouldReceive('resolve')
            ->twice()
            ->andReturn($resolved);

        $pairImages = Mockery::mock(CachedPdfPairImageService::class);
        $pairImages->shouldReceive('resolvePath')
            ->once()
            ->andReturnUsing(function (int $id1, int $id2): string {
                $left = min($id1, $id2);
                $right = max($id1, $id2);
                $row = CachedPdfPairImage::query()->create([
                    'player_1_user_id' => $left,
                    'player_2_user_id' => $right,
                    'player_1_is_collecting_points' => true,
                    'player_2_is_collecting_points' => true,
                    'picture_path' => null,
                    'inserted_at' => time(),
                ]);
                $relative = 'cached-pdf-pair-images/' . $row->id_cached_pdf_pair_image . '/picture/couple.png';
                Storage::disk(config('filesystems.user_uploads_disk', 'public'))
                    ->put($relative, PdfFixtures::circularRed200Bytes());
                $row->picture_path = $relative;
                $row->save();

                return Storage::disk(config('filesystems.user_uploads_disk', 'public'))->path($relative);
            });

        $url = (new PdfCachedImageGenerator($photoResolver, new CachedPdfPlayerImageService(), $pairImages))
            ->generatePairImageUrl($p1, $p2, true, true);

        $this->assertNotNull($url);
        $this->assertSame(1, CachedPdfPairImage::query()->count());
        $this->assertSame([], $this->initialsTempsFor($p1));
        $this->assertSame([], $this->initialsTempsFor($p2));
    }

    public function test_pair_image_url_removes_mirrored_temp_for_reversed_ids(): void
    {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $low = (int) $p1->id_user <= (int) $p2->id_user ? $p1 : $p2;
        $high = $low === $p1 ? $p2 : $p1;
        $resolved = new PdfPlayerPhotoForCache(PdfFixtures::solidRed200Path(), 1);

        $photoResolver = Mockery::mock(PdfPlayerPhotoForCacheResolver::class);
        $photoResolver->shouldReceive('resolve')
            ->twice()
            ->andReturn($resolved);

        $pairImages = Mockery::mock(CachedPdfPairImageService::class);
        $pairImages->shouldReceive('resolvePath')
            ->once()
            ->andReturnUsing(function (int $id1, int $id2): string {
                $left = min($id1, $id2);
                $right = max($id1, $id2);
                $row = CachedPdfPairImage::query()->firstOrCreate(
                    [
                        'player_1_user_id' => $left,
                        'player_2_user_id' => $right,
                        'player_1_is_collecting_points' => true,
                        'player_2_is_collecting_points' => true,
                    ],
                    ['picture_path' => null, 'inserted_at' => time()]
                );
                if ($row->picture_path === null) {
                    $relative = 'cached-pdf-pair-images/' . $row->id_cached_pdf_pair_image . '/picture/couple.png';
                    Storage::disk(config('filesystems.user_uploads_disk', 'public'))
                        ->put($relative, PdfFixtures::circularRed200Bytes());
                    $row->picture_path = $relative;
                    $row->save();
                }

                return Storage::disk(config('filesystems.user_uploads_disk', 'public'))
                    ->path((string) $row->picture_path);
            });

        $dir = storage_path('app/tmp/cached-pdf-pair-images');
        $before = is_dir($dir) ? (glob($dir . '/pair_*.png') ?: []) : [];

        $url = (new PdfCachedImageGenerator($photoResolver, new CachedPdfPlayerImageService(), $pairImages))
            ->generatePairImageUrl($high, $low, true, true);

        $this->assertNotNull($url);
        $after = is_dir($dir) ? (glob($dir . '/pair_*.png') ?: []) : [];
        $this->assertSame($before, $after);
    }

    /**
     * @return list<string>
     */
    private function initialsTempsFor(User $player): array
    {
        $dir = storage_path('app/tmp/pdf-initials-placeholders');
        if (! is_dir($dir)) {
            return [];
        }

        return array_values(glob($dir . '/player_' . $player->id_user . '_*.png') ?: []);
    }
}
