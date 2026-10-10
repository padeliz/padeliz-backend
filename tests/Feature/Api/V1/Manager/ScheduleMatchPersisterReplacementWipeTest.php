<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\EventDivisionReplacement;
use App\Models\EventParticipation;
use App\Models\GameMatch;
use App\Models\MatchSide;
use App\Models\PadelEvent;
use App\Services\Schedule\ScheduleMatchPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScheduleMatchPersisterReplacementWipeTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_regular_matches_wipes_replacements_including_null_match_id(): void
    {
        [$eventDivision, $otherEd, $match] = $this->seedTwoDivisionsWithMatches();

        $scoped = EventDivisionReplacement::query()->create([
            'event_division_id' => $eventDivision->id_event_division,
            'original_participation_id' => $eventDivision->participations()->orderBy('id_participation')->first()->id_participation,
            'match_id' => $match->id_match,
        ]);
        $wide = EventDivisionReplacement::query()->create([
            'event_division_id' => $eventDivision->id_event_division,
            'original_participation_id' => $eventDivision->participations()->orderBy('id_participation')->skip(1)->first()->id_participation,
            'match_id' => null,
        ]);
        $other = EventDivisionReplacement::query()->create([
            'event_division_id' => $otherEd->id_event_division,
            'original_participation_id' => $otherEd->participations()->orderBy('id_participation')->first()->id_participation,
            'match_id' => null,
        ]);

        $deleted = (new ScheduleMatchPersister())->deleteRegularMatches($eventDivision);

        $this->assertGreaterThan(0, $deleted);
        $this->assertDatabaseMissing('event_division_replacements', ['id_replacement' => $scoped->id_replacement]);
        $this->assertDatabaseMissing('event_division_replacements', ['id_replacement' => $wide->id_replacement]);
        $this->assertDatabaseHas('event_division_replacements', ['id_replacement' => $other->id_replacement]);
    }

    public function test_delete_regular_matches_with_none_leaves_replacements(): void
    {
        $event = PadelEvent::factory()->create();
        $division = Division::factory()->create();
        $eventDivision = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $division->id_division,
        ]);
        $part = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $eventDivision->id_event_division,
        ]);
        $row = EventDivisionReplacement::query()->create([
            'event_division_id' => $eventDivision->id_event_division,
            'original_participation_id' => $part->id_participation,
            'match_id' => null,
        ]);

        $deleted = (new ScheduleMatchPersister())->deleteRegularMatches($eventDivision);

        $this->assertSame(0, $deleted);
        $this->assertDatabaseHas('event_division_replacements', [
            'id_replacement' => $row->id_replacement,
        ]);
    }

    /**
     * @return array{0: EventDivision, 1: EventDivision, 2: GameMatch}
     */
    private function seedTwoDivisionsWithMatches(): array
    {
        $event = PadelEvent::factory()->create();
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        $eventDivision = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $divisionA->id_division,
        ]);
        $otherEd = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $divisionB->id_division,
        ]);

        foreach ([$eventDivision, $otherEd] as $ed) {
            for ($i = 0; $i < 2; $i++) {
                EventParticipation::factory()->create([
                    'event_id' => $event->id_event,
                    'event_division_id' => $ed->id_event_division,
                    'sort_order' => $i,
                ]);
            }
            $court = Court::factory()->create();
            $edCourt = EventDivisionCourt::factory()->create([
                'event_division_id' => $ed->id_event_division,
                'court_id' => $court->id_court,
            ]);
            $match = GameMatch::create([
                'event_division_id' => $ed->id_event_division,
                'event_division_court_id' => $edCourt->id_event_division_court,
                'match_kind' => 'REGULAR',
                'sequence_no' => 1,
                'scheduled_start' => '14:00:00',
                'scheduled_end' => '14:30:00',
            ]);
            MatchSide::create(['match_id' => $match->id_match, 'side' => 'LEFT', 'score' => null]);
            MatchSide::create(['match_id' => $match->id_match, 'side' => 'RIGHT', 'score' => null]);
        }

        $match = GameMatch::query()
            ->where('event_division_id', $eventDivision->id_event_division)
            ->firstOrFail();

        return [$eventDivision->fresh(), $otherEd->fresh(), $match];
    }
}
