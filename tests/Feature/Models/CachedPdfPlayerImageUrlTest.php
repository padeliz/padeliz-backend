<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\CachedPdfPlayerImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CachedPdfPlayerImageUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
    }

    public function test_url_for_player_hit_and_miss_by_collecting_flag(): void
    {
        $player = User::factory()->create();
        CachedPdfPlayerImage::query()->create([
            'player_user_id' => $player->id_user,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $player->id_user . '/true.png',
        ]);

        $hit = CachedPdfPlayerImage::urlForPlayer((int) $player->id_user, true);
        $miss = CachedPdfPlayerImage::urlForPlayer((int) $player->id_user, false);

        $this->assertNotNull($hit);
        $this->assertNull($miss);
    }

    public function test_urls_by_player_collecting_pairs_batch_keys(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        CachedPdfPlayerImage::query()->create([
            'player_user_id' => $a->id_user,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $a->id_user . '/true.png',
        ]);
        CachedPdfPlayerImage::query()->create([
            'player_user_id' => $b->id_user,
            'player_is_collecting_points' => false,
            'picture_path' => 'cached-pdf-player-images/' . $b->id_user . '/false.png',
        ]);

        $map = CachedPdfPlayerImage::urlsByPlayerCollectingPairs([
            ['player_user_id' => (int) $a->id_user, 'collecting' => true],
            ['player_user_id' => (int) $a->id_user, 'collecting' => false],
            ['player_user_id' => (int) $b->id_user, 'collecting' => false],
        ]);

        $this->assertNotNull($map[$a->id_user . ':1']);
        $this->assertNull($map[$a->id_user . ':0']);
        $this->assertNotNull($map[$b->id_user . ':0']);
    }

    public function test_url_for_player_prefer_collecting_falls_back(): void
    {
        $player = User::factory()->create();
        CachedPdfPlayerImage::query()->create([
            'player_user_id' => $player->id_user,
            'player_is_collecting_points' => false,
            'picture_path' => 'cached-pdf-player-images/' . $player->id_user . '/false.png',
        ]);

        $url = CachedPdfPlayerImage::urlForPlayerPreferCollecting((int) $player->id_user);
        $this->assertNotNull($url);
        $this->assertSame(
            CachedPdfPlayerImage::urlForPlayer((int) $player->id_user, false),
            $url
        );
    }
}
