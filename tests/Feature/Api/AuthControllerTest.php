<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Player;
use Google\Client as GoogleClient;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private $player;
    private $googleUser;

    public function setUp(): void
    {
        parent::setUp();

        // Create test player
        $this->player = Player::factory()->create([
            'name' => 'Test User',
            'phone' => '1234567890',
            'google_id' => 'google123',
            'google_name' => 'Test User',
            'google_email' => 'test@example.com',
            'google_avatar' => 'https://example.com/avatar.jpg'
        ]);

        // // Mock Google Client
        // $this->mock(GoogleClient::class, function ($mock) {
        //     $mock->shouldReceive('verifyIdToken')
        //         ->andReturn([
        //             'sub' => 'google123',
        //             'name' => 'Test User',
        //             'email' => 'test@example.com',
        //             'picture' => 'https://example.com/avatar.jpg'
        //         ]);

        //     $mock->shouldReceive('verifyIdToken')
        //         ->with('invalid_token')
        //         ->andReturn(false);
        // });
    }

    public function test_google_callback_creates_new_player()
    {
        // Mock Google Client
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->withAnyArgs()  // Accept any token in test
                ->andReturn([
                    'sub' => 'google123',
                    'name' => 'Test User',
                    'email' => 'new@example.com',  // Different from existing player
                    'picture' => 'https://example.com/avatar.jpg'
                ]);
        });

        $response = $this->postJson('/api/auth/google', [
            'token' => 'any.test.token',  // Format doesn't matter now
            'name' => 'Test User',
            'phone' => '1234567890'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'player' => [
                    'id_player',
                    'name',
                    'phone',
                    'google_name',
                    'google_avatar'
                ]
            ]);

        $this->assertDatabaseHas('players', [
            'name' => 'Test User',
            'google_id' => 'google123',
            'google_email' => 'new@example.com'
        ]);
    }

    public function test_google_callback_returns_existing_player()
    {
        // Mock Google Client
        $this->mock(GoogleClient::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google123',
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'picture' => 'https://example.com/avatar.jpg'
                ]);
        });

        $response = $this->postJson('/api/auth/google', [
            'token' => 'valid_token',
            'name' => 'Test User',
            'phone' => '1234567890'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'player' => [
                    'id_player' => $this->player->id_player,
                    'name' => 'Test User',
                    'google_name' => 'Test User',
                    'google_avatar' => 'https://example.com/avatar.jpg'
                ]
            ]);
    }

    public function test_google_callback_requires_token()
    {
        $response = $this->postJson('/api/auth/google', [
            'name' => 'Test User',
            'phone' => '1234567890'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_google_callback_handles_invalid_token()
    {
        // Mock Google Client
        $this->mock(GoogleClient::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->with('invalid_token')
                ->andReturn(false);
        });

        $response = $this->postJson('/api/auth/google', [
            'token' => 'invalid_token'
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Authentication failed'
            ]);
    }

    public function test_me_returns_authenticated_player()
    {
        Sanctum::actingAs($this->player);
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'id_player' => $this->player->id_player,
                'name' => 'Test User',
                'phone' => '1234567890',
                'google_name' => 'Test User',
                'google_avatar' => 'https://example.com/avatar.jpg'
            ]);
    }

    public function test_me_requires_authentication()
    {
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(401);
    }

    public function test_logout_deletes_token()
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(200);

        // After logout, token should be deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->player->id_player
        ]);
    }

    public function test_logout_requires_authentication()
    {
        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(401);
    }
}
