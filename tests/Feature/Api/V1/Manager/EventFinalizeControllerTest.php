<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\EventDivisionSchedule;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class EventFinalizeControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private PadelEvent $event;

    private EventDivision $eventDivision;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);

        $this->event = PadelEvent::factory()->create([
            'name' => 'Finalize Me',
            'arriving_time' => '09:00:00',
            'closing_time' => '18:00:00',
        ]);
        $division = Division::factory()->create();

        $this->eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'players_limit' => 8,
            'playing_start_time' => '14:00:00',
            'playing_end_time' => '17:00:00',
        ]);

        EventDivisionSchedule::factory()->create([
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_opponents_nr' => 4,
            'player_matches_repeat' => 1,
            'include_final' => false,
            'has_demonstrative_match' => false,
            'include_scores' => true,
            'allow_replacements' => true,
        ]);

        $this->court = Court::factory()->create();
        EventDivisionCourt::factory()->create([
            'event_division_id' => $this->eventDivision->id_event_division,
            'court_id' => $this->court->id_court,
            'display_order' => 0,
        ]);

        for ($i = 0; $i < 8; $i++) {
            EventParticipation::factory()->create([
                'event_id' => $this->event->id_event,
                'event_division_id' => $this->eventDivision->id_event_division,
                'player_id' => Player::factory()->create()->id_player,
                'sort_order' => $i,
                'is_collecting_points' => true,
                'absence_reason' => null,
                'ban_reason' => null,
            ]);
        }
    }

    public function test_finalize_edition(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/finalize")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id_event', $this->event->id_event);

        $this->assertNotNull($this->event->fresh()->finalized_at);

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/finalize")
            ->assertStatus(409);
    }

    public function test_locked_mutation_returns_409_when_finalized(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/finalize")
            ->assertOk();

        $edId = $this->eventDivision->id_event_division;

        $this->postJson("/api/v1/manager/event-divisions/{$edId}/schedule/generate", [])
            ->assertStatus(409);

        $this->postJson("/api/v1/manager/event-divisions/{$edId}/standings/recalculate")
            ->assertStatus(409);

        $this->postJson("/api/v1/manager/event-divisions/{$edId}/replacements", [
            'original_participation_id' => EventParticipation::query()
                ->where('event_division_id', $edId)
                ->value('id_participation'),
            'substitute_player_id' => Player::factory()->create()->id_player,
        ])->assertStatus(409);

        $this->patchJson(
            "/api/v1/manager/event-divisions/{$edId}",
            ['division_title' => 'Locked Title']
        )->assertStatus(409);
    }

    public function test_edition_details_still_updatable_when_finalized(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/finalize")
            ->assertOk();

        $this->patchJson("/api/v1/manager/events/{$this->event->id_event}", [
            'name' => 'Still Editable',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Still Editable');

        $this->assertNotNull($this->event->fresh()->finalized_at);
        $this->assertSame('Still Editable', $this->event->fresh()->name);
    }

    public function test_schedule_preview_ok_when_finalized(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/finalize")
            ->assertOk();

        $this->postJson(
            "/api/v1/manager/event-divisions/{$this->eventDivision->id_event_division}/schedule/preview",
            []
        )->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_edition_division_rejects_finalized_at_write(): void
    {
        Sanctum::actingAs($this->manager);

        $edId = $this->eventDivision->id_event_division;

        $this->patchJson("/api/v1/manager/event-divisions/{$edId}", [
            'finalized_at' => time(),
            'division_title' => 'No Finalize Via Write',
        ])
            ->assertOk()
            ->assertJsonMissingPath('data.finalized_at')
            ->assertJsonPath('data.division_title', 'No Finalize Via Write');

        $this->assertNull($this->event->fresh()->finalized_at);
    }

    public function test_posters_return_409_when_finalized(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/manager/events/{$this->event->id_event}/finalize")
            ->assertOk();

        $edId = $this->eventDivision->id_event_division;

        $this->postJson("/api/v1/manager/event-divisions/{$edId}/posters/ranking", [
            'organizer' => 1,
            'partner' => 1,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'PadelEvent is finalized.');

        $edCourtId = EventDivisionCourt::query()
            ->where('event_division_id', $edId)
            ->value('id_event_division_court');

        $this->postJson("/api/v1/manager/event-divisions/{$edId}/posters/matches", [
            'court' => $edCourtId,
            'organizer' => 1,
            'partner' => 1,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'PadelEvent is finalized.');
    }
}
