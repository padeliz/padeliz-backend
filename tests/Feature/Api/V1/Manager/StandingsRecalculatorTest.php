<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionCourt;
use App\Models\EditionDivisionReplacement;
use App\Models\EditionDivisionStanding;
use App\Models\EditionParticipation;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\MatchSide;
use App\Models\Player;
use App\Services\Standings\StandingsRecalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StandingsRecalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private EditionDivision $editionDivision;

    /** @var list<EditionParticipation> */
    private array $participations = [];

    private GameMatch $match;

    private EditionDivisionCourt $edCourt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $edition = Edition::factory()->create();
        $division = Division::factory()->create();

        $this->editionDivision = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $division->id_division,
        ]);

        $court = Court::factory()->create();
        $this->edCourt = EditionDivisionCourt::factory()->create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'court_id' => $court->id_court,
            'display_order' => 0,
        ]);

        for ($i = 0; $i < 4; $i++) {
            $this->participations[] = EditionParticipation::factory()->create([
                'edition_id' => $edition->id_edition,
                'edition_division_id' => $this->editionDivision->id_edition_division,
                'player_id' => Player::factory()->create()->id_player,
                'sort_order' => $i,
                'is_collecting_points' => true,
                'absence_reason' => null,
                'ban_reason' => null,
            ]);
        }

        $this->match = GameMatch::create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'edition_division_court_id' => $this->edCourt->id_edition_division_court,
            'match_kind' => 'REGULAR',
            'sequence_no' => 1,
            'scheduled_start' => '14:00:00',
            'scheduled_end' => '14:30:00',
            'points_target' => 21,
        ]);

        $left = MatchSide::create(['match_id' => $this->match->id_match, 'side' => 'LEFT', 'score' => 21]);
        $right = MatchSide::create(['match_id' => $this->match->id_match, 'side' => 'RIGHT', 'score' => 15]);

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

    public function test_recalculator_applies_wins_points_and_skips_replaced(): void
    {
        EditionDivisionReplacement::query()->create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'original_participation_id' => $this->participations[3]->id_participation,
            'substitute_participation_id' => $this->participations[0]->id_participation,
            'match_id' => $this->match->id_match,
        ]);

        $standings = (new StandingsRecalculator())->recalculate($this->editionDivision);
        $byParticipation = collect($standings)->keyBy('participation_id');

        $this->assertSame(1.0, (float) $byParticipation[$this->participations[0]->id_participation]->wins);
        $this->assertSame(21, (int) $byParticipation[$this->participations[0]->id_participation]->points_total);
        $this->assertSame(1.0, (float) $byParticipation[$this->participations[1]->id_participation]->wins);
        $this->assertSame(21, (int) $byParticipation[$this->participations[1]->id_participation]->points_total);

        $this->assertSame(0.0, (float) $byParticipation[$this->participations[2]->id_participation]->wins);
        $this->assertSame(15, (int) $byParticipation[$this->participations[2]->id_participation]->points_total);

        $this->assertSame(0.0, (float) $byParticipation[$this->participations[3]->id_participation]->wins);
        $this->assertSame(0, (int) $byParticipation[$this->participations[3]->id_participation]->points_total);

        $this->assertSame(1, (int) $byParticipation[$this->participations[0]->id_participation]->rank);
        $this->assertSame(1, (int) $byParticipation[$this->participations[1]->id_participation]->rank);
        $this->assertSame(3, (int) $byParticipation[$this->participations[2]->id_participation]->rank);
    }

    public function test_recalculator_skips_non_collecting_points(): void
    {
        $this->participations[0]->update(['is_collecting_points' => false]);

        $standings = (new StandingsRecalculator())->recalculate($this->editionDivision);
        $byParticipation = collect($standings)->keyBy('participation_id');

        $this->assertSame(0.0, (float) $byParticipation[$this->participations[0]->id_participation]->wins);
        $this->assertSame(0, (int) $byParticipation[$this->participations[0]->id_participation]->points_total);
        $this->assertSame(1.0, (float) $byParticipation[$this->participations[1]->id_participation]->wins);
    }

    public function test_recalculate_endpoint_and_finalize_lock(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/standings/recalculate"
        )->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThan(0, EditionDivisionStanding::query()
            ->where('edition_division_id', $this->editionDivision->id_edition_division)
            ->count());

        $this->postJson(
            "/api/v1/manager/editions/{$this->editionDivision->edition_id}/finalize"
        )->assertStatus(200);

        $this->postJson(
            "/api/v1/manager/edition-divisions/{$this->editionDivision->id_edition_division}/standings/recalculate"
        )->assertStatus(409);

        $this->patchJson(
            "/api/v1/manager/matches/{$this->match->id_match}/scores",
            ['left_score' => 10, 'right_score' => 10]
        )->assertStatus(409);
    }

    public function test_tie_awards_half_win(): void
    {
        MatchSide::query()->where('match_id', $this->match->id_match)->where('side', 'LEFT')->update(['score' => 18]);
        MatchSide::query()->where('match_id', $this->match->id_match)->where('side', 'RIGHT')->update(['score' => 18]);

        $standings = (new StandingsRecalculator())->recalculate($this->editionDivision);
        $byParticipation = collect($standings)->keyBy('participation_id');

        $this->assertSame(0.5, (float) $byParticipation[$this->participations[0]->id_participation]->wins);
        $this->assertSame(0.5, (float) $byParticipation[$this->participations[2]->id_participation]->wins);
        $this->assertSame(18, (int) $byParticipation[$this->participations[0]->id_participation]->points_total);
    }

    public function test_match_scoped_replacement_skips_only_that_match(): void
    {
        $this->createSecondRegularMatch(10, 20);

        EditionDivisionReplacement::query()->create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'original_participation_id' => $this->participations[3]->id_participation,
            'match_id' => $this->match->id_match,
        ]);

        $standings = (new StandingsRecalculator())->recalculate($this->editionDivision);
        $byParticipation = collect($standings)->keyBy('participation_id');

        $this->assertSame(1.0, (float) $byParticipation[$this->participations[3]->id_participation]->wins);
        $this->assertSame(20, (int) $byParticipation[$this->participations[3]->id_participation]->points_total);
        $this->assertSame(1.0, (float) $byParticipation[$this->participations[2]->id_participation]->wins);
        $this->assertSame(35, (int) $byParticipation[$this->participations[2]->id_participation]->points_total);
    }

    public function test_division_wide_replacement_skips_all_regular_matches(): void
    {
        $this->createSecondRegularMatch(10, 20);

        EditionDivisionReplacement::query()->create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'original_participation_id' => $this->participations[3]->id_participation,
            'match_id' => null,
        ]);

        $standings = (new StandingsRecalculator())->recalculate($this->editionDivision);
        $byParticipation = collect($standings)->keyBy('participation_id');

        $this->assertSame(0.0, (float) $byParticipation[$this->participations[3]->id_participation]->wins);
        $this->assertSame(0, (int) $byParticipation[$this->participations[3]->id_participation]->points_total);
    }

    public function test_unknown_substitute_still_skips_original(): void
    {
        EditionDivisionReplacement::query()->create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'original_participation_id' => $this->participations[3]->id_participation,
            'substitute_participation_id' => null,
            'substitute_player_id' => null,
            'match_id' => $this->match->id_match,
        ]);

        $standings = (new StandingsRecalculator())->recalculate($this->editionDivision);
        $byParticipation = collect($standings)->keyBy('participation_id');

        $this->assertSame(0.0, (float) $byParticipation[$this->participations[3]->id_participation]->wins);
        $this->assertSame(0, (int) $byParticipation[$this->participations[3]->id_participation]->points_total);
    }

    public function test_in_division_substitute_is_not_credited_for_replaced_slot(): void
    {
        EditionDivisionReplacement::query()->create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'original_participation_id' => $this->participations[3]->id_participation,
            'substitute_participation_id' => $this->participations[1]->id_participation,
            'match_id' => $this->match->id_match,
        ]);

        $standings = (new StandingsRecalculator())->recalculate($this->editionDivision);
        $byParticipation = collect($standings)->keyBy('participation_id');

        $this->assertSame(1.0, (float) $byParticipation[$this->participations[1]->id_participation]->wins);
        $this->assertSame(21, (int) $byParticipation[$this->participations[1]->id_participation]->points_total);
        $this->assertSame(0.0, (float) $byParticipation[$this->participations[3]->id_participation]->wins);
        $this->assertSame(0, (int) $byParticipation[$this->participations[3]->id_participation]->points_total);
    }

    private function createSecondRegularMatch(int $leftScore, int $rightScore): GameMatch
    {
        $match = GameMatch::create([
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'edition_division_court_id' => $this->edCourt->id_edition_division_court,
            'match_kind' => 'REGULAR',
            'sequence_no' => 2,
            'scheduled_start' => '14:30:00',
            'scheduled_end' => '15:00:00',
            'points_target' => 21,
        ]);
        $left = MatchSide::create(['match_id' => $match->id_match, 'side' => 'LEFT', 'score' => $leftScore]);
        $right = MatchSide::create(['match_id' => $match->id_match, 'side' => 'RIGHT', 'score' => $rightScore]);
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

        return $match;
    }
}

