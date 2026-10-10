<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventDivisionReplacement;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
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
        $event = PadelEvent::factory()->create();
        $division = Division::factory()->create();
        $eventDivision = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $division->id_division,
        ]);
        $original = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $eventDivision->id_event_division,
        ]);
        $subPart = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $eventDivision->id_event_division,
        ]);
        $guest = Player::factory()->create();

        $unknown = EventDivisionReplacement::query()->create([
            'event_division_id' => $eventDivision->id_event_division,
            'original_participation_id' => $original->id_participation,
            'substitute_participation_id' => null,
            'substitute_player_id' => null,
            'match_id' => null,
        ]);
        $this->assertNotNull($unknown->id_replacement);

        $this->expectException(QueryException::class);
        EventDivisionReplacement::query()->create([
            'event_division_id' => $eventDivision->id_event_division,
            'original_participation_id' => $subPart->id_participation,
            'substitute_participation_id' => $subPart->id_participation,
            'substitute_player_id' => $guest->id_player,
            'match_id' => null,
        ]);
    }
}
