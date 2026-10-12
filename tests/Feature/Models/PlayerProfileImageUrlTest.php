<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\User;
use App\Models\PlayerProfileImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PlayerProfileImageUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_is_always_absolute_http(): void
    {
        $disk = config('filesystems.user_uploads_disk', 'public');
        Storage::fake($disk);

        $player = User::factory()->create();
        $image = PlayerProfileImage::factory()->create([
            'player_user_id' => $player->id_user,
            'profile_path' => 'player-profile-images/' . $player->id_user . '/profile/a.png',
        ]);
        Storage::disk($disk)->put($image->profile_path, 'a');

        $this->assertMatchesRegularExpression('#^https?://#i', $image->url);
        $this->assertStringContainsString('player-profile-images/', $image->url);
    }
}
