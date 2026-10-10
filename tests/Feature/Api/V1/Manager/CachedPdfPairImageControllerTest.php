<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\CachedPdfPairImage;
use App\Models\Player;
use App\Models\PlayerProfileImage;
use App\Services\Pdf\PdfCachedImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\PdfFixtures;
use Tests\TestCase;

final class CachedPdfPairImageControllerTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/manager/cached-pdf-pair-images';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
    }

    private function actAsManager(): void
    {
        Sanctum::actingAs(Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]));
    }

    private function attachProfile(Player $player): PlayerProfileImage
    {
        $relative = 'player-profile-images/' . $player->id_player . '/profile/avatar.png';
        Storage::disk(config('filesystems.user_uploads_disk', 'public'))
            ->put($relative, PdfFixtures::solidBlue80Bytes());

        return PlayerProfileImage::query()->create([
            'player_id' => $player->id_player,
            'profile_path' => $relative,
            'sort_order' => 0,
            'inserted_at' => time(),
        ]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $this->postJson(self::ENDPOINT, [
            'player_1_id' => $p1->id_player,
            'player_2_id' => $p2->id_player,
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
        ])->assertStatus(401);
    }

    public function test_non_manager_returns_403(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        Sanctum::actingAs(Player::factory()->create(['user_types' => ['player']]));

        $this->postJson(self::ENDPOINT, [
            'player_1_id' => $p1->id_player,
            'player_2_id' => $p2->id_player,
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
        ])->assertStatus(403);
    }

    public function test_validation_errors(): void
    {
        $this->actAsManager();
        $player = Player::factory()->create();

        $this->postJson(self::ENDPOINT, [])->assertStatus(422);

        $this->postJson(self::ENDPOINT, [
            'player_1_id' => $player->id_player,
            'player_2_id' => $player->id_player,
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
        ])->assertStatus(422);

        $this->postJson(self::ENDPOINT, [
            'player_1_id' => $player->id_player,
            'player_2_id' => 999999,
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
        ])->assertStatus(422);
    }

    public function test_generates_pair_image_with_profiles(): void
    {
        $this->actAsManager();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $profile1 = $this->attachProfile($p1);
        $profile2 = $this->attachProfile($p2);

        $this->mock(PdfCachedImageGenerator::class, function ($mock) use ($p1, $p2, $profile1, $profile2): void {
            $mock->shouldReceive('generatePairImageUrl')
                ->once()
                ->andReturnUsing(function () use ($p1, $p2, $profile1, $profile2): string {
                    $left = min((int) $p1->id_player, (int) $p2->id_player);
                    $right = max((int) $p1->id_player, (int) $p2->id_player);
                    $ids = [(int) $profile1->id, (int) $profile2->id];
                    sort($ids);
                    $row = CachedPdfPairImage::query()->create([
                        'player_1_id' => $left,
                        'player_2_id' => $right,
                        'player_1_is_collecting_points' => true,
                        'player_2_is_collecting_points' => true,
                        'picture_path' => null,
                        'source_player_1_profile_image_id' => $ids[0],
                        'source_player_2_profile_image_id' => $ids[1],
                        'inserted_at' => time(),
                    ]);
                    $relative = 'cached-pdf-pair-images/' . $row->id_cached_pdf_pair_image . '/picture/couple.png';
                    Storage::disk(config('filesystems.user_uploads_disk', 'public'))
                        ->put($relative, PdfFixtures::circularRed200Bytes());
                    $row->picture_path = $relative;
                    $row->save();

                    return '/storage/' . $relative;
                });
        });

        $res = $this->postJson(self::ENDPOINT, [
            'player_1_id' => $p1->id_player,
            'player_2_id' => $p2->id_player,
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
        ]);

        $res->assertOk();
        $this->assertNotEmpty($res->json('data.couple_image_url'));

        $row = CachedPdfPairImage::query()->firstOrFail();
        $this->assertStringStartsWith('cached-pdf-pair-images/', (string) $row->picture_path);
        $expected = [(int) $profile1->id, (int) $profile2->id];
        sort($expected);
        $actual = [
            (int) $row->source_player_1_profile_image_id,
            (int) $row->source_player_2_profile_image_id,
        ];
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    public function test_generates_pair_image_when_players_have_no_profiles(): void
    {
        $this->actAsManager();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $this->mock(PdfCachedImageGenerator::class, function ($mock) use ($p1, $p2): void {
            $mock->shouldReceive('generatePairImageUrl')
                ->once()
                ->andReturnUsing(function () use ($p1, $p2): string {
                    $left = min((int) $p1->id_player, (int) $p2->id_player);
                    $right = max((int) $p1->id_player, (int) $p2->id_player);
                    $row = CachedPdfPairImage::query()->create([
                        'player_1_id' => $left,
                        'player_2_id' => $right,
                        'player_1_is_collecting_points' => true,
                        'player_2_is_collecting_points' => false,
                        'picture_path' => null,
                        'source_player_1_profile_image_id' => null,
                        'source_player_2_profile_image_id' => null,
                        'inserted_at' => time(),
                    ]);
                    $relative = 'cached-pdf-pair-images/' . $row->id_cached_pdf_pair_image . '/picture/couple.png';
                    Storage::disk(config('filesystems.user_uploads_disk', 'public'))
                        ->put($relative, PdfFixtures::circularRed200Bytes());
                    $row->picture_path = $relative;
                    $row->save();

                    return '/storage/' . $relative;
                });
        });

        $res = $this->postJson(self::ENDPOINT, [
            'player_1_id' => $p1->id_player,
            'player_2_id' => $p2->id_player,
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => false,
        ]);

        $res->assertOk();
        $this->assertNotEmpty($res->json('data.couple_image_url'));

        $row = CachedPdfPairImage::query()->firstOrFail();
        $this->assertNull($row->source_player_1_profile_image_id);
        $this->assertNull($row->source_player_2_profile_image_id);
    }

    public function test_reversed_player_order_reuses_same_cache_row(): void
    {
        $this->actAsManager();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $low = min((int) $p1->id_player, (int) $p2->id_player);
        $high = max((int) $p1->id_player, (int) $p2->id_player);

        $this->mock(PdfCachedImageGenerator::class, function ($mock) use ($low, $high): void {
            $mock->shouldReceive('generatePairImageUrl')
                ->twice()
                ->andReturnUsing(function () use ($low, $high): string {
                    CachedPdfPairImage::query()->firstOrCreate(
                        [
                            'player_1_id' => $low,
                            'player_2_id' => $high,
                            'player_1_is_collecting_points' => true,
                            'player_2_is_collecting_points' => true,
                        ],
                        [
                            'picture_path' => 'cached-pdf-pair-images/reuse/picture/couple.png',
                            'inserted_at' => time(),
                        ]
                    );

                    return '/storage/cached-pdf-pair-images/reuse/picture/couple.png';
                });
        });

        $this->postJson(self::ENDPOINT, [
            'player_1_id' => $low,
            'player_2_id' => $high,
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
        ])->assertOk();

        $this->postJson(self::ENDPOINT, [
            'player_1_id' => $high,
            'player_2_id' => $low,
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
        ])->assertOk();

        $this->assertSame(1, CachedPdfPairImage::query()->count());
    }

    public function test_collecting_combination_creates_distinct_rows(): void
    {
        $this->actAsManager();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $this->mock(PdfCachedImageGenerator::class, function ($mock) use ($p1, $p2): void {
            $mock->shouldReceive('generatePairImageUrl')
                ->twice()
                ->andReturnUsing(function (Player $a, Player $b, bool $c1, bool $c2): string {
                    $idA = (int) $a->id_player;
                    $idB = (int) $b->id_player;
                    if ($idA <= $idB) {
                        $left = $idA;
                        $right = $idB;
                        $leftC = $c1;
                        $rightC = $c2;
                    } else {
                        $left = $idB;
                        $right = $idA;
                        $leftC = $c2;
                        $rightC = $c1;
                    }
                    CachedPdfPairImage::query()->firstOrCreate(
                        [
                            'player_1_id' => $left,
                            'player_2_id' => $right,
                            'player_1_is_collecting_points' => $leftC,
                            'player_2_is_collecting_points' => $rightC,
                        ],
                        [
                            'picture_path' => 'cached-pdf-pair-images/' . ($leftC ? '1' : '0') . ($rightC ? '1' : '0') . '/picture/couple.png',
                            'inserted_at' => time(),
                        ]
                    );

                    return '/storage/fake-pair.png';
                });
        });

        $this->postJson(self::ENDPOINT, [
            'player_1_id' => $p1->id_player,
            'player_2_id' => $p2->id_player,
            'player_1_is_collecting_points' => true,
            'player_2_is_collecting_points' => true,
        ])->assertOk();

        $this->postJson(self::ENDPOINT, [
            'player_1_id' => $p1->id_player,
            'player_2_id' => $p2->id_player,
            'player_1_is_collecting_points' => false,
            'player_2_is_collecting_points' => true,
        ])->assertOk();

        $this->assertSame(2, CachedPdfPairImage::query()->count());
    }
}
