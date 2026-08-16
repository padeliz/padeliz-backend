<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionStanding;
use App\Models\EditionParticipation;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class StandingsFillRanksCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fill_ranks_sets_competition_ranks_without_mutating_totals(): void
    {
        $editionDivision = $this->makeEditionDivision();
        $participations = $this->makeParticipations($editionDivision, 4);

        $s1 = EditionDivisionStanding::create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'participation_id' => $participations[0]->id_participation,
            'wins' => 2.0,
            'points_total' => 40,
            'rank' => null,
        ]);
        $s2 = EditionDivisionStanding::create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'participation_id' => $participations[1]->id_participation,
            'wins' => 2.0,
            'points_total' => 40,
            'rank' => null,
        ]);
        $s3 = EditionDivisionStanding::create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'participation_id' => $participations[2]->id_participation,
            'wins' => 1.0,
            'points_total' => null,
            'rank' => null,
        ]);
        $s4 = EditionDivisionStanding::create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'participation_id' => $participations[3]->id_participation,
            'wins' => null,
            'points_total' => null,
            'rank' => null,
        ]);

        $exit = Artisan::call('standings:fill-ranks');
        $this->assertSame(0, $exit);

        $s1->refresh();
        $s2->refresh();
        $s3->refresh();
        $s4->refresh();

        $this->assertSame(1, (int) $s1->rank);
        $this->assertSame(1, (int) $s2->rank);
        $this->assertSame(3, (int) $s3->rank);
        $this->assertSame(4, (int) $s4->rank);

        $this->assertSame(2.0, (float) $s1->wins);
        $this->assertSame(40, (int) $s1->points_total);
        $this->assertSame(1.0, (float) $s3->wins);
        $this->assertNull($s3->points_total);
        $this->assertNull($s4->wins);
        $this->assertNull($s4->points_total);
    }

    public function test_dry_run_does_not_write(): void
    {
        $editionDivision = $this->makeEditionDivision();
        $participation = EditionParticipation::factory()->create([
            'edition_id' => $editionDivision->edition_id,
            'edition_division_id' => $editionDivision->id_edition_division,
            'player_id' => Player::factory()->create()->id_player,
            'is_collecting_points' => true,
        ]);

        $standing = EditionDivisionStanding::create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'participation_id' => $participation->id_participation,
            'wins' => 1.0,
            'points_total' => 21,
            'rank' => null,
        ]);

        $exit = Artisan::call('standings:fill-ranks', ['--dry-run' => true]);
        $this->assertSame(0, $exit);

        $standing->refresh();
        $this->assertNull($standing->rank);
    }

    public function test_skips_edition_divisions_with_all_ranks_set(): void
    {
        $completeEd = $this->makeEditionDivision();
        $incompleteEd = $this->makeEditionDivision();

        $pComplete = EditionParticipation::factory()->create([
            'edition_id' => $completeEd->edition_id,
            'edition_division_id' => $completeEd->id_edition_division,
            'player_id' => Player::factory()->create()->id_player,
            'is_collecting_points' => true,
        ]);
        $completeStanding = EditionDivisionStanding::create([
            'edition_division_id' => $completeEd->id_edition_division,
            'participation_id' => $pComplete->id_participation,
            'wins' => 3.0,
            'points_total' => 50,
            'rank' => 1,
        ]);

        $pIncomplete = EditionParticipation::factory()->create([
            'edition_id' => $incompleteEd->edition_id,
            'edition_division_id' => $incompleteEd->id_edition_division,
            'player_id' => Player::factory()->create()->id_player,
            'is_collecting_points' => true,
        ]);
        $incompleteStanding = EditionDivisionStanding::create([
            'edition_division_id' => $incompleteEd->id_edition_division,
            'participation_id' => $pIncomplete->id_participation,
            'wins' => 2.0,
            'points_total' => 30,
            'rank' => null,
        ]);

        Artisan::call('standings:fill-ranks');

        $completeStanding->refresh();
        $incompleteStanding->refresh();

        $this->assertSame(1, (int) $completeStanding->rank);
        $this->assertSame(3.0, (float) $completeStanding->wins);
        $this->assertSame(1, (int) $incompleteStanding->rank);
    }

    private function makeEditionDivision(): EditionDivision
    {
        $edition = Edition::factory()->create();
        $division = Division::factory()->create();

        return EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $division->id_division,
        ]);
    }

    /**
     * @return list<EditionParticipation>
     */
    private function makeParticipations(EditionDivision $editionDivision, int $count): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = EditionParticipation::factory()->create([
                'edition_id' => $editionDivision->edition_id,
                'edition_division_id' => $editionDivision->id_edition_division,
                'player_id' => Player::factory()->create()->id_player,
                'sort_order' => $i,
                'is_collecting_points' => true,
            ]);
        }

        return $out;
    }
}
