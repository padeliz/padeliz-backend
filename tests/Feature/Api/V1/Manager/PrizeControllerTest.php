<?php

namespace Tests\Feature\Api\V1\Manager;

use Tests\TestCase;
use App\Models\Player;
use App\Models\Prize;
use App\Models\PrizeFromClub;
use App\Models\EditionLottery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class PrizeControllerTest extends TestCase
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

    public function test_manager_can_view_prizes()
    {
        Sanctum::actingAs($this->manager);

        Prize::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/prizes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_prize',
                        'name',
                        'image',
                        'box_1_text',
                        'box_1_bg_color',
                        'box_1_text_color',
                        'box_2_text',
                        'box_2_bg_color',
                        'box_2_text_color'
                    ]
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next'
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total'
                ]
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_manager_can_view_specific_prize()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create([
            'name' => 'Test Prize',
            'box_1_text' => 'Test Box 1'
        ]);

        $response = $this->getJson("/api/v1/manager/prizes/{$prize->id_prize}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize details retrieved successfully',
                'data' => [
                    'id_prize' => $prize->id_prize,
                    'name' => 'Test Prize',
                    'box_1_text' => 'Test Box 1'
                ]
            ]);
    }

    public function test_manager_can_create_prize()
    {
        Sanctum::actingAs($this->manager);

        $prizeData = [
            'name' => 'New Prize',
            'image' => 'prize-image.jpg',
            'box_1_text' => 'Box 1 Text',
            'box_1_bg_color' => '#FF0000',
            'box_1_text_color' => '#FFFFFF',
            'box_2_text' => 'Box 2 Text',
            'box_2_bg_color' => '#00FF00',
            'box_2_text_color' => '#000000'
        ];

        $response = $this->postJson('/api/v1/manager/prizes', $prizeData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Prize created successfully',
                'data' => [
                    'name' => 'New Prize',
                    'image' => 'prize-image.jpg',
                    'box_1_text' => 'Box 1 Text',
                    'box_1_bg_color' => '#FF0000',
                    'box_1_text_color' => '#FFFFFF',
                    'box_2_text' => 'Box 2 Text',
                    'box_2_bg_color' => '#00FF00',
                    'box_2_text_color' => '#000000'
                ]
            ]);

        $this->assertDatabaseHas('prizes', [
            'name' => 'New Prize',
            'image' => 'prize-image.jpg'
        ]);
    }

    public function test_manager_can_update_prize()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create([
            'name' => 'Old Name',
            'box_1_text' => 'Old Box 1'
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'box_1_text' => 'Updated Box 1',
            'box_1_bg_color' => '#0000FF'
        ];

        $response = $this->putJson("/api/v1/manager/prizes/{$prize->id_prize}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize updated successfully',
                'data' => [
                    'id_prize' => $prize->id_prize,
                    'name' => 'Updated Name',
                    'box_1_text' => 'Updated Box 1',
                    'box_1_bg_color' => '#0000FF'
                ]
            ]);

        $this->assertDatabaseHas('prizes', [
            'id_prize' => $prize->id_prize,
            'name' => 'Updated Name',
            'box_1_text' => 'Updated Box 1'
        ]);
    }

    public function test_manager_can_delete_prize()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create();

        $response = $this->deleteJson("/api/v1/manager/prizes/{$prize->id_prize}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prize deleted successfully'
            ]);

        $this->assertDatabaseMissing('prizes', [
            'id_prize' => $prize->id_prize
        ]);
    }

    public function test_manager_cannot_delete_prize_with_club_prizes()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create();
        PrizeFromClub::factory()->create(['prize_id' => $prize->id_prize]);

        $response = $this->deleteJson("/api/v1/manager/prizes/{$prize->id_prize}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete prize because it has related club prizes or lotteries'
            ]);

        $this->assertDatabaseHas('prizes', [
            'id_prize' => $prize->id_prize
        ]);
    }

    public function test_manager_cannot_delete_prize_with_lotteries()
    {
        Sanctum::actingAs($this->manager);

        $prize = Prize::factory()->create();
        EditionLottery::factory()->create(['prize_id' => $prize->id_prize]);

        $response = $this->deleteJson("/api/v1/manager/prizes/{$prize->id_prize}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete prize because it has related club prizes or lotteries'
            ]);

        $this->assertDatabaseHas('prizes', [
            'id_prize' => $prize->id_prize
        ]);
    }

    public function test_prize_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/prizes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_prize_creation_validation_invalid_colors()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/prizes', [
            'name' => 'Test Prize',
            'box_1_bg_color' => 'invalid-color',
            'box_1_text_color' => 'invalid-color'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['box_1_bg_color', 'box_1_text_color']);
    }

    public function test_prize_update_validation_duplicate_name()
    {
        Sanctum::actingAs($this->manager);

        $prize1 = Prize::factory()->create(['name' => 'Existing Prize']);
        $prize2 = Prize::factory()->create(['name' => 'Another Prize']);

        $response = $this->putJson("/api/v1/manager/prizes/{$prize2->id_prize}", [
            'name' => 'Existing Prize'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_prize_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/prizes');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_prize_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/prizes');

        $response->assertStatus(403);
    }

    public function test_prize_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/prizes/99999');

        $response->assertStatus(404);
    }

    public function test_prize_pagination()
    {
        Sanctum::actingAs($this->manager);

        Prize::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/manager/prizes?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_prize',
                        'name',
                        'image'
                    ]
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next'
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total'
                ]
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }
}

