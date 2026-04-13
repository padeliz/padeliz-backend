<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\Player;
use App\Models\PlayerEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditionParticipationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $player;

    protected $edition;

    protected $division;

    protected $editionDivision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = Player::factory()->playerOnly()->create();
        $this->edition = Edition::factory()->create([
            'date' => now()->addDays(30)->format('Y-m-d'),
        ]);
        $this->division = Division::factory()->create();
        $this->editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 10,
        ]);

        // Create approved eligibility for the player
        PlayerEligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'approved',
        ]);
    }

    public function test_store_creates_participation()
    {
        Sanctum::actingAs($this->player);

        $participationData = [
            'division_id' => $this->division->id_division,
        ];

        $response = $this->postJson("/api/v1/app/editions/{$this->edition->id_edition}/participate", $participationData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_participation',
                    'edition_id',
                    'player_id',
                    'division_id',
                ],
            ])
            ->assertJson([
            'success' => true,
            'message' => 'Successfully registered for edition',
            'data' => [
                'player_id' => $this->player->id_player,
                'edition_id' => $this->edition->id_edition,
                ],
            ]);
    }

    public function test_destroy_removes_participation()
    {
        Sanctum::actingAs($this->player);

        // First create a participation
        $participation = $this->edition->participations()->create([
            'player_id' => $this->player->id_player,
            'division_id' => $this->division->id_division,
            'is_minor' => false,
            'is_student' => false,
            'discount' => 0,
            'payment' => 100,
        ]);

        $response = $this->deleteJson("/api/v1/app/editions/{$this->edition->id_edition}/withdraw");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Withdrawn successfully',
            ]);
    }

    public function test_requires_player_authentication()
    {
        $response = $this->postJson("/api/v1/app/editions/{$this->edition->id_edition}/participate", [
            'division_id' => $this->division->id_division,
        ]);

        $response->assertStatus(401);
    }

    public function test_cannot_participate_twice()
    {
        Sanctum::actingAs($this->player);

        // Create first participation
        $this->edition->participations()->create([
            'player_id' => $this->player->id_player,
            'division_id' => $this->division->id_division,
            'is_minor' => false,
            'is_student' => false,
            'discount' => 0,
            'payment' => 100,
        ]);

        // Try to create second participation
        $response = $this->postJson("/api/v1/app/editions/{$this->edition->id_edition}/participate", [
            'division_id' => $this->division->id_division,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_withdraw_without_participation()
    {
        Sanctum::actingAs($this->player);

        $response = $this->deleteJson("/api/v1/app/editions/{$this->edition->id_edition}/withdraw");

        $response->assertStatus(404);
    }
}
