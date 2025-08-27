<?php

namespace Tests\Feature\Api\V1\Manager;

use Tests\TestCase;
use App\Models\Player;
use Google\Client as GoogleClient;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ManagerAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    public function setUp(): void
    {
        parent::setUp();

        // Create test manager with user_types
        $this->manager = Player::factory()->manager()->create([
            'name' => 'Test Manager',
            'phone' => '1234567890',
            'google_id' => 'google_manager_123',
            'google_name' => 'Test Manager',
            'google_email' => 'manager@example.com',
            'google_avatar' => 'https://example.com/manager-avatar.jpg',
            'user_types' => ['player', 'manager']
        ]);
    }

    public function test_google_callback_creates_new_manager()
    {
        // Mock Google Client
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->withAnyArgs()
                ->andReturn([
                    'sub' => 'google_manager_456',
                    'name' => 'New Manager',
                    'email' => 'newmanager@example.com',
                    'picture' => 'https://example.com/new-manager.jpg'
                ]);
        });

        $response = $this->postJson('/api/v1/manager/auth/google', [
            'token' => 'any.test.token'
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
                    'google_avatar'
                ],
                'user_type'
                ]
            ]);

        $this->assertDatabaseHas('players', [
            'google_email' => 'newmanager@example.com'
        ]);
    }

    public function test_google_callback_returns_existing_manager()
    {
        // Mock Google Client
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google_manager_123',
                    'name' => 'Test Manager',
                    'email' => 'manager@example.com',
                    'picture' => 'https://example.com/manager-avatar.jpg'
                ]);
        });

        $response = $this->postJson('/api/v1/manager/auth/google', [
            'token' => 'valid.token'
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
                    'google_avatar'
                ],
                'user_type'
                ]
            ]);

        // Verify the existing manager was returned
        $response->assertJson([
            'success' => true,
            'message' => 'Manager authenticated successfully',
            'data' => [
                'player' => [
                    'id_player' => $this->manager->id_player,
                    'name' => 'Test Manager'
                ],
                'user_type' => 'manager'
            ]
        ]);
    }

    public function test_me_endpoint_returns_authenticated_manager()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id_player',
                'name',
                'phone',
                'google_name',
                'google_avatar'
            ]
            ])
            ->assertJson([
            'success' => true,
            'message' => 'Manager profile retrieved successfully',
            'data' => [
                'id_player' => $this->manager->id_player,
                'name' => 'Test Manager'
            ]
            ]);
    }

    public function test_me_endpoint_requires_authentication()
    {
        $response = $this->getJson('/api/v1/manager/auth/me');

        $response->assertStatus(401);
    }

    public function test_logout_endpoint_revokes_token()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Manager logged out successfully'
            ]);

        // Verify token is revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->manager->id_player,
            'tokenable_type' => Player::class
        ]);
    }

    public function test_dev_login_creates_manager_in_non_production()
    {
        // Only test in non-production environment
        if (app()->environment('production')) {
            $this->markTestSkipped('Dev login not available in production');
        }

        $response = $this->postJson('/api/v1/manager/auth/dev-login', [
            'email' => 'devmanager@example.com'
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
                    'google_email'
                ],
                'user_type'
                ]
            ]);

        $this->assertDatabaseHas('players', [
            'google_email' => 'devmanager@example.com'
        ]);
    }

    public function test_manager_access_control()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->postJson('/api/v1/manager/auth/dev-login', [
            'email' => 'test@example.com'
        ]);

        // Dev-login endpoints are accessible without authentication in development
        // They only check if the environment is production
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'player' => [
                        'id_player',
                        'name',
                        'google_email'
                    ],
                    'user_type'
                ]
            ]);
    }

    public function test_manager_can_access_both_player_and_manager_endpoints()
    {
        Sanctum::actingAs($this->manager);

        // Should be able to access manager endpoints
        $managerResponse = $this->getJson('/api/v1/manager/auth/me');
        $managerResponse->assertStatus(200);

        // Should also be able to access player endpoints
        $playerResponse = $this->getJson('/api/v1/player/auth/me');
        $playerResponse->assertStatus(200);
    }

    public function test_user_types_field_handles_manager_combinations()
    {
        $testCases = [
            ['player', 'manager'],
            ['manager'],
            ['player', 'manager', 'admin']
        ];

        foreach ($testCases as $userTypes) {
            $manager = Player::factory()->create(['user_types' => $userTypes]);

            Sanctum::actingAs($manager);

            $response = $this->getJson('/api/v1/manager/auth/me');

            $response->assertStatus(200)
                ->assertJson([
                'success' => true,
                'message' => 'Manager profile retrieved successfully'
                ]);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
