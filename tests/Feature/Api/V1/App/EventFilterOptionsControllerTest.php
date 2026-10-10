<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\Club;
use App\Models\EventDivision;
use App\Models\EventLotteryLucky;
use App\Models\EventParticipation;
use App\Models\EventRegistration;
use App\Models\Location;
use App\Models\PadelEvent;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventFilterOptionsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['responsecache.enabled' => false]);
    }

    public function test_requires_date_range(): void
    {
        $this->getJson('/api/v1/app/events/filter-options')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ]);
    }

    public function test_returns_clubs_in_date_range_ordered_by_name(): void
    {
        $clubB = Club::factory()->create(['name' => 'Beta Padél']);
        $clubA = Club::factory()->create(['name' => 'Alpha Padél']);
        $clubOut = Club::factory()->create(['name' => 'Outside']);

        PadelEvent::factory()->forLocation(Location::factory()->forClub($clubB)->create())
            ->create(['date' => '2026-04-10']);
        PadelEvent::factory()->forLocation(Location::factory()->forClub($clubA)->create())
            ->create(['date' => '2026-04-20']);
        PadelEvent::factory()->forLocation(Location::factory()->forClub($clubOut)->create())
            ->create(['date' => '2026-05-05']);

        $response = $this->getJson('/api/v1/app/events/filter-options?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.clubs')
            ->assertJsonPath('data.clubs.0.name', 'Alpha Padél')
            ->assertJsonPath('data.clubs.1.name', 'Beta Padél')
            ->assertJsonPath('data.clubs.0.id_club', $clubA->id_club)
            ->assertJsonPath('data.clubs.1.id_club', $clubB->id_club);
    }

    public function test_guest_gets_zero_unpaid_and_false_lottery(): void
    {
        $club = Club::factory()->create();
        PadelEvent::factory()->forLocation(Location::factory()->forClub($club)->create())
            ->create(['date' => '2026-04-12']);

        $this->getJson('/api/v1/app/events/filter-options?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]))
            ->assertOk()
            ->assertJsonPath('data.unpaid_count', 0)
            ->assertJsonPath('data.has_lottery_winner', false);
    }

    public function test_authenticated_unpaid_count_and_lottery_flag(): void
    {
        $player = Player::factory()->playerOnly()->create();

        $unpaidEdition = PadelEvent::factory()->create(['date' => '2026-04-10']);
        $unpaidDivision = EventDivision::factory()->create(['event_id' => $unpaidEdition->id_event]);
        EventRegistration::factory()->forEvent($unpaidEdition)->create([
            'player_id' => $player->id_player,
            'event_division_id' => $unpaidDivision->id_event_division,
            'payment_status' => RegistrationPaymentStatus::Unpaid,
            'payment' => 55,
            'payment_due_at' => time() + 3600,
        ]);

        $awaitingEdition = PadelEvent::factory()->create(['date' => '2026-04-11']);
        $awaitingDivision = EventDivision::factory()->create(['event_id' => $awaitingEdition->id_event]);
        EventRegistration::factory()->forEvent($awaitingEdition)->create([
            'player_id' => $player->id_player,
            'event_division_id' => $awaitingDivision->id_event_division,
            'payment_status' => RegistrationPaymentStatus::AwaitingProofReview,
            'payment' => 40,
            'payment_due_at' => time() + 3600,
        ]);

        $paidEdition = PadelEvent::factory()->create(['date' => '2026-04-12']);
        $paidDivision = EventDivision::factory()->create(['event_id' => $paidEdition->id_event]);
        EventRegistration::factory()->forEvent($paidEdition)->paid()->create([
            'player_id' => $player->id_player,
            'event_division_id' => $paidDivision->id_event_division,
            'payment' => 55,
        ]);

        $lotteryEdition = PadelEvent::factory()->create(['date' => '2026-04-15']);
        $lotteryDivision = EventDivision::factory()->create(['event_id' => $lotteryEdition->id_event]);
        $participation = EventParticipation::factory()->create([
            'event_id' => $lotteryEdition->id_event,
            'event_division_id' => $lotteryDivision->id_event_division,
            'player_id' => $player->id_player,
        ]);
        EventLotteryLucky::factory()->create([
            'drawn_participation_id' => $participation->id_participation,
            'accepted_at' => time() - 10,
            'rejected_at' => null,
        ]);

        Sanctum::actingAs($player);

        $this->getJson('/api/v1/app/events/filter-options?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]))
            ->assertOk()
            ->assertJsonPath('data.unpaid_count', 2)
            ->assertJsonPath('data.has_lottery_winner', true);
    }

    public function test_authenticated_lottery_false_when_not_accepted(): void
    {
        $player = Player::factory()->playerOnly()->create();
        $event = PadelEvent::factory()->create(['date' => '2026-04-15']);
        $division = EventDivision::factory()->create(['event_id' => $event->id_event]);
        $participation = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'player_id' => $player->id_player,
        ]);
        EventLotteryLucky::factory()->create([
            'drawn_participation_id' => $participation->id_participation,
            'accepted_at' => null,
            'rejected_at' => null,
        ]);

        Sanctum::actingAs($player);

        $this->getJson('/api/v1/app/events/filter-options?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]))
            ->assertOk()
            ->assertJsonPath('data.unpaid_count', 0)
            ->assertJsonPath('data.has_lottery_winner', false);
    }
}
