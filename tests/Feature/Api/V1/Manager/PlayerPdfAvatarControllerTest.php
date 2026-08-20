<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\CachedPdfPlayerImage;
use App\Models\Player;
use App\Models\PlayerProfileImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PlayerPdfAvatarControllerTest extends TestCase
{
    use RefreshDatabase;

    private function solidPng(): string
    {
        $img = imagecreatetruecolor(80, 80);
        $red = imagecolorallocate($img, 255, 0, 0);
        imagefill($img, 0, 0, $red);
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    private function attachProfile(Player $player, int $sortOrder = 0): PlayerProfileImage
    {
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $relative = 'player-profile-images/' . $player->id_player . '/profile/avatar.png';
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))->put($relative, $this->solidPng());

        return PlayerProfileImage::query()->create([
            'player_id' => $player->id_player,
            'profile_path' => $relative,
            'sort_order' => $sortOrder,
            'inserted_at' => time(),
        ]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $player = Player::factory()->create();
        $this->postJson("/api/v1/manager/players/{$player->id_player}/pdf-avatar", [
            'is_collecting_points' => true,
        ])->assertStatus(401);
    }

    public function test_non_manager_returns_403(): void
    {
        $player = Player::factory()->create([
            'user_types' => ['player'],
        ]);
        Sanctum::actingAs($player);

        $this->postJson("/api/v1/manager/players/{$player->id_player}/pdf-avatar", [
            'is_collecting_points' => true,
        ])->assertStatus(403);
    }

    public function test_missing_is_collecting_points_returns_422(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);
        $player = Player::factory()->create();

        $this->postJson("/api/v1/manager/players/{$player->id_player}/pdf-avatar", [])
            ->assertStatus(422);
    }

    public function test_missing_profile_image_returns_initials_avatar_with_null_source(): void
    {
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);
        $player = Player::factory()->create();

        $res = $this->postJson("/api/v1/manager/players/{$player->id_player}/pdf-avatar", [
            'is_collecting_points' => true,
        ]);
        $res->assertOk();
        $this->assertNotEmpty($res->json('data.pdf_avatar_url'));

        $row = CachedPdfPlayerImage::query()->where('player_id', $player->id_player)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->source_player_profile_image_id);
        $this->assertStringStartsWith('cached-pdf-player-images/', (string) $row->picture_path);
    }

    public function test_generates_pdf_avatar_url_keyed_by_collecting_flag(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);
        $player = Player::factory()->create();
        $profile = $this->attachProfile($player);

        $res = $this->postJson("/api/v1/manager/players/{$player->id_player}/pdf-avatar", [
            'is_collecting_points' => true,
        ]);
        $res->assertOk();
        $url = $res->json('data.pdf_avatar_url');
        $this->assertNotEmpty($url);

        $row = CachedPdfPlayerImage::query()
            ->where('player_id', $player->id_player)
            ->where('player_is_collecting_points', true)
            ->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->picture_path);
        $this->assertSame((int) $profile->id, (int) $row->source_player_profile_image_id);
    }

    public function test_second_call_does_not_bump_updated_at_when_cache_fresh(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);
        $player = Player::factory()->create();
        $profile = $this->attachProfile($player);

        $this->postJson("/api/v1/manager/players/{$player->id_player}/pdf-avatar", [
            'is_collecting_points' => true,
        ])->assertOk();

        $disk = config('filesystems.user_uploads_disk', 'public');
        $abs = Storage::disk($disk)->path((string) $profile->profile_path);
        touch($abs, 1_600_000_000);

        $row = CachedPdfPlayerImage::query()
            ->where('player_id', $player->id_player)
            ->where('player_is_collecting_points', true)
            ->firstOrFail();
        $frozen = time();
        $row->forceFill(['updated_at' => $frozen, 'inserted_at' => $frozen])->save();

        $this->postJson("/api/v1/manager/players/{$player->id_player}/pdf-avatar", [
            'is_collecting_points' => true,
        ])->assertOk();

        $row->refresh();
        $this->assertSame($frozen, (int) $row->updated_at);
    }

    public function test_collecting_and_non_collecting_create_distinct_rows(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);
        $player = Player::factory()->create();
        $this->attachProfile($player);

        $this->postJson("/api/v1/manager/players/{$player->id_player}/pdf-avatar", [
            'is_collecting_points' => true,
        ])->assertOk();
        $this->postJson("/api/v1/manager/players/{$player->id_player}/pdf-avatar", [
            'is_collecting_points' => false,
        ])->assertOk();

        $this->assertSame(2, CachedPdfPlayerImage::query()->where('player_id', $player->id_player)->count());
    }
}
