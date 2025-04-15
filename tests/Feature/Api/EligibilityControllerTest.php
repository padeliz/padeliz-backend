<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Player;
use App\Models\Edition;
use App\Models\PlayerEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EligibilityControllerTest extends TestCase
{
    use RefreshDatabase;

    private $player;
    private $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = Player::factory()->create();
        $this->edition = Edition::factory()->create([
            'date' => date('Y-m-d', strtotime('+30 days'))
        ]);
    }

    public function test_cannot_create_eligibility_for_past_edition()
    {
        $pastEdition = Edition::factory()->create([
            'date' => date('Y-m-d', strtotime('-1 day'))
        ]);

        $response = $this->actingAs($this->player)
            ->postJson('/api/eligibility', [
                'edition_id' => $pastEdition->id_edition
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot create eligibility for past editions']);
    }

    public function test_cannot_create_multiple_pending_eligibilities()
    {
        PlayerEligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'edition_id' => $this->edition->id_edition,
            'review_status' => 'pending'
        ]);

        $response = $this->actingAs($this->player)
            ->postJson('/api/eligibility', [
                'edition_id' => $this->edition->id_edition
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'You already have a pending eligibility']);
    }

    public function test_can_get_approved_eligibility()
    {
        $eligibility = PlayerEligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'approved',
            'reviewed_at' => time()
        ]);

        $response = $this->actingAs($this->player)
            ->getJson('/api/eligibility/approved');

        $response->assertOk()
            ->assertJson(['id_eligibility' => $eligibility->id_eligibility]);
    }

    public function test_can_update_pending_eligibility()
    {
        PlayerEligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'pending'
        ]);

        $response = $this->actingAs($this->player)
            ->patchJson('/api/eligibility');

        $response->assertOk();
        $this->assertNotNull($response->json('resubmitted_at'));
    }
}
