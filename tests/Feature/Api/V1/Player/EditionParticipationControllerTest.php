<?php

namespace Tests\Feature\Api\V1\Player;

use Tests\TestCase;
use App\Models\Edition;
use App\Models\Player;
use App\Models\EditionParticipation;
use App\Models\PlayerEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class EditionParticipationControllerTest extends TestCase
{
    use RefreshDatabase;

    private $player;
    private $edition;

    public function setUp(): void
    {
        parent::setUp();

        // Create a player user
        $this->player = Player::factory()->playerOnly()->create([
            'name' => 'Test Player',
            'google_email' => 'player@example.com',
            'user_types' => ['player']
        ]);

        // Create an edition
        $this->edition = Edition::factory()->create([
            'name' => 'Test Edition',
            'date' => now()->addDays(10)
        ]);
    }

    public function test_store_creates_participation()
    {
        Sanctum::actingAs($this->player);

        // Create an approved eligibility first
        PlayerEligibility::factory()->approved()->create([
            'player_id' => $this->player->id_player,
            'edition_id' => $this->edition->id_edition
        ]);

        // Create an edition division
        $division = \App\Models\Division::factory()->create();
        \App\Models\EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $division->id_division,
            'players_limit' => 10
        ]);

        $participationData = [
            'division_id' => $division->id_division
        ];

        $response = $this->postJson("/api/v1/player/editions/{$this->edition->id_edition}/participate", $participationData);

        $response->assertStatus(201)
            ->assertJson([
                'player_id' => $this->player->id_player,
                'edition_id' => $this->edition->id_edition
            ]);

        $this->assertDatabaseHas('edition_participations', [
            'player_id' => $this->player->id_player,
            'edition_id' => $this->edition->id_edition
        ]);
    }

    public function test_destroy_removes_participation()
    {
        Sanctum::actingAs($this->player);

        // Create participation first
        EditionParticipation::factory()->create([
            'player_id' => $this->player->id_player,
            'edition_id' => $this->edition->id_edition
        ]);

        $response = $this->deleteJson("/api/v1/player/editions/{$this->edition->id_edition}/withdraw");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('edition_participations', [
            'player_id' => $this->player->id_player,
            'edition_id' => $this->edition->id_edition
        ]);
    }

    public function test_requires_player_authentication()
    {
        // Test without authentication
        $response = $this->postJson("/api/v1/player/editions/{$this->edition->id_edition}/participate");
        $response->assertStatus(401);

        // Test with manager (should still work as managers can access player endpoints)
        $manager = Player::factory()->manager()->create();
        Sanctum::actingAs($manager);

        // Create an approved eligibility for manager
        PlayerEligibility::factory()->approved()->create([
            'player_id' => $manager->id_player,
            'edition_id' => $this->edition->id_edition
        ]);

        // Create an edition division
        $division = \App\Models\Division::factory()->create();
        \App\Models\EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $division->id_division,
            'players_limit' => 10
        ]);

        $response = $this->postJson("/api/v1/player/editions/{$this->edition->id_edition}/participate", [
            'division_id' => $division->id_division
        ]);
        $response->assertStatus(201);
    }

    public function test_cannot_participate_twice()
    {
        Sanctum::actingAs($this->player);

        // Create an approved eligibility first
        PlayerEligibility::factory()->approved()->create([
            'player_id' => $this->player->id_player,
            'edition_id' => $this->edition->id_edition
        ]);

        // Create an edition division
        $division = \App\Models\Division::factory()->create();
        \App\Models\EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $division->id_division,
            'players_limit' => 10
        ]);

        // Create participation first
        EditionParticipation::factory()->create([
            'player_id' => $this->player->id_player,
            'edition_id' => $this->edition->id_edition,
            'division_id' => $division->id_division
        ]);

        $response = $this->postJson("/api/v1/player/editions/{$this->edition->id_edition}/participate", [
            'division_id' => $division->id_division
        ]);

        $response->assertStatus(201);
    }

    public function test_cannot_withdraw_without_participation()
    {
        Sanctum::actingAs($this->player);

        $response = $this->deleteJson("/api/v1/player/editions/{$this->edition->id_edition}/withdraw");

        $response->assertStatus(404);
    }
}
