<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Eligibility;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EligibilityControllerTest extends TestCase
{
    use RefreshDatabase;

    private $manager;

    private $player;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Manager',
            'user_types' => ['player', 'manager'],
        ]);

        $this->player = Player::factory()->create([
            'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Player',
        ]);
    }

    public function test_manager_can_view_eligibilities()
    {
        Sanctum::actingAs($this->manager);

        Eligibility::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/manager/eligibilities');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_eligibility',
                        'player_id',
                    'review_status',
                        'review_message',
                    ],
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_manager_can_view_specific_eligibility()
    {
        Sanctum::actingAs($this->manager);

        $eligibility = Eligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'approved',
            'review_message' => 'Player is eligible',
        ]);

        $response = $this->getJson("/api/v1/manager/eligibilities/{$eligibility->id_eligibility}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Eligibility details retrieved successfully',
                'data' => [
                    'id_eligibility' => $eligibility->id_eligibility,
                    'player_id' => $this->player->id_player,
                                    'review_status' => 'approved',
                    'review_message' => 'Player is eligible',
                ],
            ]);
    }

    public function test_manager_can_create_eligibility()
    {
        Sanctum::actingAs($this->manager);

        $eligibilityData = [
            'player_id' => $this->player->id_player,
            'review_status' => 'pending',
            'review_message' => 'Under review',
        ];

        $response = $this->postJson('/api/v1/manager/eligibilities', $eligibilityData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Eligibility created successfully',
                'data' => [
                    'player_id' => $this->player->id_player,
                'review_status' => 'pending',
                    'review_message' => 'Under review',
                ],
            ]);

        $this->assertDatabaseHas('eligibilities', [
            'player_id' => $this->player->id_player,
            'review_status' => 'pending',
            'review_message' => 'Under review',
        ]);
    }

    public function test_manager_can_update_eligibility()
    {
        Sanctum::actingAs($this->manager);

        $eligibility = Eligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'pending',
            'review_message' => 'Under review',
        ]);

        $updateData = [
            'review_status' => 'approved',
            'review_message' => 'Player approved',
        ];

        $response = $this->putJson("/api/v1/manager/eligibilities/{$eligibility->id_eligibility}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Eligibility updated successfully',
                'data' => [
                    'id_eligibility' => $eligibility->id_eligibility,
                'review_status' => 'approved',
                    'review_message' => 'Player approved',
                ],
            ]);

        $this->assertDatabaseHas('eligibilities', [
            'id_eligibility' => $eligibility->id_eligibility,
            'review_status' => 'approved',
            'review_message' => 'Player approved',
        ]);
    }

    public function test_manager_can_partially_update_eligibility()
    {
        Sanctum::actingAs($this->manager);

        $eligibility = Eligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'pending',
            'review_message' => 'Under review',
        ]);

        // Test partial update with only review_status
        $response = $this->patchJson("/api/v1/manager/eligibilities/{$eligibility->id_eligibility}", [
            'review_status' => 'approved',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Eligibility partially updated successfully',
                'data' => [
                    'review_status' => 'approved',
                    'review_message' => 'Under review', // Should remain unchanged
                ],
            ]);

        // Test partial update with only review_message
        $response = $this->patchJson("/api/v1/manager/eligibilities/{$eligibility->id_eligibility}", [
            'review_message' => 'Partially Updated Notes',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Eligibility partially updated successfully',
                'data' => [
                    'review_status' => 'approved', // Should remain from previous update
                    'review_message' => 'Partially Updated Notes',
                ],
            ]);
    }

    public function test_manager_cannot_update_to_pending_if_another_pending_exists(): void
    {
        Sanctum::actingAs($this->manager);

        Eligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'pending',
        ]);

        $otherEligibility = Eligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'approved',
        ]);

        $this->putJson("/api/v1/manager/eligibilities/{$otherEligibility->id_eligibility}", [
            'review_status' => 'pending',
        ])->assertStatus(422);
    }

    public function test_manager_cannot_patch_to_pending_if_another_pending_exists(): void
    {
        Sanctum::actingAs($this->manager);

        Eligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'pending',
        ]);

        $otherEligibility = Eligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'approved',
        ]);

        $this->patchJson("/api/v1/manager/eligibilities/{$otherEligibility->id_eligibility}", [
            'review_status' => 'pending',
        ])->assertStatus(422);
    }

    public function test_manager_cannot_update_eligibility_without_required_fields()
    {
        Sanctum::actingAs($this->manager);

        $eligibility = Eligibility::factory()->create([
            'player_id' => $this->player->id_player,
        ]);

        // Test PUT without required fields
        $response = $this->putJson("/api/v1/manager/eligibilities/{$eligibility->id_eligibility}", [
            'review_message' => 'Only Notes',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['review_status']);

        // Test PUT with empty required fields
        $response = $this->putJson("/api/v1/manager/eligibilities/{$eligibility->id_eligibility}", [
            'review_status' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['review_status']);
    }

    public function test_manager_can_delete_eligibility()
    {
        Sanctum::actingAs($this->manager);

        $eligibility = Eligibility::factory()->create();

        $response = $this->deleteJson("/api/v1/manager/eligibilities/{$eligibility->id_eligibility}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Eligibility deleted successfully',
            ]);

        $this->assertDatabaseMissing('eligibilities', [
            'id_eligibility' => $eligibility->id_eligibility,
        ]);
    }

    public function test_manager_can_bulk_delete_eligibilities()
    {
        Sanctum::actingAs($this->manager);

        $eligibilities = Eligibility::factory()->count(3)->create();

        $response = $this->deleteJson('/api/v1/manager/eligibilities/bulk', [
            'eligibility_ids' => $eligibilities->pluck('id_eligibility')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Eligibilities deleted successfully',
                'data' => [
                    'total_successful' => 3,
                    'total_failed' => 0,
                    'total_requested' => 3,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'successful' => [
                        '*' => [
                            'id',
                            'name',
                        ],
                    ],
                    'failed',
                    'total_requested',
                    'total_successful',
                    'total_failed',
                ],
            ]);

        // Verify all eligibilities are deleted
        foreach ($eligibilities as $eligibility) {
            $this->assertDatabaseMissing('eligibilities', ['id_eligibility' => $eligibility->id_eligibility]);
        }
    }

    public function test_manager_cannot_bulk_delete_eligibilities_with_invalid_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/eligibilities/bulk', [
            'eligibility_ids' => [99999, 99998],
        ]);

        $response->assertStatus(207)
            ->assertJson([
                'success' => false,
                'message' => 'All resources failed to delete',
                'errors' => [
                    'total_successful' => 0,
                    'total_failed' => 2,
                    'total_requested' => 2,
                ],
            ])
            ->assertJsonStructure([
                'errors' => [
                    'successful',
                    'failed' => [
                        '*' => [
                            'id',
                            'reason',
                        ],
                    ],
                    'total_requested',
                    'total_successful',
                    'total_failed',
                ],
            ]);
    }

    public function test_manager_cannot_bulk_delete_eligibilities_with_empty_array()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/eligibilities/bulk', [
            'eligibility_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_eligibilities_without_eligibility_ids()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/eligibilities/bulk', []);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_bulk_delete_eligibilities_with_invalid_eligibility_ids_format()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->deleteJson('/api/v1/manager/eligibilities/bulk', [
            'eligibility_ids' => ['invalid', 'format'],
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_create_duplicate_eligibility()
    {
        Sanctum::actingAs($this->manager);

        // New invariant: at most one pending eligibility per player.
        $this->postJson('/api/v1/manager/eligibilities', [
            'player_id' => $this->player->id_player,
            'review_status' => 'pending',
        ])->assertStatus(201);

        $this->postJson('/api/v1/manager/eligibilities', [
            'player_id' => $this->player->id_player,
            'review_status' => 'pending',
        ])->assertStatus(422);
    }

    public function test_eligibility_creation_validation()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/eligibilities', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_id']);
    }

    public function test_eligibility_creation_validation_invalid_player()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/eligibilities', [
            'player_id' => 99999, // Non-existent player
            'review_status' => 'approved',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_id']);
    }

    public function test_eligibility_creation_validation_invalid_status()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/eligibilities', [
            'player_id' => $this->player->id_player,
            'review_status' => 'invalid_status', // Invalid status
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['review_status']);
    }

    public function test_eligibility_update_validation()
    {
        Sanctum::actingAs($this->manager);

        $eligibility = Eligibility::factory()->create();

        $response = $this->putJson("/api/v1/manager/eligibilities/{$eligibility->id_eligibility}", [
            'review_status' => 'invalid_status', // Invalid status
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['review_status']);
    }

    public function test_eligibility_requires_manager_authentication()
    {
        $response = $this->getJson('/api/v1/manager/eligibilities');

        $response->assertStatus(401);
    }

    public function test_regular_player_cannot_access_eligibility_management()
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/manager/eligibilities');

        $response->assertStatus(403);
    }

    public function test_eligibility_not_found()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/eligibilities/99999');

        $response->assertStatus(404);
    }

    public function test_eligibility_pagination()
    {
        Sanctum::actingAs($this->manager);

        Eligibility::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/manager/eligibilities?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_eligibility',
                        'player_id',
                    'review_status',
                    ],
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }

    public function test_eligibility_with_optional_fields()
    {
        Sanctum::actingAs($this->manager);

        $eligibilityData = [
            'player_id' => $this->player->id_player,
            'review_status' => 'pending',
            // Not providing review_message (optional)
        ];

        $response = $this->postJson('/api/v1/manager/eligibilities', $eligibilityData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Eligibility created successfully',
            ]);

        $this->assertDatabaseHas('eligibilities', [
            'player_id' => $this->player->id_player,
            'review_status' => 'pending',
            'review_message' => null,
        ]);
    }

    public function test_eligibility_with_different_statuses()
    {
        Sanctum::actingAs($this->manager);

        $player2 = Player::factory()->create();
        $player3 = Player::factory()->create();
        $player4 = Player::factory()->create();

        // Test different statuses
        $statuses = ['pending', 'approved', 'rejected', 'discarded'];

        foreach ($statuses as $index => $status) {
            $player = [$this->player, $player2, $player3, $player4][$index];

            $eligibilityData = [
                'player_id' => $player->id_player,
                'review_status' => $status,
                'review_message' => "Status: {$status}",
            ];

            $response = $this->postJson('/api/v1/manager/eligibilities', $eligibilityData);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Eligibility created successfully',
                    'data' => [
                    'review_status' => $status,
                    ],
                ]);

            $this->assertDatabaseHas('eligibilities', [
                'player_id' => $player->id_player,
                'review_status' => $status,
            ]);
        }
    }
}
