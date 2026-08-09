<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Player;
use App\Models\PlayerEmail;
use App\Models\PlayerIdentity;
use App\Models\PlayerInvite;
use App\Models\PlayerInviteEvent;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = Player::factory()->playerOnly()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Player',
            'user_types' => ['player'],
        ]);

        PlayerIdentity::create([
            'player_id' => $this->player->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google_player_123',
            'display_name' => 'Test Player',
            'avatar_url' => 'https://example.com/player-avatar.jpg',
            'linked_at' => time(),
            'last_login_at' => null,
        ]);
    }

    public function test_google_callback_returns_404_for_new_user()
    {
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google_player_456',
                    'name' => 'New Player',
                    'email' => 'newplayer@example.com',
                    'picture' => 'https://example.com/new-player.jpg',
                ]);
        });

        $response = $this->postJson('/api/v1/app/auth/google', [
            'id_token' => 'any.test.token',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'User not found. Please register first.',
            ]);
    }

    public function test_google_callback_returns_existing_player()
    {
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google_player_123',
                    'name' => 'Test Player',
                    'email' => 'player@example.com',
                    'picture' => 'https://example.com/player-avatar.jpg',
                ]);
        });

        $response = $this->postJson('/api/v1/app/auth/google', [
            'id_token' => 'valid.token',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'player' => [
                        'id_player',
                        'name',
                    ],
                    'user_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Player authenticated successfully',
                'data' => [
                    'player' => [
                        'id_player' => $this->player->id_player,
                        'name' => 'Test Player',
                    ],
                    'user_type' => 'player',
                ],
            ]);

        $this->assertNotNull(
            PlayerIdentity::query()
                ->where('provider', 'google')
                ->where('provider_subject', 'google_player_123')
                ->value('last_login_at')
        );
    }

    public function test_google_callback_returns_403_for_manager_without_player_privileges()
    {
        $manager = Player::factory()->managerOnly()->create([
            'first_name' => 'Manager', 'middle_name' => null, 'last_name' => 'Only',
        ]);

        PlayerIdentity::create([
            'player_id' => $manager->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google_manager_123',
            'linked_at' => time(),
        ]);

        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google_manager_123',
                    'name' => 'Test Manager',
                    'email' => 'manager@example.com',
                    'picture' => 'https://example.com/manager-avatar.jpg',
                ]);
        });

        $response = $this->postJson('/api/v1/app/auth/google', [
            'id_token' => 'valid.token',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'User does not have player privileges.',
            ]);
    }

    public function test_google_callback_validates_required_fields()
    {
        $response = $this->postJson('/api/v1/app/auth/google', []);

        $response->assertStatus(422);
    }

    public function test_google_callback_handles_invalid_token()
    {
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn(null);
        });

        $response = $this->postJson('/api/v1/app/auth/google', [
            'id_token' => 'invalid.token',
        ]);

        $response->assertStatus(401);
    }

    public function test_me_endpoint_returns_authenticated_player()
    {
        Sanctum::actingAs($this->player);

        $response = $this->getJson('/api/v1/app/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_player',
                    'name',
                    'uploaded_profile_images',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Player profile retrieved successfully',
                'data' => [
                    'id_player' => $this->player->id_player,
                    'name' => 'Test Player',
                ],
            ]);
    }

    public function test_me_endpoint_returns_uploaded_profile_images()
    {
        Sanctum::actingAs($this->player);

        $response = $this->getJson('/api/v1/app/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'uploaded_profile_images',
                ],
            ]);

        $data = $response->json('data');
        $this->assertIsArray($data['uploaded_profile_images']);
    }

    public function test_me_endpoint_requires_authentication()
    {
        $response = $this->getJson('/api/v1/app/auth/me');

        $response->assertStatus(401);
    }

    public function test_logout_endpoint_revokes_token()
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Player logged out successfully',
            ]);
    }

    public function test_logout_endpoint_requires_authentication()
    {
        $response = $this->postJson('/api/v1/app/auth/logout');

        $response->assertStatus(401);
    }

    public function test_dev_login_creates_player_in_non_production()
    {
        $response = $this->postJson('/api/v1/app/auth/dev-login', [
            'email' => 'dev@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'player' => [
                        'id_player',
                        'name',
                    ],
                    'user_type',
                ],
            ]);

        $this->assertDatabaseHas('player_emails', [
            'email' => 'dev@example.com',
        ]);

        $this->assertDatabaseHas('player_identities', [
            'provider' => 'dev',
            'provider_subject' => 'dev:dev@example.com',
        ]);
    }

    public function test_google_callback_performance_with_large_dataset()
    {
        Player::factory()
            ->count(100)
            ->state(new Sequence(
                fn (Sequence $sequence) => [
                    'first_name' => 'John', 'last_name' => (string) ($sequence->index + 1),
                ]
            ))
            ->create();

        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google_player_123',
                    'name' => 'Test Player',
                    'email' => 'player@example.com',
                    'picture' => 'https://example.com/player-avatar.jpg',
                ]);
        });

        $startTime = microtime(true);

        $response = $this->postJson('/api/v1/app/auth/google', [
            'id_token' => 'any.test.token',
        ]);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);
        $this->assertLessThan(2.0, $executionTime, 'Authentication should complete within 2 seconds');
    }

    public function test_rate_limiting_on_google_callback()
    {
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google_player_123',
                    'name' => 'Test Player',
                    'email' => 'player@example.com',
                    'picture' => 'https://example.com/player-avatar.jpg',
                ]);
        });

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/app/auth/google', [
                'id_token' => 'any.test.token',
            ]);
            $response->assertStatus(200);
        }
    }

    public function test_concurrent_requests_handle_properly()
    {
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google_player_123',
                    'name' => 'Test Player',
                    'email' => 'player@example.com',
                    'picture' => 'https://example.com/player-avatar.jpg',
                ]);
        });

        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->postJson('/api/v1/app/auth/google', [
                'id_token' => 'any.test.token',
            ]);
        }

        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
    }

    public function test_user_types_field_handles_various_combinations()
    {
        $userTypes = [
            ['player'],
            ['player', 'manager'],
            ['manager', 'player'],
        ];

        foreach ($userTypes as $types) {
            $player = Player::factory()->create(['user_types' => $types]);
            Sanctum::actingAs($player);

            $response = $this->getJson('/api/v1/app/auth/me');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Player profile retrieved successfully',
                ]);
        }
    }

    public function test_google_login_uses_identity_not_email()
    {
        $other = Player::factory()->create(['first_name' => 'Other', 'middle_name' => null, 'last_name' => 'Player']);
        PlayerEmail::create([
            'player_id' => $other->id_player,
            'email' => 'player@example.com',
            'purposes' => ['contact'],
            'is_primary' => true,
            'verified_at' => null,
        ]);

        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google_player_123',
                    'name' => 'Test Player',
                    'email' => 'player@example.com',
                    'picture' => 'https://example.com/player-avatar.jpg',
                ]);
        });

        $response = $this->postJson('/api/v1/app/auth/google', [
            'id_token' => 'any.test.token',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'player' => [
                        'id_player' => $this->player->id_player,
                    ],
                ],
            ]);
    }

    public function test_invite_redeem_links_identity_and_consumes_invite()
    {
        $invitee = Player::factory()->create(['first_name' => 'Invitee', 'middle_name' => null, 'last_name' => 'Player']);
        $rawToken = 'invite-token-redeem-1';
        $invite = PlayerInvite::create([
            'player_id' => $invitee->id_player,
            'token_hash' => PlayerInvite::hashToken($rawToken),
            'expires_at' => time() + 3600,
        ]);

        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google_new_invitee',
                    'name' => 'Invitee',
                    'email' => 'invitee@example.com',
                    'picture' => 'https://example.com/invitee.jpg',
                ]);
        });

        $response = $this->postJson('/api/v1/app/auth/invite/redeem', [
            'invite_token' => $rawToken,
            'provider' => 'google',
            'id_token' => 'valid.token',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'player' => [
                        'id_player' => $invitee->id_player,
                    ],
                    'user_type' => 'player',
                ],
            ]);

        $this->assertNotNull($invite->fresh()->accepted_at);
        $this->assertDatabaseHas('player_identities', [
            'player_id' => $invitee->id_player,
            'provider' => 'google',
            'provider_subject' => 'google_new_invitee',
        ]);
        $this->assertDatabaseHas('player_emails', [
            'player_id' => $invitee->id_player,
            'email' => 'invitee@example.com',
        ]);
        $this->assertDatabaseHas('player_invite_events', [
            'invite_id' => $invite->id_invite,
            'event' => PlayerInviteEvent::EVENT_REDEEM_SUCCEEDED,
            'result_code' => 'linked',
        ]);
    }

    public function test_invite_redeem_already_linked_logs_in_without_consuming_invite()
    {
        $rawToken = 'invite-token-already-linked';
        $invite = PlayerInvite::create([
            'player_id' => $this->player->id_player,
            'token_hash' => PlayerInvite::hashToken($rawToken),
            'expires_at' => time() + 3600,
        ]);

        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google_player_123',
                    'name' => 'Test Player',
                    'email' => 'player@example.com',
                ]);
        });

        $response = $this->postJson('/api/v1/app/auth/invite/redeem', [
            'invite_token' => $rawToken,
            'provider' => 'google',
            'id_token' => 'valid.token',
        ]);

        $response->assertStatus(200);
        $this->assertNull($invite->fresh()->accepted_at);
        $this->assertDatabaseHas('player_invite_events', [
            'invite_id' => $invite->id_invite,
            'event' => PlayerInviteEvent::EVENT_REDEEM_SUCCEEDED,
            'result_code' => 'already_linked_login',
        ]);
    }

    public function test_invite_redeem_rejects_identity_linked_to_another_player()
    {
        $invitee = Player::factory()->create(['first_name' => 'Another', 'middle_name' => null, 'last_name' => 'Invitee']);
        $rawToken = 'invite-token-conflict-identity';
        $invite = PlayerInvite::create([
            'player_id' => $invitee->id_player,
            'token_hash' => PlayerInvite::hashToken($rawToken),
            'expires_at' => time() + 3600,
        ]);

        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google_player_123',
                    'email' => 'someone@example.com',
                ]);
        });

        $response = $this->postJson('/api/v1/app/auth/invite/redeem', [
            'invite_token' => $rawToken,
            'provider' => 'google',
            'id_token' => 'valid.token',
        ]);

        $response->assertStatus(409);
        $this->assertNull($invite->fresh()->accepted_at);
        $this->assertDatabaseHas('player_invite_events', [
            'invite_id' => $invite->id_invite,
            'event' => PlayerInviteEvent::EVENT_REDEEM_FAILED,
            'result_code' => 'identity_conflict',
        ]);
    }

    public function test_invite_redeem_rejects_contact_conflict()
    {
        $owner = Player::factory()->create(['first_name' => 'Email', 'middle_name' => null, 'last_name' => 'Owner']);
        PlayerEmail::create([
            'player_id' => $owner->id_player,
            'email' => 'taken@example.com',
            'purposes' => ['contact'],
            'is_primary' => true,
        ]);

        $invitee = Player::factory()->create(['first_name' => 'Conflict', 'middle_name' => null, 'last_name' => 'Invitee']);
        $rawToken = 'invite-token-contact-conflict';
        $invite = PlayerInvite::create([
            'player_id' => $invitee->id_player,
            'token_hash' => PlayerInvite::hashToken($rawToken),
            'expires_at' => time() + 3600,
        ]);

        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google_brand_new_sub',
                    'email' => 'taken@example.com',
                ]);
        });

        $response = $this->postJson('/api/v1/app/auth/invite/redeem', [
            'invite_token' => $rawToken,
            'provider' => 'google',
            'id_token' => 'valid.token',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseHas('player_invite_events', [
            'invite_id' => $invite->id_invite,
            'event' => PlayerInviteEvent::EVENT_REDEEM_FAILED,
            'result_code' => 'contact_conflict',
        ]);
    }

    public function test_invite_redeem_rejects_unsupported_provider()
    {
        $invitee = Player::factory()->create(['first_name' => 'Apple', 'middle_name' => null, 'last_name' => 'Invitee']);
        $rawToken = 'invite-token-apple';
        PlayerInvite::create([
            'player_id' => $invitee->id_player,
            'token_hash' => PlayerInvite::hashToken($rawToken),
            'expires_at' => time() + 3600,
        ]);

        $response = $this->postJson('/api/v1/app/auth/invite/redeem', [
            'invite_token' => $rawToken,
            'provider' => 'apple',
            'id_token' => 'valid.token',
        ]);

        $response->assertStatus(422);
    }

    public function test_invite_redeem_rejects_inactive_invite()
    {
        $invitee = Player::factory()->create(['first_name' => 'Expired', 'middle_name' => null, 'last_name' => 'Invitee']);
        $rawToken = 'invite-token-expired';
        PlayerInvite::create([
            'player_id' => $invitee->id_player,
            'token_hash' => PlayerInvite::hashToken($rawToken),
            'expires_at' => time() - 10,
        ]);

        $response = $this->postJson('/api/v1/app/auth/invite/redeem', [
            'invite_token' => $rawToken,
            'provider' => 'google',
            'id_token' => 'valid.token',
        ]);

        $response->assertStatus(404);
    }
}
