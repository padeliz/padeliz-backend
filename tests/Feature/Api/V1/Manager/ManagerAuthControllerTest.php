<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Player;
use App\Models\PlayerIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ManagerAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);

        PlayerIdentity::create([
            'player_id' => $this->manager->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google_manager_123',
            'display_name' => 'Test Manager',
            'avatar_url' => 'https://example.com/manager-avatar.jpg',
            'linked_at' => time(),
        ]);
    }

    public function test_google_callback_returns_404_for_new_user()
    {
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->withAnyArgs()
                ->andReturn([
                    'sub' => 'google_manager_456',
                    'name' => 'New Manager',
                    'email' => 'newmanager@example.com',
                    'picture' => 'https://example.com/new-manager.jpg',
                ]);
        });

        $response = $this->postJson('/api/v1/manager/auth/google', [
            'id_token' => 'any.test.token',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'User not found. Please register first.',
            ]);
    }

    public function test_google_callback_returns_existing_manager()
    {
        $this->mock(\Google\Client::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn([
                    'sub' => 'google_manager_123',
                    'name' => 'Test Manager',
                    'email' => 'manager@example.com',
                    'picture' => 'https://example.com/manager-avatar.jpg',
                ]);
        });

        $response = $this->postJson('/api/v1/manager/auth/google', [
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
                'message' => 'Manager authenticated successfully',
                'data' => [
                    'player' => [
                        'id_player' => $this->manager->id_player,
                        'name' => 'Test Manager',
                    ],
                    'user_type' => 'manager',
                ],
            ]);
    }

    public function test_google_callback_returns_403_for_player_without_manager_privileges()
    {
        $player = Player::factory()->playerOnly()->create([
            'first_name' => 'Player', 'middle_name' => null, 'last_name' => 'Only',
        ]);

        PlayerIdentity::create([
            'player_id' => $player->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google_player_123',
            'linked_at' => time(),
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

        $response = $this->postJson('/api/v1/manager/auth/google', [
            'id_token' => 'valid.token',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'User does not have manager privileges.',
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
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Manager profile retrieved successfully',
                'data' => [
                    'id_player' => $this->manager->id_player,
                    'name' => 'Test Manager',
                ],
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
                'message' => 'Manager logged out successfully',
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->manager->id_player,
            'tokenable_type' => Player::class,
        ]);
    }

    public function test_dev_login_creates_manager_in_non_production()
    {
        if (app()->environment('production')) {
            $this->markTestSkipped('Dev login not available in production');
        }

        $response = $this->postJson('/api/v1/manager/auth/dev-login', [
            'email' => 'devmanager@example.com',
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
            'email' => 'devmanager@example.com',
        ]);
        $this->assertDatabaseHas('player_identities', [
            'provider' => 'dev',
            'provider_subject' => 'dev:devmanager@example.com',
        ]);
    }

    public function test_manager_access_control()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->postJson('/api/v1/manager/auth/dev-login', [
            'email' => 'test@example.com',
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
    }

    public function test_manager_can_access_both_player_and_manager_endpoints()
    {
        Sanctum::actingAs($this->manager);

        $managerResponse = $this->getJson('/api/v1/manager/auth/me');
        $managerResponse->assertStatus(200);

        $playerResponse = $this->getJson('/api/v1/app/auth/me');
        $playerResponse->assertStatus(200);
    }

    public function test_user_types_field_handles_manager_combinations()
    {
        $testCases = [
            ['player', 'manager'],
            ['manager'],
            ['player', 'manager', 'admin'],
        ];

        foreach ($testCases as $userTypes) {
            $manager = Player::factory()->create(['user_types' => $userTypes]);

            Sanctum::actingAs($manager);

            $response = $this->getJson('/api/v1/manager/auth/me');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Manager profile retrieved successfully',
                ]);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
