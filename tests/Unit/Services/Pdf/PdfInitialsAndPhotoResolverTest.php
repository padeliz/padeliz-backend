<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Models\Player;
use App\Models\PlayerProfileImage;
use App\Services\Pdf\PdfInitialsPlaceholderImageService;
use App\Services\Pdf\PdfPlayerPhotoForCacheResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PdfFixtures;
use Tests\TestCase;

final class PdfInitialsAndPhotoResolverTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temps = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
    }

    protected function tearDown(): void
    {
        foreach ($this->temps as $path) {
            @unlink($path);
        }
        $this->temps = [];
        parent::tearDown();
    }

    public function test_initials_extraction(): void
    {
        $service = new PdfInitialsPlaceholderImageService();
        $this->assertSame('M', $service->initials('Madonna'));
        $this->assertSame('AB', $service->initials('Andrei R. Brebeanu'));
        $this->assertSame('?', $service->initials(''));
        $this->assertSame('?', $service->initials('   '));
    }

    public function test_create_temp_writes_decodable_300_png(): void
    {
        $service = new PdfInitialsPlaceholderImageService();
        $path = $service->createTemp(42, 'Andreea Enescu');
        $this->temps[] = $path;

        $this->assertFileExists($path);
        $data = file_get_contents($path);
        $this->assertNotFalse($data);
        $image = imagecreatefromstring($data);
        $this->assertNotFalse($image);
        $this->assertSame(300, imagesx($image));
        $this->assertSame(300, imagesy($image));
        imagedestroy($image);
    }

    public function test_resolver_returns_profile_path_and_id_when_usable(): void
    {
        $player = Player::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pop']);
        $relative = 'player-profile-images/' . $player->id_player . '/profile/ok.png';
        $png = $this->solidPngBytes();
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))->put($relative, $png);

        $image = PlayerProfileImage::factory()->create([
            'player_id' => $player->id_player,
            'profile_path' => $relative,
            'is_pdf_primary' => true,
            'sort_order' => 0,
        ]);

        $resolved = (new PdfPlayerPhotoForCacheResolver())->resolve($player->fresh());
        $this->assertFalse($resolved->isEphemeral());
        $this->assertSame((int) $image->id, $resolved->sourcePlayerProfileImageId);
        $this->assertFileExists($resolved->absolutePath);
    }

    public function test_resolver_uses_initials_when_missing_profile(): void
    {
        $player = Player::factory()->create(['first_name' => 'No', 'last_name' => 'Photo']);
        $resolved = (new PdfPlayerPhotoForCacheResolver())->resolve($player);
        $this->temps[] = $resolved->absolutePath;

        $this->assertTrue($resolved->isEphemeral());
        $this->assertNull($resolved->sourcePlayerProfileImageId);
        $this->assertFileExists($resolved->absolutePath);
    }

    public function test_resolver_uses_initials_when_profile_unreadable(): void
    {
        $player = Player::factory()->create(['first_name' => 'Bad', 'last_name' => 'File']);
        $relative = 'player-profile-images/' . $player->id_player . '/profile/bad.jpg';
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))->put($relative, ' ');

        PlayerProfileImage::factory()->create([
            'player_id' => $player->id_player,
            'profile_path' => $relative,
            'is_pdf_primary' => true,
            'sort_order' => 0,
        ]);

        $resolved = (new PdfPlayerPhotoForCacheResolver())->resolve($player->fresh());
        $this->temps[] = $resolved->absolutePath;

        $this->assertTrue($resolved->isEphemeral());
        $this->assertNull($resolved->sourcePlayerProfileImageId);
    }

    private function solidPngBytes(): string
    {
        return PdfFixtures::solidRed40Bytes();
    }
}
