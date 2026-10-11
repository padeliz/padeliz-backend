<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\CachedPdfPlayerImage;
use App\Models\User;
use App\Models\PlayerProfileImage;
use App\Services\Pdf\PdfCachedImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\PdfFixtures;
use Tests\TestCase;

final class PlayerPdfAvatarControllerTest extends TestCase
{
    use RefreshDatabase;

    private function attachProfile(User $player, int $sortOrder = 0): PlayerProfileImage
    {
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $relative = 'player-profile-images/' . $player->id_user . '/profile/avatar.png';
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))
            ->put($relative, PdfFixtures::solidRed80Bytes());

        return PlayerProfileImage::query()->create([
            'player_user_id' => $player->id_user,
            'profile_path' => $relative,
            'sort_order' => $sortOrder,
            'inserted_at' => time(),
        ]);
    }

    private function mockAvatarUrl(string $url): void
    {
        $this->mock(PdfCachedImageGenerator::class, function ($mock) use ($url): void {
            $mock->shouldReceive('generatePlayerAvatarUrl')
                ->andReturn($url);
        });
    }

    public function test_unauthenticated_returns_401(): void
    {
        $player = User::factory()->create();
        $this->postJson("/api/v1/manager/players/{$player->id_user}/pdf-avatar", [
            'is_collecting_points' => true,
        ])->assertStatus(401);
    }

    public function test_non_manager_returns_403(): void
    {
        $player = User::factory()->create([
            'user_types' => ['player'],
        ]);
        Sanctum::actingAs($player);

        $this->postJson("/api/v1/manager/players/{$player->id_user}/pdf-avatar", [
            'is_collecting_points' => true,
        ])->assertStatus(403);
    }

    public function test_missing_is_collecting_points_returns_422(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);
        $player = User::factory()->create();

        $this->postJson("/api/v1/manager/players/{$player->id_user}/pdf-avatar", [])
            ->assertStatus(422);
    }

    public function test_missing_profile_image_returns_initials_avatar_with_null_source(): void
    {
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);
        $player = User::factory()->create();

        $this->mock(PdfCachedImageGenerator::class, function ($mock) use ($player): void {
            $mock->shouldReceive('generatePlayerAvatarUrl')
                ->once()
                ->andReturnUsing(function () use ($player): string {
                    $row = CachedPdfPlayerImage::query()->create([
                        'player_user_id' => $player->id_user,
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

                    return '/storage/' . $relative;
                });
        });

        $res = $this->postJson("/api/v1/manager/players/{$player->id_user}/pdf-avatar", [
            'is_collecting_points' => true,
        ]);
        $res->assertOk();
        $this->assertNotEmpty($res->json('data.pdf_avatar_url'));

        $row = CachedPdfPlayerImage::query()->where('player_user_id', $player->id_user)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->source_player_profile_image_id);
        $this->assertStringStartsWith('cached-pdf-player-images/', (string) $row->picture_path);
    }

    public function test_generates_pdf_avatar_url_keyed_by_collecting_flag(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);
        $player = User::factory()->create();
        $profile = $this->attachProfile($player);

        $this->mock(PdfCachedImageGenerator::class, function ($mock) use ($player, $profile): void {
            $mock->shouldReceive('generatePlayerAvatarUrl')
                ->once()
                ->andReturnUsing(function () use ($player, $profile): string {
                    $row = CachedPdfPlayerImage::query()->create([
                        'player_user_id' => $player->id_user,
                        'player_is_collecting_points' => true,
                        'picture_path' => null,
                        'source_player_profile_image_id' => (int) $profile->id,
                        'inserted_at' => time(),
                    ]);
                    $relative = 'cached-pdf-player-images/' . $row->id_cached_pdf_player_image . '/picture/avatar.png';
                    Storage::disk(config('filesystems.user_uploads_disk', 'public'))
                        ->put($relative, PdfFixtures::circularRed200Bytes());
                    $row->picture_path = $relative;
                    $row->save();

                    return '/storage/' . $relative;
                });
        });

        $res = $this->postJson("/api/v1/manager/players/{$player->id_user}/pdf-avatar", [
            'is_collecting_points' => true,
        ]);
        $res->assertOk();
        $url = $res->json('data.pdf_avatar_url');
        $this->assertNotEmpty($url);

        $row = CachedPdfPlayerImage::query()
            ->where('player_user_id', $player->id_user)
            ->where('player_is_collecting_points', true)
            ->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->picture_path);
        $this->assertSame((int) $profile->id, (int) $row->source_player_profile_image_id);
    }

    public function test_second_call_does_not_bump_updated_at_when_cache_fresh(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);
        $player = User::factory()->create();
        $profile = $this->attachProfile($player);

        $row = CachedPdfPlayerImage::query()->create([
            'player_user_id' => $player->id_user,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/1/picture/avatar.png',
            'source_player_profile_image_id' => (int) $profile->id,
            'inserted_at' => time(),
        ]);
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))
            ->put((string) $row->picture_path, PdfFixtures::circularRed200Bytes());
        $frozen = time();
        $row->forceFill(['updated_at' => $frozen, 'inserted_at' => $frozen])->save();

        $this->mockAvatarUrl('/storage/' . $row->picture_path);

        $this->postJson("/api/v1/manager/players/{$player->id_user}/pdf-avatar", [
            'is_collecting_points' => true,
        ])->assertOk();

        $row->refresh();
        $this->assertSame($frozen, (int) $row->updated_at);
    }

    public function test_collecting_and_non_collecting_create_distinct_rows(): void
    {
        $manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);
        $player = User::factory()->create();
        $this->attachProfile($player);

        $this->mock(PdfCachedImageGenerator::class, function ($mock) use ($player): void {
            $mock->shouldReceive('generatePlayerAvatarUrl')
                ->twice()
                ->andReturnUsing(function (User $p, bool $collecting) use ($player): string {
                    $this->assertSame((int) $player->id_user, (int) $p->id_user);
                    CachedPdfPlayerImage::query()->firstOrCreate(
                        [
                            'player_user_id' => $player->id_user,
                            'player_is_collecting_points' => $collecting,
                        ],
                        [
                            'picture_path' => 'cached-pdf-player-images/' . ($collecting ? 'c' : 'n') . '/picture/avatar.png',
                            'inserted_at' => time(),
                        ]
                    );

                    return '/storage/fake-' . ($collecting ? 'c' : 'n') . '.png';
                });
        });

        $this->postJson("/api/v1/manager/players/{$player->id_user}/pdf-avatar", [
            'is_collecting_points' => true,
        ])->assertOk();
        $this->postJson("/api/v1/manager/players/{$player->id_user}/pdf-avatar", [
            'is_collecting_points' => false,
        ])->assertOk();

        $this->assertSame(2, CachedPdfPlayerImage::query()->where('player_user_id', $player->id_user)->count());
    }
}
