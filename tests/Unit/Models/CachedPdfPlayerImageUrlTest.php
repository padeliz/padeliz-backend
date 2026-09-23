<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\CachedPdfPlayerImage;
use App\Models\Player;
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
        $player = Player::factory()->create();
        CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $player->id_player . '/true.png',
        ]);

        $hit = CachedPdfPlayerImage::urlForPlayer((int) $player->id_player, true);
        $miss = CachedPdfPlayerImage::urlForPlayer((int) $player->id_player, false);

        $this->assertNotNull($hit);
        $this->assertNull($miss);
    }

    public function test_urls_by_player_collecting_pairs_batch_keys(): void
    {
        $a = Player::factory()->create();
        $b = Player::factory()->create();
        CachedPdfPlayerImage::query()->create([
            'player_id' => $a->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $a->id_player . '/true.png',
        ]);
        CachedPdfPlayerImage::query()->create([
            'player_id' => $b->id_player,
            'player_is_collecting_points' => false,
            'picture_path' => 'cached-pdf-player-images/' . $b->id_player . '/false.png',
        ]);

        $map = CachedPdfPlayerImage::urlsByPlayerCollectingPairs([
            ['player_id' => (int) $a->id_player, 'collecting' => true],
            ['player_id' => (int) $a->id_player, 'collecting' => false],
            ['player_id' => (int) $b->id_player, 'collecting' => false],
        ]);

        $this->assertNotNull($map[$a->id_player . ':1']);
        $this->assertNull($map[$a->id_player . ':0']);
        $this->assertNotNull($map[$b->id_player . ':0']);
    }

    public function test_url_for_player_prefer_collecting_falls_back(): void
    {
        $player = Player::factory()->create();
        CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => false,
            'picture_path' => 'cached-pdf-player-images/' . $player->id_player . '/false.png',
        ]);

        $url = CachedPdfPlayerImage::urlForPlayerPreferCollecting((int) $player->id_player);
        $this->assertNotNull($url);
        $this->assertSame(
            CachedPdfPlayerImage::urlForPlayer((int) $player->id_player, false),
            $url
        );
    }
}
