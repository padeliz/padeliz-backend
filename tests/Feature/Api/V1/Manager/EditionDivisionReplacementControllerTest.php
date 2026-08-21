<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionDivisionReplacement;
use App\Models\EditionDivisionSchedule;
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

    private EditionDivisionSchedule $schedule;

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
        ]);

        $this->schedule = EditionDivisionSchedule::factory()->create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
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
        ]);
        MatchPlayer::create([
            'match_side_id' => $left->id_match_side,
            'slot' => 1,
            'participation_id' => $this->participations[1]->id_participation,
            'player_id' => $this->participations[1]->player_id,
        ]);
        MatchPlayer::create([
            'match_side_id' => $right->id_match_side,
            'slot' => 0,
            'participation_id' => $this->participations[2]->id_participation,
            'player_id' => $this->participations[2]->player_id,
        ]);
        MatchPlayer::create([
            'match_side_id' => $right->id_match_side,
            'slot' => 1,
            'participation_id' => $this->participations[3]->id_participation,
            'player_id' => $this->participations[3]->player_id,
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

        $slot = MatchPlayer::query()->where('participation_id', $originalId)->firstOrFail();
        $this->assertSame($originalId, (int) $slot->participation_id);
        $this->assertSame((int) $this->participations[0]->player_id, (int) $slot->player_id);
        $this->assertArrayNotHasKey('got_replaced', $slot->getAttributes());

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
        $slotAfter = MatchPlayer::query()->where('participation_id', $originalId)->firstOrFail();
        $this->assertSame($originalId, (int) $slotAfter->participation_id);
        $this->assertSame((int) $this->participations[0]->player_id, (int) $slotAfter->player_id);
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

        $this->schedule->update(['allow_replacements' => false]);
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
            'division_title' => 'Other ED',
        ]);
        EditionDivisionSchedule::factory()->create([
            'edition_division_id' => $otherEd->id_edition_division,
            'allow_replacements' => true,
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

    public function test_create_with_neither_substitute(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $create = $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'match_id' => $this->match->id_match,
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.substitute_participation_id', null)
            ->assertJsonPath('data.substitute_player_id', null);
    }

    public function test_create_with_only_guest_substitute(): void
    {
        Sanctum::actingAs($this->manager);

        $guestId = Player::factory()->create()->id_player;
        $edId = $this->editionDivision->id_edition_division;
        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_player_id' => $guestId,
        ])->assertStatus(201)
            ->assertJsonPath('data.substitute_player_id', $guestId)
            ->assertJsonPath('data.substitute_participation_id', null);
    }

    public function test_create_with_both_substitutes_is_422(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[1]->id_participation,
            'substitute_player_id' => Player::factory()->create()->id_player,
        ])->assertStatus(422);
    }

    public function test_create_unique_conflict_same_match(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $payload = [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[1]->id_participation,
            'match_id' => $this->match->id_match,
        ];
        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", $payload)
            ->assertStatus(201);
        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", $payload)
            ->assertStatus(422);
    }

    public function test_create_unique_conflict_both_match_id_null(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $payload = [
            'original_participation_id' => $this->participations[0]->id_participation,
        ];
        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", $payload)
            ->assertStatus(201);
        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", $payload)
            ->assertStatus(422);
    }

    public function test_patch_omit_keeps_unspecified_fields(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $create = $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[1]->id_participation,
            'match_id' => $this->match->id_match,
        ]);
        $create->assertStatus(201);
        $id = (int) $create->json('data.id_replacement');

        $this->patchJson("/api/v1/manager/edition-division-replacements/{$id}", [
            'original_participation_id' => $this->participations[2]->id_participation,
        ])->assertOk()
            ->assertJsonPath('data.original_participation_id', $this->participations[2]->id_participation)
            ->assertJsonPath('data.substitute_participation_id', $this->participations[1]->id_participation)
            ->assertJsonPath('data.match_id', $this->match->id_match);
    }

    public function test_patch_one_substitute_id_nulls_the_other_type(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $guestId = Player::factory()->create()->id_player;
        $create = $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[1]->id_participation,
        ]);
        $create->assertStatus(201);
        $id = (int) $create->json('data.id_replacement');

        $this->patchJson("/api/v1/manager/edition-division-replacements/{$id}", [
            'substitute_player_id' => $guestId,
        ])->assertOk()
            ->assertJsonPath('data.substitute_player_id', $guestId)
            ->assertJsonPath('data.substitute_participation_id', null);
    }

    public function test_patch_both_substitute_ids_null_is_unknown(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $create = $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'substitute_participation_id' => $this->participations[1]->id_participation,
        ]);
        $id = (int) $create->json('data.id_replacement');

        $this->patchJson("/api/v1/manager/edition-division-replacements/{$id}", [
            'substitute_participation_id' => null,
            'substitute_player_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.substitute_participation_id', null)
            ->assertJsonPath('data.substitute_player_id', null);
    }

    public function test_patch_match_id_null_is_division_wide(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $create = $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'match_id' => $this->match->id_match,
        ]);
        $id = (int) $create->json('data.id_replacement');

        $this->patchJson("/api/v1/manager/edition-division-replacements/{$id}", [
            'match_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.match_id', null);
    }

    public function test_patch_unique_conflict_is_422(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
            'match_id' => $this->match->id_match,
        ])->assertStatus(201);

        $other = $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[1]->id_participation,
            'match_id' => $this->match->id_match,
        ]);
        $other->assertStatus(201);
        $id = (int) $other->json('data.id_replacement');

        $this->patchJson("/api/v1/manager/edition-division-replacements/{$id}", [
            'original_participation_id' => $this->participations[0]->id_participation,
        ])->assertStatus(422);
    }

    public function test_patch_blocked_when_finalized(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $create = $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
        ]);
        $id = (int) $create->json('data.id_replacement');

        $this->postJson("/api/v1/manager/editions/{$this->edition->id_edition}/finalize")
            ->assertOk();

        $this->patchJson("/api/v1/manager/edition-division-replacements/{$id}", [
            'match_id' => $this->match->id_match,
        ])->assertStatus(409);
    }

    public function test_patch_blocked_when_replacements_not_allowed(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $create = $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
        ]);
        $id = (int) $create->json('data.id_replacement');

        $this->schedule->update(['allow_replacements' => false]);

        $this->patchJson("/api/v1/manager/edition-division-replacements/{$id}", [
            'match_id' => $this->match->id_match,
        ])->assertStatus(409);
    }

    public function test_delete_when_replacements_not_allowed(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->editionDivision->id_edition_division;
        $create = $this->postJson("/api/v1/manager/edition-divisions/{$edId}/replacements", [
            'original_participation_id' => $this->participations[0]->id_participation,
        ]);
        $id = (int) $create->json('data.id_replacement');

        $this->schedule->update(['allow_replacements' => false]);

        $this->deleteJson("/api/v1/manager/edition-division-replacements/{$id}")
            ->assertOk();
        $this->assertDatabaseMissing('edition_division_replacements', [
            'id_replacement' => $id,
        ]);
    }
}
