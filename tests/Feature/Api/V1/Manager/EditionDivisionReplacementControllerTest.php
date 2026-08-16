<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionDivisionReplacement;
use App\Models\EditionParticipation;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\MatchSide;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditionDivisionReplacementControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private Edition $edition;

    private EditionDivision $editionDivision;

    /** @var list<EditionParticipation> */
    private array $participations = [];

    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $this->edition = Edition::factory()->create();
        $division = Division::factory()->create();

        $this->editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $division->id_division,
            'allow_replacements' => true,
        ]);

        $court = Court::factory()->create();
        $edCourt = EditionDivisionCourt::factory()->create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'court_id' => $court->id_court,
            'display_order' => 0,
        ]);

        for ($i = 0; $i < 4; $i++) {
            $this->participations[] = EditionParticipation::factory()->create([
                'edition_id' => $this->edition->id_edition,
                'edition_division_id' => $this->editionDivision->id_edition_division,
                'player_id' => Player::factory()->create()->id_player,
                'sort_order' => $i,
                'is_collecting_points' => true,
            ]);
        }

        $this->match = GameMatch::create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'edition_division_court_id' => $edCourt->id_edition_division_court,
            'match_kind' => 'REGULAR',
            'sequence_no' => 1,
            'scheduled_start' => '14:00:00',
            'scheduled_end' => '14:30:00',
            'points_target' => 21,
        ]);

        $left = MatchSide::create(['match_id' => $this->match->id_match, 'side' => 'LEFT', 'score' => null]);
        $right = MatchSide::create(['match_id' => $this->match->id_match, 'side' => 'RIGHT', 'score' => null]);

        MatchPlayer::create([
            'match_side_id' => $left->id_match_side,
            'slot' => 0,
            'participation_id' => $this->participations[0]->id_participation,
            'player_id' => $this->participations[0]->player_id,
            'got_replaced' => false,
        ]);
        MatchPlayer::create([
            'match_side_id' => $left->id_match_side,
            'slot' => 1,
            'participation_id' => $this->participations[1]->id_participation,
            'player_id' => $this->participations[1]->player_id,
            'got_replaced' => false,
        ]);
        MatchPlayer::create([
            'match_side_id' => $right->id_match_side,
            'slot' => 0,
            'participation_id' => $this->participations[2]->id_participation,
            'player_id' => $this->participations[2]->player_id,
            'got_replaced' => false,
        ]);
        MatchPlayer::create([
            'match_side_id' => $right->id_match_side,
            'slot' => 1,
            'participation_id' => $this->participations[3]->id_participation,
            'player_id' => $this->participations[3]->player_id,
            'got_replaced' => false,
        ]);
    }

    public function test_create_list_and_delete_replacement(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $originalId = $this->participations[0]->id_participation;
        $substituteId = $this->participations[3]->id_participation;

        $create = $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $originalId,
            'substitute_participation_id' => $substituteId,
            'match_id' => $this->match->id_match,
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.original_participation_id', $originalId)
            ->assertJsonPath('data.substitute_participation_id', $substituteId)
            ->assertJsonPath('data.match_id', $this->match->id_match);

        $replacementId = (int) $create->json('data.id_replacement');
        $this->assertDatabaseHas('edition_division_replacements', [
            'id_replacement' => $replacementId,
            'edition_division_id' => $edId,
        ]);

        $this->assertTrue((bool) MatchPlayer::query()
            ->where('participation_id', $originalId)
            ->value('got_replaced'));

        $this->getJson("/api/v1/manager/edition-divisions/{$edId}/replacements")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $this->deleteJson("/api/v1/manager/edition-division-replacements/{$replacementId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('edition_division_replacements', [
            'id_replacement' => $replacementId,
        ]);
        $this->assertFalse((bool) MatchPlayer::query()
            ->where('participation_id', $originalId)
            ->value('got_replaced'));
    }

    public function test_finalize_blocks_replacements(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;

        $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/finalize")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($this->edition->fresh()->finalized_at);

        $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/finalize")
            ->assertStatus(409);

        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[3]->id_participation,
            'match_id' => $this->match->id_match,
        ])->assertStatus(409);

        $this->assertSame(0, EditionDivisionReplacement::query()->count());
    }

    public function test_replacement_blocked_when_not_allowed(): void
    {
        Sanctum::actingAs($this->manager);

        $this->editionDivision->update(['allow_replacements' => false]);
        $edId = $this->editionDivision->id_edition_division;

        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_player_id' => Player::factory()->create()->id_player,
            'match_id' => $this->match->id_match,
        ])->assertStatus(409);
    }

    public function test_delete_replacement_blocked_after_finalize(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;

        $create = $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[3]->id_participation,
        ]);
        $create->assertStatus(201);
        $replacementId = (int) $create->json('data.id_replacement');

        $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/finalize")
            ->assertOk();

        $this->deleteJson("/api/v1/manager/edition-division-replacements/{$replacementId}")
            ->assertStatus(409);

        $this->assertDatabaseHas('edition_division_replacements', [
            'id_replacement' => $replacementId,
        ]);
    }

    public function test_index_for_edition_lists_all_division_replacements(): void
    {
        Sanctum::actingAs($this->manager);

        $otherDivision = Division::factory()->create();
        $otherEd = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $otherDivision->id_division,
            'allow_replacements' => true,
            'division_title' => 'Other ED',
        ]);

        $edId = $this->editionDivision->id_edition_division;
        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[1]->id_participation,
        ])->assertStatus(201);

        $otherPartA = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $otherEd->id_edition_division,
            'player_id' => Player::factory()->create()->id_player,
            'sort_order' => 0,
        ]);
        $otherPartB = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $otherEd->id_edition_division,
            'player_id' => Player::factory()->create()->id_player,
            'sort_order' => 1,
        ]);
        $this->postJson("/api/v1/manager/edition-divisions/{$otherEd->id_edition_division}/replacements", [
            'original_participation_id' => $otherPartA->id_participation,
            'substitute_participation_id' => $otherPartB->id_participation,
        ])->assertStatus(201);

        $response = $this->getJson(
            "/api/v1/manager/editions/{$this->edition->id_edition}/replacements"
        );
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertNotNull($data[0]['edition_division']['id_edition_division'] ?? null);
        $this->assertNotNull($data[0]['original_participation']['player']['id_player'] ?? null);
        $this->assertNotNull($data[0]['substitute_participation']['player']['id_player'] ?? null);
    }
}
