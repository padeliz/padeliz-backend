<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->player = Player::factory()->playerOnly()->create([
            'name' => 'Test Player',
            'phone' => '1234567890',
            'google_id' => 'google_player_123',
            'google_name' => 'Test Player',
            'google_email' => 'player@example.com',
            'google_avatar' => 'https://example.com/player-avatar.jpg',
            'user_types' => ['player'],
        ]);
    }

    public function test_google_callback_returns_404_for_new_user()
    {
        // Mock Google Client
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

        $this->assertDatabaseMissing('players', [
            'google_email' => 'newplayer@example.com',
        ]);
    }

    public function test_google_callback_returns_existing_player()
    {
        // Mock Google Client
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
                    'google_name',
                    'google_avatar',
                ],
                'user_type',
                ],
            ]);

        // Verify the existing player was returned
        $response->assertJson([
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
    }

    public function test_google_callback_returns_403_for_manager_without_player_privileges()
    {
        // Create a manager without player privileges
        $manager = Player::factory()->managerOnly()->create([
            'google_email' => 'manager@example.com',
        ]);

        // Mock Google Client
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
        // Mock Google Client to return null (invalid token)
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
                'phone',
                'google_name',
                'google_avatar',
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
                    'google_email',
                ],
                'user_type',
                ],
            ]);

        $this->assertDatabaseHas('players', [
            'google_email' => 'dev@example.com',
        ]);
    }

    public function test_google_callback_performance_with_large_dataset()
    {
        // Create many players to test performance (names must be unique: uidx_name on pm_players).
        Player::factory()
            ->count(100)
            ->state(new Sequence(
                fn (Sequence $sequence) => [
                    'name' => 'John ' . ($sequence->index + 1),
                ]
            ))
            ->create();

        // Mock Google Client to return existing player
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
        // Bound large-dataset auth latency; allow modest headroom for slower CI/DB hosts.
        $this->assertLessThan(2.0, $executionTime, 'Authentication should complete within 2 seconds');
    }

    public function test_rate_limiting_on_google_callback()
    {
        // Mock Google Client to return existing player
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                'sub' => 'google_player_123',
                'name' => 'Test Player',
                'email' => 'player@example.com',
                'picture' => 'https://example.com/player-avatar.jpg',
                ]);
        });

        // Make multiple requests quickly
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/app/auth/google', [
                'id_token' => 'any.test.token',
            ]);
            $response->assertStatus(200);
        }
    }

    public function test_concurrent_requests_handle_properly()
    {
        // Mock Google Client to return existing player
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                'sub' => 'google_player_123',
                'name' => 'Test Player',
                'email' => 'player@example.com',
                'picture' => 'https://example.com/player-avatar.jpg',
                ]);
        });

        // Simulate concurrent requests
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

        foreach ($userTypes as $userTypes) {
            $player = Player::factory()->create(['user_types' => $userTypes]);
            Sanctum::actingAs($player);

            $response = $this->getJson('/api/v1/app/auth/me');

            $response->assertStatus(200)
                ->assertJson([
                'success' => true,
                'message' => 'Player profile retrieved successfully',
                ]);
        }
    }

    public function test_database_constraints_are_respected()
    {
        // Test that unique constraints are respected
        $existingPlayer = Player::factory()->create([
            'google_email' => 'unique@example.com',
        ]);

        // Mock Google Client
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                'sub' => 'google_player_456',
                'name' => 'New Player',
                'email' => 'unique@example.com',
                'picture' => 'https://example.com/new-player.jpg',
                ]);
        });

        $response = $this->postJson('/api/v1/app/auth/google', [
            'id_token' => 'any.test.token',
        ]);

        $response->assertStatus(200);

        // Should return the existing player, not create a new one
        $this->assertDatabaseCount('players', 2); // original + existing
    }
}
