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
use App\Models\MatchSide;
use App\Models\Player;
use App\Services\Schedule\ScheduleMatchPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScheduleMatchPersisterReplacementWipeTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_regular_matches_wipes_replacements_including_null_match_id(): void
    {
        [$editionDivision, $otherEd, $match] = $this->seedTwoDivisionsWithMatches();

        $scoped = EditionDivisionReplacement::query()->create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'original_participation_id' => $editionDivision->participations()->orderBy('id_participation')->first()->id_participation,
            'match_id' => $match->id_match,
        ]);
        $wide = EditionDivisionReplacement::query()->create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'original_participation_id' => $editionDivision->participations()->orderBy('id_participation')->skip(1)->first()->id_participation,
            'match_id' => null,
        ]);
        $other = EditionDivisionReplacement::query()->create([
            'edition_division_id' => $otherEd->id_edition_division,
            'original_participation_id' => $otherEd->participations()->orderBy('id_participation')->first()->id_participation,
            'match_id' => null,
        ]);

        $deleted = (new ScheduleMatchPersister())->deleteRegularMatches($editionDivision);

        $this->assertGreaterThan(0, $deleted);
        $this->assertDatabaseMissing('edition_division_replacements', ['id_replacement' => $scoped->id_replacement]);
        $this->assertDatabaseMissing('edition_division_replacements', ['id_replacement' => $wide->id_replacement]);
        $this->assertDatabaseHas('edition_division_replacements', ['id_replacement' => $other->id_replacement]);
    }

    public function test_delete_regular_matches_with_none_leaves_replacements(): void
    {
        $edition = Edition::factory()->create();
        $division = Division::factory()->create();
        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $division->id_division,
        ]);
        $part = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $editionDivision->id_edition_division,
        ]);
        $row = EditionDivisionReplacement::query()->create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'original_participation_id' => $part->id_participation,
            'match_id' => null,
        ]);

        $deleted = (new ScheduleMatchPersister())->deleteRegularMatches($editionDivision);

        $this->assertSame(0, $deleted);
        $this->assertDatabaseHas('edition_division_replacements', [
            'id_replacement' => $row->id_replacement,
        ]);
    }

    /**
     * @return array{0: EditionDivision, 1: EditionDivision, 2: GameMatch}
     */
    private function seedTwoDivisionsWithMatches(): array
    {
        $edition = Edition::factory()->create();
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $divisionA->id_division,
        ]);
        $otherEd = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $divisionB->id_division,
        ]);

        foreach ([$editionDivision, $otherEd] as $ed) {
            for ($i = 0; $i < 2; $i++) {
                EditionParticipation::factory()->create([
                    'edition_id' => $edition->id_edition,
                    'edition_division_id' => $ed->id_edition_division,
                    'sort_order' => $i,
                ]);
            }
            $court = Court::factory()->create();
            $edCourt = EditionDivisionCourt::factory()->create([
                'edition_division_id' => $ed->id_edition_division,
                'court_id' => $court->id_court,
            ]);
            $match = GameMatch::create([
                'edition_division_id' => $ed->id_edition_division,
                'edition_division_court_id' => $edCourt->id_edition_division_court,
                'match_kind' => 'REGULAR',
                'sequence_no' => 1,
                'scheduled_start' => '14:00:00',
                'scheduled_end' => '14:30:00',
            ]);
            MatchSide::create(['match_id' => $match->id_match, 'side' => 'LEFT', 'score' => null]);
            MatchSide::create(['match_id' => $match->id_match, 'side' => 'RIGHT', 'score' => null]);
        }

        $match = GameMatch::query()
            ->where('edition_division_id', $editionDivision->id_edition_division)
            ->firstOrFail();

        return [$editionDivision->fresh(), $otherEd->fresh(), $match];
    }
}
