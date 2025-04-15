<?php

namespace Tests\Feature\Api;

use App\Models\Division;
use Tests\TestCase;
use App\Models\Player;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use App\Models\PlayerEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EditionParticipationControllerTest extends TestCase
{
    use RefreshDatabase;

    private $player;
    private $edition;
    private $division;
    private $editionDivision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = Player::factory()->create();
        $this->edition = Edition::factory()->create([
            'date' => date('Y-m-d', strtotime('+30 days')),
            'entry_fee' => 20,
            'students_entry_fee' => 15
        ]);
        $this->division = Division::factory()->create();
    }

    public function test_cannot_join_past_edition()
    {
        $pastEdition = Edition::factory()->create([
            'date' => date('Y-m-d', strtotime('-1 day'))
        ]);

        $response = $this->actingAs($this->player)
            ->postJson("/api/editions/{$pastEdition->id_edition}/participate", [
                'division_id' => $this->division->id_division
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot join past editions']);
    }

    public function test_cannot_join_without_eligibility()
    {
        $response = $this->actingAs($this->player)
            ->postJson("/api/editions/{$this->edition->id_edition}/participate", [
                'division_id' => $this->division->id_division
            ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'You are not eligible yet. Please submit your eligibility request first.']);
    }

    public function test_cannot_join_full_division()
    {
        // Create approved eligibility
        PlayerEligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'approved'
        ]);

        // include the division in the edition
        EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 1
        ]);

        // dd('$editionDivision', $editionDivision); // TODO: Remove

        // Create a participation to fill up the division
        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => Player::factory()->create()->id_player,
            'division_id' => $this->division->id_division
        ]);

        $response = $this->actingAs($this->player)
            ->postJson("/api/editions/{$this->edition->id_edition}/participate", [
                'division_id' => $this->division->id_division
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'This division is already full']);
    }

    public function test_can_join_with_student_discount()
    {
        PlayerEligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'approved',
            'being_student' => true
        ]);

        // include the division in the edition
        EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $this->division->id_division,
        ]);

        // Create a participation to fill up the division
        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => Player::factory()->create()->id_player,
            'division_id' => $this->division->id_division
        ]);

        $response = $this->actingAs($this->player)
            ->postJson("/api/editions/{$this->edition->id_edition}/participate", [
                'division_id' => $this->division->id_division
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('payment', $this->edition->students_entry_fee);
    }
}
