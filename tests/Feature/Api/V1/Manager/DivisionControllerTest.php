<?php

namespace Tests\Feature\Api\V1\Manager;

use Tests\TestCase;
use App\Models\Player;
use App\Models\Division;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class DivisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    public function setUp(): void
    {
        parent::setUp();

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

    public function test_manager_can_view_divisions()
    {
        Sanctum::actingAs($this->manager);

        Division::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/divisions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_division',
                        'name',
                        'color',
                        'is_students_only'
                    ]
                ],
                'current_page',
                'per_page',
                'total'
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_manager_can_view_specific_division()
    {
        Sanctum::actingAs($this->manager);

        $division = Division::factory()->create([
            'name' => 'Test Division',
            'color' => '#ff0000'
        ]);

        $response = $this->getJson("/api/v1/manager/divisions/{$division->id_division}");

        $response->assertStatus(200)
            ->assertJson([
                'id_division' => $division->id_division,
                'name' => 'Test Division',
                'color' => '#ff0000'
            ]);
    }

    public function test_division_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/divisions');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_division_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/divisions');

        $response->assertStatus(403);
    }

    public function test_division_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/divisions/99999');

        $response->assertStatus(404);
    }

    public function test_division_pagination()
    {
        Sanctum::actingAs($this->manager);

        Division::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/manager/divisions?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_division',
                        'name',
                        'color',
                        'is_students_only'
                    ]
                ],
                'current_page',
                'per_page',
                'total'
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(1, $response->json('current_page'));
        $this->assertEquals(25, $response->json('total'));
    }
}
