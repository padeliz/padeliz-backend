<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EligibilityControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $player;

    protected $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = Player::factory()->playerOnly()->create();
        $this->edition = Edition::factory()->create([
            'date' => now()->addDays(30)->format('Y-m-d'),
        ]);
    }

    public function test_player_can_view_approved_eligibilities()
    {
        Sanctum::actingAs($this->player);

        $eligibility = PlayerEligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'approved',
        ]);

        $response = $this->getJson('/api/v1/app/eligibility/approved');

        $response->assertStatus(200)
            ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'player_id',
                'review_status',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Approved eligibility retrieved successfully',
            ]);
    }

    public function test_player_can_view_pending_eligibilities()
    {
        Sanctum::actingAs($this->player);

        $eligibility = PlayerEligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'pending',
        ]);

        $response = $this->getJson('/api/v1/app/eligibility/pending');

        $response->assertStatus(200)
            ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'player_id',
                'review_status',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Pending eligibility retrieved successfully',
            ]);
    }

    public function test_player_can_view_rejected_eligibilities()
    {
        Sanctum::actingAs($this->player);

        $eligibility = PlayerEligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'rejected',
        ]);

        $response = $this->getJson('/api/v1/app/eligibility/rejected');

        $response->assertStatus(200)
            ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'player_id',
                'review_status',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Rejected eligibility retrieved successfully',
            ]);
    }

    public function test_player_can_create_eligibility()
    {
        Sanctum::actingAs($this->player);

        $eligibilityData = [
            'edition_id' => $this->edition->id_edition,
        ];

        $response = $this->postJson('/api/v1/app/eligibility', $eligibilityData);

        $response->assertStatus(201)
            ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'player_id',
                'edition_id',
                'name',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Eligibility created successfully',
            ]);
    }

    public function test_player_can_update_eligibility()
    {
        Sanctum::actingAs($this->player);

        $eligibility = PlayerEligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'pending',
        ]);

        $response = $this->patchJson('/api/v1/app/eligibility', []);

        $response->assertStatus(200)
            ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'player_id',
                'edition_id',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Eligibility updated successfully',
            ]);
    }

    public function test_eligibility_requires_authentication()
    {
        $response = $this->getJson('/api/v1/app/eligibility/approved');

        $response->assertStatus(401);
    }

    public function test_player_cannot_update_eligibility_without_pending()
    {
        Sanctum::actingAs($this->player);

        $response = $this->patchJson('/api/v1/app/eligibility', []);

        $response->assertStatus(404);
    }

    public function test_eligibility_validation()
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/eligibility', []);

        $response->assertStatus(422);
    }
}
