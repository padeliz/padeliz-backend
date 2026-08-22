<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionLotteryLucky;
use App\Models\EditionParticipation;
use App\Models\EditionRegistration;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Court;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class EditionParticipationMappingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Manager',
        ]);
    }

    public function test_preview_requires_auth(): void
    {
        $edition = Edition::factory()->create();
        $this->getJson($this->previewUrl($edition))->assertUnauthorized();
    }

    public function test_preview_when_eligible(): void
    {
        Sanctum::actingAs($this->manager);
        $edition = Edition::factory()->create(['finalized_at' => null]);
        $ed = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);
        $player = Player::factory()->create();
        EditionRegistration::factory()->withoutEditionDivision()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $player->id_player,
            'is_student' => false,
            'payment' => 50,
        ]);

        $this->getJson($this->previewUrl($edition))
            ->assertOk()
            ->assertJsonPath('data.can_map', true)
            ->assertJsonPath('data.disabled_reason', null)
            ->assertJsonPath('data.registered_player_count', 1)
            ->assertJsonPath('data.edition_wide_registration_count', 1);
    }

    public function test_preview_disabled_reasons(): void
    {
        Sanctum::actingAs($this->manager);

        $finalized = Edition::factory()->create(['finalized_at' => time()]);
        EditionDivision::factory()->create(['edition_id' => $finalized->id_edition]);
        $this->getJson($this->previewUrl($finalized))
            ->assertOk()
            ->assertJsonPath('data.can_map', false)
            ->assertJsonPath('data.disabled_reason', 'finalized');

        $noDivisions = Edition::factory()->create(['finalized_at' => null]);
        $this->getJson($this->previewUrl($noDivisions))
            ->assertOk()
            ->assertJsonPath('data.disabled_reason', 'no_divisions');

        $withSchedule = Edition::factory()->create(['finalized_at' => null]);
        $ed = EditionDivision::factory()->create(['edition_id' => $withSchedule->id_edition]);
        $this->createRegularMatch($ed);
        $this->getJson($this->previewUrl($withSchedule))
            ->assertOk()
            ->assertJsonPath('data.disabled_reason', 'schedule_exists');

        $withLottery = Edition::factory()->create(['finalized_at' => null]);
        $edLottery = EditionDivision::factory()->create(['edition_id' => $withLottery->id_edition]);
        $participation = EditionParticipation::factory()->create([
            'edition_id' => $withLottery->id_edition,
            'edition_division_id' => $edLottery->id_edition_division,
        ]);
        EditionLotteryLucky::factory()->create([
            'drawn_participation_id' => $participation->id_participation,
        ]);
        $this->getJson($this->previewUrl($withLottery))
            ->assertOk()
            ->assertJsonPath('data.disabled_reason', 'lottery_exists');
    }

    public function test_map_phase_one_and_phase_two(): void
    {
        Sanctum::actingAs($this->manager);

        $edition = Edition::factory()->create(['date' => '2026-06-15', 'finalized_at' => null]);
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        $edSpecific = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $divisionA->id_division,
            'division_title' => 'Specific ED',
            'skill_order' => 1,
            'players_limit' => 16,
        ]);
        $edWide = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $divisionB->id_division,
            'division_title' => 'Wide ED',
            'skill_order' => 2,
            'players_limit' => 16,
        ]);

        $specificPlayer = Player::factory()->create();
        $widePlayer = Player::factory()->create();

        EditionRegistration::factory()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $specificPlayer->id_player,
            'edition_division_id' => $edSpecific->id_edition_division,
            'is_student' => false,
            'payment' => 50,
        ]);
        EditionRegistration::factory()->withoutEditionDivision()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $widePlayer->id_player,
            'is_student' => false,
            'payment' => 50,
        ]);

        $this->postJson($this->mapUrl($edition), ['confirm_overwrite' => false])
            ->assertOk()
            ->assertJsonPath('data.created_count', 2);

        $this->assertDatabaseHas('edition_participations', [
            'edition_id' => $edition->id_edition,
            'player_id' => $specificPlayer->id_player,
            'edition_division_id' => $edSpecific->id_edition_division,
        ]);
        $this->assertDatabaseHas('edition_participations', [
            'edition_id' => $edition->id_edition,
            'player_id' => $widePlayer->id_player,
            'edition_division_id' => $edSpecific->id_edition_division,
        ]);
    }

    public function test_map_requires_confirm_overwrite_when_participations_exist(): void
    {
        Sanctum::actingAs($this->manager);
        $edition = Edition::factory()->create(['finalized_at' => null]);
        $ed = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);
        EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $ed->id_edition_division,
        ]);

        $this->postJson($this->mapUrl($edition), ['confirm_overwrite' => false])
            ->assertStatus(409);

        $this->postJson($this->mapUrl($edition), ['confirm_overwrite' => true])
            ->assertOk();
    }

    public function test_map_overwrites_manual_participations(): void
    {
        Sanctum::actingAs($this->manager);
        $edition = Edition::factory()->create(['finalized_at' => null]);
        $ed = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);
        $manual = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $ed->id_edition_division,
        ]);

        $this->postJson($this->mapUrl($edition), ['confirm_overwrite' => true])
            ->assertOk();

        $this->assertDatabaseMissing('edition_participations', [
            'id_participation' => $manual->id_participation,
        ]);
    }

    public function test_map_allows_over_limit_on_history_matched_ed_when_other_eds_have_capacity(): void
    {
        Sanctum::actingAs($this->manager);

        $advancersDivision = Division::factory()->create(['name' => 'Advancers']);
        $explorersDivision = Division::factory()->create(['name' => 'Explorers']);

        $pastEdition = Edition::factory()->create(['date' => '2026-01-01']);
        $pastAdvancersEd = EditionDivision::factory()->create([
            'edition_id' => $pastEdition->id_edition,
            'division_id' => $advancersDivision->id_division,
            'division_title' => 'Advancers',
        ]);

        $edition = Edition::factory()->create(['date' => '2026-06-15', 'finalized_at' => null]);
        $advancersEd = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $advancersDivision->id_division,
            'division_title' => 'Advancers',
            'skill_order' => 0,
            'players_limit' => 2,
        ]);
        EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $explorersDivision->id_division,
            'division_title' => 'Explorers',
            'skill_order' => 1,
            'players_limit' => 10,
        ]);

        $players = Player::factory()->count(3)->create();
        foreach ($players as $player) {
            EditionParticipation::factory()->create([
                'player_id' => $player->id_player,
                'edition_id' => $pastEdition->id_edition,
                'edition_division_id' => $pastAdvancersEd->id_edition_division,
                'absence_reason' => null,
                'ban_reason' => null,
            ]);
            EditionRegistration::factory()->withoutEditionDivision()->create([
                'edition_id' => $edition->id_edition,
                'player_id' => $player->id_player,
                'is_student' => false,
                'payment' => 50,
            ]);
        }

        $this->postJson($this->mapUrl($edition), ['confirm_overwrite' => false])
            ->assertOk()
            ->assertJsonPath('data.created_count', 3);

        $this->assertSame(
            3,
            EditionParticipation::query()
                ->where('edition_id', $edition->id_edition)
                ->where('edition_division_id', $advancersEd->id_edition_division)
                ->count()
        );
    }

    private function previewUrl(Edition $edition): string
    {
        return '/api/v1/manager/editions/' . $edition->id_edition . '/participations/map/preview';
    }

    private function mapUrl(Edition $edition): string
    {
        return '/api/v1/manager/editions/' . $edition->id_edition . '/participations/map';
    }

    private function createRegularMatch(EditionDivision $editionDivision): GameMatch
    {
        $court = Court::factory()->create();
        $edCourt = EditionDivisionCourt::factory()->create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'court_id' => $court->id_court,
            'display_order' => 0,
        ]);

        return GameMatch::create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'edition_division_court_id' => $edCourt->id_edition_division_court,
            'match_kind' => 'REGULAR',
            'sequence_no' => 1,
            'scheduled_start' => '14:00:00',
            'scheduled_end' => '14:30:00',
            'points_target' => 21,
        ]);
    }
}
