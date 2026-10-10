<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Player;
use App\Models\PlayerIdentity;
use App\Models\PlayerProfileImage;
use App\Services\PlayerIdentity\PlayerAvatarResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PlayerAvatarResolutionTest extends TestCase
{
    use RefreshDatabase;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($this->player);
    }

    private function identity(string $subject, ?string $avatar, int $linkedAt, string $provider = PlayerIdentity::PROVIDER_GOOGLE, ?Player $owner = null): PlayerIdentity
    {
        return PlayerIdentity::query()->create([
            'player_id' => ($owner ?? $this->player)->id_player,
            'provider' => $provider,
            'provider_subject' => $subject,
            'display_name' => 'Name ' . $subject,
            'avatar_url' => $avatar,
            'linked_at' => $linkedAt,
        ]);
    }

    private function meAvatar(): mixed
    {
        return $this->getJson('/api/v1/app/auth/me')->assertOk()->json('data.avatar_url');
    }

    public function test_first_google_link_with_avatar_becomes_primary(): void
    {
        $first = $this->identity('g-1', 'https://img.test/1.jpg', 100);
        $second = $this->identity('g-2', 'https://img.test/2.jpg', 200);

        $this->assertTrue($first->fresh()->is_avatar_primary);
        $this->assertFalse($second->fresh()->is_avatar_primary);
    }

    public function test_non_google_or_avatarless_identities_are_not_promoted(): void
    {
        $noAvatar = $this->identity('g-0', null, 50);
        $apple = $this->identity('a-1', 'https://img.test/a.jpg', 60, PlayerIdentity::PROVIDER_APPLE);

        $this->assertFalse($noAvatar->fresh()->is_avatar_primary);
        $this->assertFalse($apple->fresh()->is_avatar_primary);
    }

    public function test_me_returns_the_primary_google_avatar(): void
    {
        $this->identity('g-1', 'https://img.test/1.jpg', 100);
        $this->identity('g-2', 'https://img.test/2.jpg', 200);

        $this->assertSame('https://img.test/1.jpg', $this->meAvatar());
    }

    public function test_me_falls_back_to_the_latest_linked_google_avatar_when_none_is_primary(): void
    {
        $this->identity('g-1', 'https://img.test/1.jpg', 100);
        $this->identity('g-2', 'https://img.test/2.jpg', 200);
        PlayerIdentity::query()->where('player_id', $this->player->id_player)->update(['is_avatar_primary' => false]);

        $this->assertSame('https://img.test/2.jpg', $this->meAvatar());
    }

    public function test_me_avatar_is_null_without_google_and_ignores_pdf_and_uploaded_images(): void
    {
        PlayerProfileImage::factory()->create([
            'player_id' => $this->player->id_player,
            'is_pdf_primary' => true,
        ]);
        $this->identity('a-1', 'https://img.test/apple.jpg', 100, PlayerIdentity::PROVIDER_APPLE);

        $response = $this->getJson('/api/v1/app/auth/me')->assertOk();

        $this->assertNull($response->json('data.avatar_url'));
        $this->assertArrayNotHasKey('pdf_avatar_url', $response->json('data'));
        $this->assertArrayNotHasKey('profile_image_url', $response->json('data'));
    }

    public function test_players_endpoint_also_exposes_the_google_avatar(): void
    {
        $this->identity('g-1', 'https://img.test/1.jpg', 100);

        $this->getJson('/api/v1/app/players/' . $this->player->id_player)->assertOk();
        $this->assertSame(
            'https://img.test/1.jpg',
            app(PlayerAvatarResolver::class)->resolveForApp($this->player->fresh())
        );
    }

    public function test_set_avatar_primary_moves_the_flag_exclusively(): void
    {
        $first = $this->identity('g-1', 'https://img.test/1.jpg', 100);
        $second = $this->identity('g-2', 'https://img.test/2.jpg', 200);

        $this->postJson('/api/v1/app/auth/identities/' . $second->id_identity . '/avatar-primary')
            ->assertOk()
            ->assertJsonPath('data.is_avatar_primary', true);

        $this->assertFalse($first->fresh()->is_avatar_primary);
        $this->assertTrue($second->fresh()->is_avatar_primary);
        $this->assertSame('https://img.test/2.jpg', $this->meAvatar());
    }

    public function test_set_avatar_primary_rejects_other_players_identities(): void
    {
        $other = Player::factory()->playerOnly()->create();
        $foreign = $this->identity('g-9', 'https://img.test/9.jpg', 100, PlayerIdentity::PROVIDER_GOOGLE, $other);

        $this->postJson('/api/v1/app/auth/identities/' . $foreign->id_identity . '/avatar-primary')
            ->assertForbidden();
    }

    public function test_set_avatar_primary_rejects_non_google_and_avatarless_identities(): void
    {
        $apple = $this->identity('a-1', 'https://img.test/a.jpg', 100, PlayerIdentity::PROVIDER_APPLE);
        $bare = $this->identity('g-0', null, 100);

        $this->postJson('/api/v1/app/auth/identities/' . $apple->id_identity . '/avatar-primary')
            ->assertStatus(422);
        $this->postJson('/api/v1/app/auth/identities/' . $bare->id_identity . '/avatar-primary')
            ->assertStatus(422);
    }
}
