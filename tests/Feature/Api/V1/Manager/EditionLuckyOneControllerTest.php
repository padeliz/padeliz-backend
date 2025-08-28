<?php

namespace Tests\Feature\Api\V1\Manager;

use Tests\TestCase;
use App\Models\Player;
use App\Models\EditionLuckyOne;
use App\Models\EditionLottery;
use App\Models\EditionParticipation;
use App\Models\Edition;
use App\Models\Division;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class EditionLuckyOneControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;
    private $lottery;
    private $participation;

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

        $edition = Edition::factory()->create();
        $division = Division::factory()->create();
        $player = Player::factory()->create();

        $this->lottery = EditionLottery::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $division->id_division
        ]);

        $this->participation = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $player->id_player,
            'division_id' => $division->id_division
        ]);
    }

    public function test_manager_can_view_lucky_ones_for_lottery()
    {
        Sanctum::actingAs($this->manager);

        EditionLuckyOne::factory()->count(3)->create(['lottery_id' => $this->lottery->id_lottery]);

        $response = $this->getJson("/api/v1/manager/lotteries/{$this->lottery->id_lottery}/lucky-ones");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lucky ones retrieved successfully'
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_manager_can_view_specific_lucky_one()
    {
        Sanctum::actingAs($this->manager);

        $luckyOne = EditionLuckyOne::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'drawn_participation_id' => $this->participation->id_participation,
            'drawn_at' => time(),
            'available_at' => time()
        ]);

        $response = $this->getJson("/api/v1/manager/lucky-ones/{$luckyOne->id_lucky_one}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lucky one details retrieved successfully',
                'data' => [
                    'id_lucky_one' => $luckyOne->id_lucky_one,
                    'lottery_id' => $this->lottery->id_lottery,
                    'drawn_participation_id' => $this->participation->id_participation
                ]
            ]);
    }

    public function test_manager_can_create_lucky_one()
    {
        Sanctum::actingAs($this->manager);

        $luckyOneData = [
            'lottery_id' => $this->lottery->id_lottery,
            'drawn_participation_id' => $this->participation->id_participation,
            'drawn_at' => time(),
            'available_at' => time(),
            'announced_at' => time(),
            'accepted_at' => null,
            'rejected_at' => null
        ];

        $response = $this->postJson('/api/v1/manager/lucky-ones', $luckyOneData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Lucky one created successfully',
                'data' => [
                    'lottery_id' => $this->lottery->id_lottery,
                    'drawn_participation_id' => $this->participation->id_participation
                ]
            ]);

        $this->assertDatabaseHas('edition_lucky_ones', [
            'lottery_id' => $this->lottery->id_lottery,
            'drawn_participation_id' => $this->participation->id_participation
        ]);
    }

    public function test_manager_can_create_lucky_one_with_default_timestamps()
    {
        Sanctum::actingAs($this->manager);

        $luckyOneData = [
            'lottery_id' => $this->lottery->id_lottery,
            'drawn_participation_id' => $this->participation->id_participation
            // Not providing timestamps - should use defaults
        ];

        $response = $this->postJson('/api/v1/manager/lucky-ones', $luckyOneData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Lucky one created successfully'
            ]);

        $this->assertDatabaseHas('edition_lucky_ones', [
            'lottery_id' => $this->lottery->id_lottery,
            'drawn_participation_id' => $this->participation->id_participation
        ]);
    }

    public function test_manager_can_update_lucky_one()
    {
        Sanctum::actingAs($this->manager);

        $luckyOne = EditionLuckyOne::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'drawn_participation_id' => $this->participation->id_participation,
            'drawn_at' => time(),
            'available_at' => time()
        ]);

        $updateData = [
            'announced_at' => time(),
            'accepted_at' => time(),
            'rejected_at' => null
        ];

        $response = $this->putJson("/api/v1/manager/lucky-ones/{$luckyOne->id_lucky_one}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lucky one updated successfully',
                'data' => [
                    'id_lucky_one' => $luckyOne->id_lucky_one,
                    'announced_at' => $updateData['announced_at'],
                    'accepted_at' => $updateData['accepted_at'],
                    'rejected_at' => null
                ]
            ]);

        $this->assertDatabaseHas('edition_lucky_ones', [
            'id_lucky_one' => $luckyOne->id_lucky_one,
            'announced_at' => $updateData['announced_at'],
            'accepted_at' => $updateData['accepted_at']
        ]);
    }

    public function test_manager_can_delete_lucky_one()
    {
        Sanctum::actingAs($this->manager);

        $luckyOne = EditionLuckyOne::factory()->create();

        $response = $this->deleteJson("/api/v1/manager/lucky-ones/{$luckyOne->id_lucky_one}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lucky one deleted successfully'
            ]);

        $this->assertDatabaseMissing('edition_lucky_ones', [
            'id_lucky_one' => $luckyOne->id_lucky_one
        ]);
    }

    public function test_manager_cannot_create_duplicate_lucky_one()
    {
        Sanctum::actingAs($this->manager);

        // Create first lucky one
        EditionLuckyOne::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'drawn_participation_id' => $this->participation->id_participation
        ]);

        // Try to create duplicate
        $duplicateData = [
            'lottery_id' => $this->lottery->id_lottery,
            'drawn_participation_id' => $this->participation->id_participation
        ];

        $response = $this->postJson('/api/v1/manager/lucky-ones', $duplicateData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This participation is already a lucky one for this lottery'
            ]);
    }

    public function test_manager_cannot_update_to_duplicate_lucky_one()
    {
        Sanctum::actingAs($this->manager);

        $lottery2 = EditionLottery::factory()->create();
        $participation2 = EditionParticipation::factory()->create();

        // Create two different lucky ones
        $luckyOne1 = EditionLuckyOne::factory()->create([
            'lottery_id' => $this->lottery->id_lottery,
            'drawn_participation_id' => $this->participation->id_participation
        ]);

        $luckyOne2 = EditionLuckyOne::factory()->create([
            'lottery_id' => $lottery2->id_lottery,
            'drawn_participation_id' => $participation2->id_participation
        ]);

        // Try to update second to match first
        $response = $this->putJson("/api/v1/manager/lucky-ones/{$luckyOne2->id_lucky_one}", [
            'lottery_id' => $this->lottery->id_lottery,
            'drawn_participation_id' => $this->participation->id_participation
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This participation is already a lucky one for this lottery'
            ]);
    }

    public function test_lucky_one_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/lucky-ones', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lottery_id', 'drawn_participation_id']);
    }

    public function test_lucky_one_creation_validation_invalid_lottery()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/lucky-ones', [
            'lottery_id' => 99999, // Non-existent lottery
            'drawn_participation_id' => $this->participation->id_participation
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lottery_id']);
    }

    public function test_lucky_one_creation_validation_invalid_participation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/lucky-ones', [
            'lottery_id' => $this->lottery->id_lottery,
            'drawn_participation_id' => 99999 // Non-existent participation
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['drawn_participation_id']);
    }

    public function test_lucky_one_update_validation()
    {
        Sanctum::actingAs($this->manager);

        $luckyOne = EditionLuckyOne::factory()->create();

        $response = $this->putJson("/api/v1/manager/lucky-ones/{$luckyOne->id_lucky_one}", [
            'lottery_id' => 99999 // Non-existent lottery
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lottery_id']);
    }

    public function test_lucky_one_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/lucky-ones/1');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_lucky_one_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        // Create a lucky one first so the route model binding works
        $luckyOne = EditionLuckyOne::factory()->create();

        $response = $this->getJson("/api/v1/manager/lucky-ones/{$luckyOne->id_lucky_one}");

        $response->assertStatus(403);
    }

    public function test_lucky_one_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/lucky-ones/99999');

        $response->assertStatus(404);
    }

    public function test_lucky_one_with_optional_timestamps()
    {
        Sanctum::actingAs($this->manager);

        $luckyOneData = [
            'lottery_id' => $this->lottery->id_lottery,
            'drawn_participation_id' => $this->participation->id_participation,
            'drawn_at' => time(),
            'available_at' => time(),
            'announced_at' => null,
            'accepted_at' => null,
            'rejected_at' => null
        ];

        $response = $this->postJson('/api/v1/manager/lucky-ones', $luckyOneData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Lucky one created successfully'
            ]);

        $this->assertDatabaseHas('edition_lucky_ones', [
            'lottery_id' => $this->lottery->id_lottery,
            'drawn_participation_id' => $this->participation->id_participation,
            'announced_at' => null,
            'accepted_at' => null,
            'rejected_at' => null
        ]);
    }
}

