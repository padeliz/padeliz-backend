<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionDivisionReplacement;
use App\Models\EditionParticipation;
use App\Models\Player;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ReplacementSubstituteCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_players_has_no_got_replaced_column(): void
    {
        $this->assertFalse(Schema::hasColumn('match_players', 'got_replaced'));
    }

    public function test_check_rejects_both_substitutes_and_allows_both_null(): void
    {
        $edition = Edition::factory()->create();
        $division = Division::factory()->create();
        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $division->id_division,
        ]);
        $original = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $editionDivision->id_edition_division,
        ]);
        $subPart = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $editionDivision->id_edition_division,
        ]);
        $guest = Player::factory()->create();

        $unknown = EditionDivisionReplacement::query()->create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'original_participation_id' => $original->id_participation,
            'substitute_participation_id' => null,
            'substitute_player_id' => null,
            'match_id' => null,
        ]);
        $this->assertNotNull($unknown->id_replacement);

        $this->expectException(QueryException::class);
        EditionDivisionReplacement::query()->create([
            'edition_division_id' => $editionDivision->id_edition_division,
            'original_participation_id' => $subPart->id_participation,
            'substitute_participation_id' => $subPart->id_participation,
            'substitute_player_id' => $guest->id_player,
            'match_id' => null,
        ]);
    }
}
