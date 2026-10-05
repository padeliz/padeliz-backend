<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Player;
use App\Models\PlayerEmail;
use App\Models\PlayerIdentity;
use Google\Client as GoogleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

final class PlayerIdentityControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogleToken(array $payload): void
    {
        $this->app->bind(GoogleClient::class, function () use ($payload) {
            $mock = Mockery::mock(GoogleClient::class);
            $mock->shouldReceive('verifyIdToken')->andReturn($payload);

            return $mock;
        });
    }

    private function mockInvalidGoogleToken(): void
    {
        $this->app->bind(GoogleClient::class, function () {
            $mock = Mockery::mock(GoogleClient::class);
            $mock->shouldReceive('verifyIdToken')->andReturn(false);

            return $mock;
        });
    }

    public function test_list_identities_requires_auth(): void
    {
        $this->getJson('/api/v1/app/auth/identities')->assertStatus(401);
    }

    public function test_list_identities_returns_all_providers(): void
    {
        $player = Player::factory()->playerOnly()->withGoogleIdentity('g-1')->create();
        PlayerIdentity::factory()->create([
            'player_id' => $player->id_player,
            'provider' => PlayerIdentity::PROVIDER_DEV,
            'provider_subject' => 'dev:x',
        ]);
        Sanctum::actingAs($player);

        $this->getJson('/api/v1/app/auth/identities')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_link_google_identity_success(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);
        $this->mockGoogleToken([
            'sub' => 'link-sub-1',
            'email' => 'link1@example.com',
            'name' => 'Link One',
            'picture' => 'https://example.com/a.png',
        ]);

        $this->postJson('/api/v1/app/auth/identities/google', [
            'id_token' => 'token',
        ])
            ->assertOk()
            ->assertJsonPath('data.result_code', 'linked')
            ->assertJsonPath('data.identity.email', 'link1@example.com');

        $this->assertDatabaseHas('player_identities', [
            'player_id' => $player->id_player,
            'provider_subject' => 'link-sub-1',
        ]);
    }

    public function test_link_google_idempotent(): void
    {
        $player = Player::factory()->playerOnly()->withGoogleIdentity('idem-sub')->create();
        Sanctum::actingAs($player);
        $this->mockGoogleToken([
            'sub' => 'idem-sub',
            'email' => 'idem@example.com',
            'name' => 'Idem',
            'picture' => null,
        ]);

        $this->postJson('/api/v1/app/auth/identities/google', [
            'id_token' => 'token',
        ])
            ->assertOk()
            ->assertJsonPath('data.result_code', 'already_linked');

        $this->assertSame(1, PlayerIdentity::query()->where('provider_subject', 'idem-sub')->count());
    }

    public function test_link_google_identity_conflict(): void
    {
        Player::factory()->playerOnly()->withGoogleIdentity('taken-sub')->create();
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);
        $this->mockGoogleToken([
            'sub' => 'taken-sub',
            'email' => 'other@example.com',
            'name' => 'X',
            'picture' => null,
        ]);

        $this->postJson('/api/v1/app/auth/identities/google', [
            'id_token' => 'token',
        ])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'identity_conflict');
    }

    public function test_link_google_contact_conflict(): void
    {
        $owner = Player::factory()->playerOnly()->create();
        PlayerEmail::factory()->create([
            'player_id' => $owner->id_player,
            'email' => 'owned@example.com',
            'is_primary' => true,
        ]);
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);
        $this->mockGoogleToken([
            'sub' => 'contact-sub',
            'email' => 'owned@example.com',
            'name' => 'X',
            'picture' => null,
        ]);

        $this->postJson('/api/v1/app/auth/identities/google', [
            'id_token' => 'token',
        ])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'contact_conflict');
    }

    public function test_link_unsupported_provider(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $this->postJson('/api/v1/app/auth/identities/apple', [
            'id_token' => 'token',
        ])->assertStatus(422);
    }

    public function test_link_missing_id_token(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $this->postJson('/api/v1/app/auth/identities/google', [])
            ->assertStatus(422);
    }

    public function test_link_invalid_id_token(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);
        $this->mockInvalidGoogleToken();

        $this->postJson('/api/v1/app/auth/identities/google', [
            'id_token' => 'bad',
        ])->assertStatus(401);
    }

    public function test_unlink_second_google_keeps_email(): void
    {
        $player = Player::factory()->playerOnly()->create();
        $email = PlayerEmail::factory()->create([
            'player_id' => $player->id_player,
            'email' => 'multi@example.com',
            'purposes' => ['contact', 'login'],
            'is_primary' => true,
        ]);
        $keep = PlayerIdentity::factory()->create([
            'player_id' => $player->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'keep-sub',
            'email_id' => $email->id_player_email,
        ]);
        $drop = PlayerIdentity::factory()->create([
            'player_id' => $player->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'drop-sub',
            'email_id' => $email->id_player_email,
        ]);
        Sanctum::actingAs($player);

        $this->deleteJson("/api/v1/app/auth/identities/{$drop->id_identity}")
            ->assertOk();

        $this->assertDatabaseMissing('player_identities', ['id_identity' => $drop->id_identity]);
        $this->assertDatabaseHas('player_identities', ['id_identity' => $keep->id_identity]);
        $this->assertDatabaseHas('player_emails', [
            'id_player_email' => $email->id_player_email,
            'email' => 'multi@example.com',
        ]);
        $email->refresh();
        $this->assertContains('login', $email->purposes);
    }

    public function test_unlink_last_google_rejected(): void
    {
        $player = Player::factory()->playerOnly()->withGoogleIdentity('only-g')->create();
        $identity = $player->identities()->first();
        Sanctum::actingAs($player);

        $this->deleteJson("/api/v1/app/auth/identities/{$identity->id_identity}")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'last_provider_identity');
    }

    public function test_unlink_last_google_rejected_even_with_dev_identity(): void
    {
        $player = Player::factory()->playerOnly()->withGoogleIdentity('only-g2')->create();
        PlayerIdentity::factory()->create([
            'player_id' => $player->id_player,
            'provider' => PlayerIdentity::PROVIDER_DEV,
            'provider_subject' => 'dev:keep',
        ]);
        $google = $player->identities()->where('provider', PlayerIdentity::PROVIDER_GOOGLE)->first();
        Sanctum::actingAs($player);

        $this->deleteJson("/api/v1/app/auth/identities/{$google->id_identity}")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'last_provider_identity');
    }

    public function test_unlink_another_players_identity_forbidden(): void
    {
        $owner = Player::factory()->playerOnly()->withGoogleIdentity('owner-sub')->create();
        $identity = $owner->identities()->first();
        $intruder = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($intruder);

        $this->deleteJson("/api/v1/app/auth/identities/{$identity->id_identity}")
            ->assertStatus(403);
    }

    public function test_me_includes_identities(): void
    {
        $player = Player::factory()->playerOnly()->withGoogleIdentity('me-sub')->create();
        Sanctum::actingAs($player);

        $this->getJson('/api/v1/app/auth/me')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'identities' => [
                        ['id_identity', 'provider', 'email', 'display_name', 'avatar_url', 'linked_at', 'last_login_at'],
                    ],
                ],
            ]);
    }

    public function test_manager_player_index_does_not_force_identities(): void
    {
        $manager = Player::factory()->managerOnly()->create();
        Player::factory()->playerOnly()->withGoogleIdentity('mgr-sub')->create();
        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/manager/players');
        $response->assertOk();
        $first = $response->json('data.0') ?? $response->json('data.data.0');
        if (is_array($first)) {
            $this->assertArrayNotHasKey('identities', $first);
        }
    }

    public function test_unlink_drops_login_purpose_when_email_unreferenced(): void
    {
        $player = Player::factory()->playerOnly()->create();
        $emailA = PlayerEmail::factory()->create([
            'player_id' => $player->id_player,
            'email' => 'a@example.com',
            'purposes' => ['contact', 'login'],
            'is_primary' => true,
        ]);
        $emailB = PlayerEmail::factory()->create([
            'player_id' => $player->id_player,
            'email' => 'b@example.com',
            'purposes' => ['contact', 'login'],
            'is_primary' => false,
        ]);
        PlayerIdentity::factory()->create([
            'player_id' => $player->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'keep-a',
            'email_id' => $emailA->id_player_email,
        ]);
        $drop = PlayerIdentity::factory()->create([
            'player_id' => $player->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'drop-b',
            'email_id' => $emailB->id_player_email,
        ]);
        Sanctum::actingAs($player);

        $this->deleteJson("/api/v1/app/auth/identities/{$drop->id_identity}")->assertOk();

        $emailB->refresh();
        $this->assertNotContains('login', $emailB->purposes);
        $this->assertContains('contact', $emailB->purposes);
    }
}
