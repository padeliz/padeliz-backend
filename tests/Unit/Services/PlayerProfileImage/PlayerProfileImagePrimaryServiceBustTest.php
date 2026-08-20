<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerProfileImage;

use App\Models\CachedPdfPairImage;
use App\Models\CachedPdfPlayerImage;
use App\Models\Player;
use App\Models\PlayerProfileImage;
use App\Services\PlayerProfileImage\PlayerProfileImagePrimaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PlayerProfileImagePrimaryServiceBustTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
    }

    public function test_bust_pdf_cache_deletes_player_and_pair_rows(): void
    {
        $player = Player::factory()->create();
        $other = Player::factory()->create();
        $left = min((int) $player->id_player, (int) $other->id_player);
        $right = max((int) $player->id_player, (int) $other->id_player);

        CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => null,
            'inserted_at' => time(),
        ]);
        CachedPdfPairImage::query()->create([
            'player_1_id' => $left,
            'player_2_id' => $right,
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
            'picture_path' => null,
            'inserted_at' => time(),
        ]);
        CachedPdfPlayerImage::query()->create([
            'player_id' => $other->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => null,
            'inserted_at' => time(),
        ]);

        app(PlayerProfileImagePrimaryService::class)->bustPdfCache((int) $player->id_player);

        $this->assertSame(0, CachedPdfPlayerImage::query()->where('player_id', $player->id_player)->count());
        $this->assertSame(0, CachedPdfPairImage::query()->count());
        $this->assertSame(1, CachedPdfPlayerImage::query()->where('player_id', $other->id_player)->count());
    }

    public function test_deleting_last_profile_image_busts_pdf_cache(): void
    {
        $player = Player::factory()->create();
        $image = PlayerProfileImage::factory()->create([
            'player_id' => $player->id_player,
            'is_pdf_primary' => true,
            'sort_order' => 0,
        ]);

        CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => null,
            'inserted_at' => time(),
        ]);

        $image->delete();
        $player->unsetRelation('profileImages');
        app(PlayerProfileImagePrimaryService::class)->promoteSurvivorAfterDelete($player->fresh());

        $this->assertSame(0, CachedPdfPlayerImage::query()->where('player_id', $player->id_player)->count());
    }

    public function test_source_fk_columns_exist_on_cache_tables(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn(
            'cached_pdf_player_images',
            'source_player_profile_image_id'
        ));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn(
            'cached_pdf_pair_images',
            'source_player_1_profile_image_id'
        ));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn(
            'cached_pdf_pair_images',
            'source_player_2_profile_image_id'
        ));
    }
}
