<?php

namespace Tests\Feature\Api\V1\Player;

use Tests\TestCase;
use App\Models\Player;
use App\Models\PlayerEligibility;
use App\Models\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class EligibilityControllerTest extends TestCase
{
    use RefreshDatabase;

    private $player;
    private $edition;

    public function setUp(): void
    {
        parent::setUp();

        $this->player = Player::factory()->playerOnly()->create([
            'name' => 'Test Player',
            'phone' => '1234567890',
            'google_id' => 'google123',
            'google_name' => 'Test Player',
            'google_email' => 'player@example.com',
            'google_avatar' => 'https://example.com/avatar.jpg',
            'user_types' => ['player']
        ]);

        $this->edition = Edition::factory()->create([
            'name' => 'Test Edition',
            'date' => now()->addDays(10)
        ]);
    }

    public function test_player_can_view_approved_eligibilities()
    {
        Sanctum::actingAs($this->player);

        // Create some approved eligibilities
        PlayerEligibility::factory()->approved()->create([
            'player_id' => $this->player->id_player
        ]);

        $response = $this->getJson('/api/v1/player/eligibility/approved');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id_eligibility',
                'player_id',
                'review_status'
            ]);
    }

    public function test_player_can_view_pending_eligibilities()
    {
        Sanctum::actingAs($this->player);

        // Create a pending eligibility
        PlayerEligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'pending'
        ]);

        $response = $this->getJson('/api/v1/player/eligibility/pending');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id_eligibility',
                'player_id',
                'review_status'
            ]);
    }

    public function test_player_can_view_rejected_eligibilities()
    {
        Sanctum::actingAs($this->player);

        // Create some rejected eligibilities
        PlayerEligibility::factory()->rejected()->create([
            'player_id' => $this->player->id_player
        ]);

        $response = $this->getJson('/api/v1/player/eligibility/rejected');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id_eligibility',
                'player_id',
                'review_status'
            ]);
    }

    public function test_player_can_create_eligibility()
    {
        Sanctum::actingAs($this->player);

        $eligibilityData = [
            'edition_id' => $this->edition->id_edition,
            'name' => 'Test Player',
            'phone' => '1234567890'
        ];

        $response = $this->postJson('/api/v1/player/eligibility', $eligibilityData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id_eligibility',
                'player_id',
                'edition_id',
                'name'
            ]);

        $this->assertDatabaseHas('player_eligibilities', [
            'player_id' => $this->player->id_player,
            'edition_id' => $this->edition->id_edition
        ]);
    }

    public function test_player_can_update_eligibility()
    {
        Sanctum::actingAs($this->player);

        $eligibility = PlayerEligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'edition_id' => $this->edition->id_edition,
            'review_status' => 'pending'
        ]);

        $response = $this->patchJson('/api/v1/player/eligibility', []);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id_eligibility',
                'player_id',
                'edition_id'
            ]);

        $this->assertDatabaseHas('player_eligibilities', [
            'id_eligibility' => $eligibility->id_eligibility
        ]);
    }

    public function test_eligibility_requires_authentication()
    {
        $response = $this->getJson('/api/v1/player/eligibility/approved');

        $response->assertStatus(401);
    }

    public function test_player_cannot_update_eligibility_without_pending()
    {
        Sanctum::actingAs($this->player);

        // Create an approved eligibility (not pending)
        PlayerEligibility::factory()->approved()->create([
            'player_id' => $this->player->id_player,
            'edition_id' => $this->edition->id_edition
        ]);

        $response = $this->patchJson('/api/v1/player/eligibility', []);

        $response->assertStatus(404);
    }

    public function test_eligibility_validation()
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/player/eligibility', [
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id']);
    }
}
