<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\PadelEvent;
use App\Models\EventDivision;
use App\Models\EventLotteryLucky;
use App\Models\EventParticipation;
use App\Models\EventRegistration;
use App\Models\Player;
use App\Services\EventParticipationFlagService;
use App\Services\EventParticipationLotteryGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventParticipationFlagServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventParticipationFlagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventParticipationFlagService(new EventParticipationLotteryGuard());
    }

    public function test_duplicate_and_not_registered_flags(): void
    {
        $event = PadelEvent::factory()->create();
        $edA = EventDivision::factory()->create(['event_id' => $event->id_event]);
        $edB = EventDivision::factory()->create(['event_id' => $event->id_event]);
        $registered = Player::factory()->create();
        $unregistered = Player::factory()->create();

        EventRegistration::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $edA->id_event_division,
            'player_id' => $registered->id_player,
            'is_student' => false,
            'payment' => 50,
        ]);

        $p1 = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $edA->id_event_division,
            'player_id' => $registered->id_player,
        ]);
        $p2 = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $edB->id_event_division,
            'player_id' => $registered->id_player,
        ]);
        $p3 = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $edA->id_event_division,
            'player_id' => $unregistered->id_player,
        ]);

        $payload = $this->service->forEventParticipations(
            (int) $event->id_event,
            collect([$p1, $p2, $p3])
        );

        $this->assertSame(['duplicate'], $payload['flags_by_participation_id'][(int) $p1->id_participation]);
        $this->assertSame(['duplicate'], $payload['flags_by_participation_id'][(int) $p2->id_participation]);
        $this->assertSame(['not_registered'], $payload['flags_by_participation_id'][(int) $p3->id_participation]);
    }

    public function test_registered_not_participating_count_and_participations_locked(): void
    {
        $event = PadelEvent::factory()->create();
        $ed = EventDivision::factory()->create(['event_id' => $event->id_event]);
        $registeredOnly = Player::factory()->create();
        $participating = Player::factory()->create();

        EventRegistration::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $ed->id_event_division,
            'player_id' => $registeredOnly->id_player,
            'is_student' => false,
            'payment' => 50,
        ]);
        EventRegistration::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $ed->id_event_division,
            'player_id' => $participating->id_player,
            'is_student' => false,
            'payment' => 50,
        ]);

        $participation = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $ed->id_event_division,
            'player_id' => $participating->id_player,
        ]);

        EventLotteryLucky::factory()->create([
            'drawn_participation_id' => $participation->id_participation,
        ]);

        $payload = $this->service->forEventParticipations(
            (int) $event->id_event,
            collect([$participation])
        );

        $this->assertSame(1, $payload['event_summary']['registered_not_participating_count']);
        $this->assertTrue($payload['event_summary']['participations_locked']);
    }
}
