<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventDivisionCourt;
use App\Models\EventLotteryLucky;
use App\Models\EventParticipation;
use App\Models\EventRegistration;
use App\Models\GameMatch;
use App\Models\PadelEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

final class EventParticipationMappingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->manager()->create([
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Manager',
        ]);
    }

    public function test_preview_requires_auth(): void
    {
        $event = PadelEvent::factory()->create();
        $this->getJson($this->previewUrl($event))->assertUnauthorized();
    }

    public function test_preview_when_eligible(): void
    {
        Sanctum::actingAs($this->manager);
        $event = PadelEvent::factory()->create(['finalized_at' => null]);
        $ed = EventDivision::factory()->create(['event_id' => $event->id_event]);
        $player = User::factory()->create();
        EventRegistration::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $ed->id_event_division,
            'player_user_id' => $player->id_user,
            'is_student' => false,
            'payment' => 50,
        ]);

        $this->getJson($this->previewUrl($event))
            ->assertOk()
            ->assertJsonPath('data.can_map', true)
            ->assertJsonPath('data.disabled_reason', null)
            ->assertJsonPath('data.registered_player_count', 1)
            ->assertJsonPath('data.event_wide_registration_count', 0);
    }

    public function test_preview_disabled_reasons(): void
    {
        Sanctum::actingAs($this->manager);

        $finalized = PadelEvent::factory()->create(['finalized_at' => time()]);
        EventDivision::factory()->create(['event_id' => $finalized->id_event]);
        $this->getJson($this->previewUrl($finalized))
            ->assertOk()
            ->assertJsonPath('data.can_map', false)
            ->assertJsonPath('data.disabled_reason', 'finalized');

        $noDivisions = PadelEvent::factory()->create(['finalized_at' => null]);
        $this->getJson($this->previewUrl($noDivisions))
            ->assertOk()
            ->assertJsonPath('data.disabled_reason', 'no_divisions');

        $withSchedule = PadelEvent::factory()->create(['finalized_at' => null]);
        $ed = EventDivision::factory()->create(['event_id' => $withSchedule->id_event]);
        $this->createRegularMatch($ed);
        $this->getJson($this->previewUrl($withSchedule))
            ->assertOk()
            ->assertJsonPath('data.disabled_reason', 'schedule_exists');

        $withLottery = PadelEvent::factory()->create(['finalized_at' => null]);
        $edLottery = EventDivision::factory()->create(['event_id' => $withLottery->id_event]);
        $participation = EventParticipation::factory()->create([
            'event_id' => $withLottery->id_event,
            'event_division_id' => $edLottery->id_event_division,
        ]);
        EventLotteryLucky::factory()->create([
            'drawn_participation_id' => $participation->id_participation,
        ]);
        $this->getJson($this->previewUrl($withLottery))
            ->assertOk()
            ->assertJsonPath('data.disabled_reason', 'lottery_exists');
    }

    public function test_map_phase_one_and_phase_two(): void
    {
        Sanctum::actingAs($this->manager);

        $event = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-06-15') + ['finalized_at' => null]);
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        $edSpecific = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $divisionA->id_division,
            'division_title' => 'Specific ED',
            'skill_order' => 1,
            'players_limit' => 16,
        ]);
        $edWide = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $divisionB->id_division,
            'division_title' => 'Wide ED',
            'skill_order' => 2,
            'players_limit' => 16,
        ]);

        $specificPlayer = User::factory()->create();
        $widePlayer = User::factory()->create();

        EventRegistration::factory()->create([
            'event_id' => $event->id_event,
            'player_user_id' => $specificPlayer->id_user,
            'event_division_id' => $edSpecific->id_event_division,
            'is_student' => false,
            'payment' => 50,
        ]);
        EventRegistration::factory()->create([
            'event_id' => $event->id_event,
            'player_user_id' => $widePlayer->id_user,
            'event_division_id' => $edWide->id_event_division,
            'is_student' => false,
            'payment' => 50,
        ]);

        $this->postJson($this->mapUrl($event), ['confirm_overwrite' => false])
            ->assertOk()
            ->assertJsonPath('data.created_count', 2);

        $this->assertDatabaseHas('event_participations', [
            'event_id' => $event->id_event,
            'player_user_id' => $specificPlayer->id_user,
            'event_division_id' => $edSpecific->id_event_division,
        ]);
        $this->assertDatabaseHas('event_participations', [
            'event_id' => $event->id_event,
            'player_user_id' => $widePlayer->id_user,
            'event_division_id' => $edWide->id_event_division,
        ]);
    }

    public function test_map_requires_confirm_overwrite_when_participations_exist(): void
    {
        Sanctum::actingAs($this->manager);
        $event = PadelEvent::factory()->create(['finalized_at' => null]);
        $ed = EventDivision::factory()->create(['event_id' => $event->id_event]);
        EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $ed->id_event_division,
        ]);

        $this->postJson($this->mapUrl($event), ['confirm_overwrite' => false])
            ->assertStatus(409);

        $this->postJson($this->mapUrl($event), ['confirm_overwrite' => true])
            ->assertOk();
    }

    public function test_map_overwrites_manual_participations(): void
    {
        Sanctum::actingAs($this->manager);
        $event = PadelEvent::factory()->create(['finalized_at' => null]);
        $ed = EventDivision::factory()->create(['event_id' => $event->id_event]);
        $manual = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $ed->id_event_division,
        ]);

        $this->postJson($this->mapUrl($event), ['confirm_overwrite' => true])
            ->assertOk();

        $this->assertDatabaseMissing('event_participations', [
            'id_participation' => $manual->id_participation,
        ]);
    }

    public function test_map_allows_over_limit_on_history_matched_ed_when_other_eds_have_capacity(): void
    {
        Sanctum::actingAs($this->manager);

        $advancersDivision = Division::factory()->create(['name' => 'Advancers']);
        $explorersDivision = Division::factory()->create(['name' => 'Explorers']);

        $pastEdition = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-01-01'));
        $pastAdvancersEd = EventDivision::factory()->create([
            'event_id' => $pastEdition->id_event,
            'division_id' => $advancersDivision->id_division,
            'division_title' => 'Advancers',
        ]);

        $event = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-06-15') + ['finalized_at' => null]);
        $advancersEd = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $advancersDivision->id_division,
            'division_title' => 'Advancers',
            'skill_order' => 0,
            'players_limit' => 2,
        ]);
        EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'division_id' => $explorersDivision->id_division,
            'division_title' => 'Explorers',
            'skill_order' => 1,
            'players_limit' => 10,
        ]);

        $players = User::factory()->count(3)->create();
        foreach ($players as $player) {
            EventParticipation::factory()->create([
                'player_user_id' => $player->id_user,
                'event_id' => $pastEdition->id_event,
                'event_division_id' => $pastAdvancersEd->id_event_division,
                'absence_reason' => null,
                'ban_reason' => null,
            ]);
            EventRegistration::factory()->create([
                'event_id' => $event->id_event,
                'event_division_id' => $advancersEd->id_event_division,
                'player_user_id' => $player->id_user,
                'is_student' => false,
                'payment' => 50,
            ]);
        }

        $this->postJson($this->mapUrl($event), ['confirm_overwrite' => false])
            ->assertOk()
            ->assertJsonPath('data.created_count', 3);

        $this->assertSame(
            3,
            EventParticipation::query()
                ->where('event_id', $event->id_event)
                ->where('event_division_id', $advancersEd->id_event_division)
                ->count()
        );
    }

    private function previewUrl(PadelEvent $event): string
    {
        return '/api/v1/manager/events/' . $event->id_event . '/participations/map/preview';
    }

    private function mapUrl(PadelEvent $event): string
    {
        return '/api/v1/manager/events/' . $event->id_event . '/participations/map';
    }

    private function createRegularMatch(EventDivision $eventDivision): GameMatch
    {
        $court = Court::factory()->create();
        $edCourt = EventDivisionCourt::factory()->create([
            'event_division_id' => $eventDivision->id_event_division,
            'court_id' => $court->id_court,
            'display_order' => 0,
        ]);

        return GameMatch::create([
            'event_division_id' => $eventDivision->id_event_division,
            'event_division_court_id' => $edCourt->id_event_division_court,
            'match_kind' => 'REGULAR',
            'sequence_no' => 1,
            'scheduled_start' => '14:00:00',
            'scheduled_end' => '14:30:00',
            'points_target' => 21,
        ]);
    }
}
