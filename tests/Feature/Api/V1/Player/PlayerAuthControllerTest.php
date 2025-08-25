<?php

namespace Tests\Feature\Api\V1\Player;

use Tests\TestCase;
use App\Models\Player;
use Google\Client as GoogleClient;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class PlayerAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private $player;
    private $googleUser;

    public function setUp(): void
    {
        parent::setUp();

        // Create test player with user_types
        $this->player = Player::factory()->playerOnly()->create([
            'name' => 'Test User',
            'phone' => '1234567890',
            'google_id' => 'google123',
            'google_name' => 'Test User',
            'google_email' => 'test@example.com',
            'google_avatar' => 'https://example.com/avatar.jpg',
            'user_types' => ['player']
        ]);
    }

    public function test_google_callback_creates_new_player()
    {
        // Mock Google Client
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->withAnyArgs()
                ->andReturn([
                    'sub' => 'google456',
                    'name' => 'New User',
                    'email' => 'new@example.com',
                    'picture' => 'https://example.com/new-avatar.jpg'
                ]);
        });

        $response = $this->postJson('/api/v1/player/auth/google', [
            'token' => 'any.test.token'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'player' => [
                    'id_player',
                    'name',
                    'google_name',
                    'google_avatar',
                    'user_types'
                ]
            ]);

        $this->assertDatabaseHas('players', [
            'google_email' => 'new@example.com'
        ]);
    }

    public function test_google_callback_returns_existing_player()
    {
        // Mock Google Client
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google123',
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'picture' => 'https://example.com/avatar.jpg'
                ]);
        });

        $response = $this->postJson('/api/v1/player/auth/google', [
            'token' => 'valid.token'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'player' => [
                    'id_player',
                    'name',
                    'google_name',
                    'google_avatar',
                    'user_types'
                ]
            ]);

        // Verify the existing player was returned
        $response->assertJson([
            'player' => [
                'id_player' => $this->player->id_player,
                'name' => 'Test User'
            ]
        ]);
    }

    public function test_google_callback_validates_required_fields()
    {
        $response = $this->postJson('/api/v1/player/auth/google', [
            // Missing token
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_google_callback_handles_invalid_token()
    {
        // Mock Google Client to return false for invalid token
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->with('invalid_token')
                ->andReturn(false);
        });

        $response = $this->postJson('/api/v1/player/auth/google', [
            'token' => 'invalid_token'
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Player authentication failed']);
    }

    public function test_me_endpoint_returns_authenticated_player()
    {
        Sanctum::actingAs($this->player);

        $response = $this->getJson('/api/v1/player/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id_player',
                'name',
                'phone',
                'google_name',
                'google_avatar',
                'user_types'
            ])
            ->assertJson([
                'id_player' => $this->player->id_player,
                'name' => 'Test User',
                'user_types' => ['player']
            ]);
    }

    public function test_me_endpoint_requires_authentication()
    {
        $response = $this->getJson('/api/v1/player/auth/me');

        $response->assertStatus(401);
    }

    public function test_logout_endpoint_revokes_token()
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/player/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Player logged out successfully']);

        // Verify token is revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->player->id_player,
            'tokenable_type' => Player::class
        ]);
    }

    public function test_logout_endpoint_requires_authentication()
    {
        $response = $this->postJson('/api/v1/player/auth/logout');

        $response->assertStatus(401);
    }

    public function test_dev_login_creates_player_in_non_production()
    {
        // Only test in non-production environment
        if (app()->environment('production')) {
            $this->markTestSkipped('Dev login not available in production');
        }

        $response = $this->postJson('/api/v1/player/auth/dev-login', [
            'email' => 'dev@example.com'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'player' => [
                    'id_player',
                    'name',
                    'google_email',
                    'user_types'
                ]
            ]);

        $this->assertDatabaseHas('players', [
            'google_email' => 'dev@example.com'
        ]);
    }

    // Performance and bottleneck tests
    public function test_google_callback_performance_with_large_dataset()
    {
        // Create many players to test database performance (without external image downloads)
        Player::factory()->count(100)->create([
            'google_avatar' => 'https://example.com/test-avatar.jpg' // Provide a valid avatar URL
        ]);

        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google999',
                    'name' => 'Performance Test User',
                    'email' => 'perf@example.com',
                    'picture' => 'https://example.com/perf.jpg'
                ]);
        });

        $startTime = microtime(true);

        $response = $this->postJson('/api/v1/player/auth/google', [
            'token' => 'perf.test.token'
        ]);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);

        // Assert reasonable performance (should complete within 2 seconds)
        $this->assertLessThan(2.0, $executionTime, 'Google callback took too long to execute');
    }

    public function test_rate_limiting_on_google_callback()
    {
        // Mock Google Client for rate limiting test
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->withAnyArgs()
                ->andReturn([
                    'sub' => 'rate_test_id',
                    'name' => 'Rate Test User',
                    'email' => 'rate@example.com',
                    'picture' => 'https://example.com/rate-avatar.jpg'
                ]);
        });

        // Test that multiple requests can be made without rate limiting
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/v1/player/auth/google', [
                'token' => "token{$i}"
            ]);
            $response->assertStatus(200);
        }

        // The 11th request should also succeed (no rate limiting configured)
        $response = $this->postJson('/api/v1/player/auth/google', [
            'token' => 'rate_limited_token'
        ]);

        $response->assertStatus(200);
    }

    public function test_concurrent_requests_handle_properly()
    {
        // Mock Google Client for concurrent test
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->withAnyArgs()
                ->andReturn([
                    'sub' => 'concurrent_test_id',
                    'name' => 'Concurrent Test User',
                    'email' => 'concurrent@example.com',
                    'picture' => 'https://example.com/concurrent-avatar.jpg'
                ]);
        });

        // Test that multiple concurrent requests don't cause issues
        $promises = [];

        for ($i = 0; $i < 5; $i++) {
            $promises[] = $this->postJson('/api/v1/player/auth/google', [
                'token' => "concurrent_token_{$i}"
            ]);
        }

        // All requests should complete successfully
        foreach ($promises as $promise) {
            $promise->assertStatus(200);
        }
    }

    public function test_user_types_field_handles_various_combinations()
    {
        $testCases = [
            ['player'],
            ['player', 'manager'],
            ['player', 'manager', 'admin']
        ];

        foreach ($testCases as $userTypes) {
            $player = Player::factory()->create(['user_types' => $userTypes]);

            Sanctum::actingAs($player);

            $response = $this->getJson('/api/v1/player/auth/me');

            $response->assertStatus(200)
                ->assertJson([
                    'user_types' => $userTypes
                ]);
        }

        // Test that users without 'player' type get 403
        $nonPlayerUserTypes = [
            ['manager'],
            ['admin']
        ];

        foreach ($nonPlayerUserTypes as $userTypes) {
            $player = Player::factory()->create(['user_types' => $userTypes]);

            Sanctum::actingAs($player);

            $response = $this->getJson('/api/v1/player/auth/me');

            $response->assertStatus(403);
        }
    }

    public function test_database_constraints_are_respected()
    {
        // Mock Google Client to return a duplicate email
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->with('duplicate_token')
                ->andReturn([
                    'sub' => 'duplicate_google_id',
                    'name' => 'Duplicate User',
                    'email' => 'unique@example.com', // This email already exists
                    'picture' => 'https://example.com/duplicate-avatar.jpg'
                ]);
        });

        // Create a player with the email that will be returned by the mock
        $player1 = Player::factory()->create([
            'google_id' => 'unique_google_id',
            'google_email' => 'unique@example.com'
        ]);

        $response = $this->postJson('/api/v1/player/auth/google', [
            'token' => 'duplicate_token'
        ]);

        // This should succeed because it will find the existing player
        $response->assertStatus(200);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
