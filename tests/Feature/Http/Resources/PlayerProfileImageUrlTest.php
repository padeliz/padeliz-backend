<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Resources;

use App\Http\Resources\PlayerResource;
use App\Models\CachedPdfPlayerImage;
use App\Models\Player;
use App\Models\PlayerProfileImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlayerProfileImageUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_image_url_null_when_no_uploads(): void
    {
        $player = Player::factory()->create();

        $payload = (new PlayerResource($player->fresh()))->toArray(Request::create('/'));

        $this->assertArrayHasKey('profile_image_url', $payload);
        $this->assertNull($payload['profile_image_url']);
    }

    public function test_profile_image_url_prefers_pdf_primary(): void
    {
        $player = Player::factory()->create();
        $disk = config('filesystems.user_uploads_disk', 'public');
        Storage::fake($disk);

        $first = PlayerProfileImage::factory()->create([
            'player_id' => $player->id_player,
            'sort_order' => 0,
            'is_pdf_primary' => false,
            'profile_path' => 'player-profile-images/' . $player->id_player . '/profile/a.png',
        ]);
        Storage::disk($disk)->put($first->profile_path, 'a');

        $primary = PlayerProfileImage::factory()->create([
            'player_id' => $player->id_player,
            'sort_order' => 1,
            'is_pdf_primary' => true,
            'profile_path' => 'player-profile-images/' . $player->id_player . '/profile/b.png',
        ]);
        Storage::disk($disk)->put($primary->profile_path, 'b');

        $payload = (new PlayerResource($player->fresh()))->toArray(Request::create('/'));

        $this->assertSame($primary->url, $payload['profile_image_url']);
        $this->assertNotSame($first->url, $payload['profile_image_url']);
    }

    public function test_profile_image_url_falls_back_to_first_by_sort_order(): void
    {
        $player = Player::factory()->create();
        $disk = config('filesystems.user_uploads_disk', 'public');
        Storage::fake($disk);

        PlayerProfileImage::factory()->create([
            'player_id' => $player->id_player,
            'sort_order' => 1,
            'is_pdf_primary' => false,
            'profile_path' => 'player-profile-images/' . $player->id_player . '/profile/later.png',
        ]);
        $first = PlayerProfileImage::factory()->create([
            'player_id' => $player->id_player,
            'sort_order' => 0,
            'is_pdf_primary' => false,
            'profile_path' => 'player-profile-images/' . $player->id_player . '/profile/first.png',
        ]);
        Storage::disk($disk)->put($first->profile_path, 'x');

        $payload = (new PlayerResource($player->fresh()))->toArray(Request::create('/'));

        $this->assertSame($first->url, $payload['profile_image_url']);
    }

    public function test_profile_image_url_independent_of_pdf_avatar_url(): void
    {
        $player = Player::factory()->create();
        $disk = config('filesystems.user_uploads_disk', 'public');
        Storage::fake($disk);

        $image = PlayerProfileImage::factory()->create([
            'player_id' => $player->id_player,
            'sort_order' => 0,
            'is_pdf_primary' => true,
            'profile_path' => 'player-profile-images/' . $player->id_player . '/profile/p.png',
        ]);
        Storage::disk($disk)->put($image->profile_path, 'p');

        CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf/avatars/' . $player->id_player . '.png',
            'source_player_profile_image_id' => $image->id,
        ]);

        $fresh = $player->fresh()->load(['cachedPdfImages', 'profileImages']);
        $payload = (new PlayerResource($fresh))->toArray(Request::create('/'));

        $this->assertSame($image->url, $payload['profile_image_url']);
        $this->assertNotNull($payload['pdf_avatar_url']);
        $this->assertNotSame($payload['pdf_avatar_url'], $payload['profile_image_url']);
    }
}
