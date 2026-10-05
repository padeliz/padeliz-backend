<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\Club;
use App\Models\Division;
use App\Models\PadelEvent;
use App\Models\EventDivision;
use App\Models\EventLotteryLucky;
use App\Models\EventParticipation;
use App\Models\EventRegistration;
use App\Models\Location;
use App\Models\Organizer;
use App\Models\Player;
use App\Services\EventBadgeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tz;

    protected function setUp(): void
    {
        parent::setUp();

        config(['responsecache.enabled' => false]);
        $this->tz = (string) config('app.timezone', 'Europe/Bucharest');
    }

    public function test_requires_date_from_and_date_to_query_parameters(): void
    {
        $this->getJson('/api/v1/app/events')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonStructure(['errors' => ['date_from', 'date_to']]);
    }

    public function test_rejects_when_date_to_is_before_date_from(): void
    {
        $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-09',
        ]))
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure(['errors' => ['date_to']]);
    }

    public function test_guest_can_list_editions_in_date_range(): void
    {
        PadelEvent::factory()->create(['date' => '2026-04-05']);
        PadelEvent::factory()->create(['date' => '2026-04-15']);
        PadelEvent::factory()->create(['date' => '2026-04-25']);

        $response = $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-20',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-04-15');
    }

    public function test_app_index_omits_description_and_includes_cover_organizer_badges(): void
    {
        $organizer = Organizer::factory()->create(['name' => 'Tour Org']);
        $date = Carbon::now($this->tz)->addDays(14)->format('Y-m-d');
        $event = PadelEvent::factory()->create([
            'date' => $date,
            'description' => '<p>Secret description</p>',
            'cover_path' => 'events/1/cover/test.png',
            'organizer_id' => $organizer->id_organizer,
            'inserted_at' => time() - 1800,
            'registration_allowed_until' => time() + 86400,
            'cancellation_allowed_until' => time() + 86400,
        ]);

        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => Carbon::now($this->tz)->format('Y-m-d'),
            'date_to' => Carbon::now($this->tz)->addDays(30)->format('Y-m-d'),
        ]));

        $response->assertOk()
            ->assertJsonPath('data.0.id_event', $event->id_event)
            ->assertJsonPath('data.0.organizer.name', 'Tour Org')
            ->assertJsonStructure(['data' => [['cover_url', 'badges', 'organizer']]]);

        $this->assertArrayNotHasKey('description', $response->json('data.0'));
        $this->assertNotNull($response->json('data.0.cover_url'));
        $keys = collect($response->json('data.0.badges'))->pluck('key')->all();
        $this->assertContains(EventBadgeService::KEY_NEW, $keys);
    }

    public function test_guest_can_show_edition(): void
    {
        $event = PadelEvent::factory()->create(['date' => '2026-04-15']);

        $this->getJson('/api/v1/app/events/' . $event->id_event)
            ->assertOk()
            ->assertJsonPath('data.id_event', $event->id_event)
            ->assertJsonPath('data.date', '2026-04-15');
    }

    public function test_app_show_includes_description_cover_organizer_and_badges(): void
    {
        $organizer = Organizer::factory()->create(['name' => 'Show Org']);
        $player = Player::factory()->playerOnly()->create();
        $now = time();
        $event = PadelEvent::factory()->create([
            'date' => Carbon::now($this->tz)->addDays(14)->format('Y-m-d'),
            'description' => '<p>Show description</p>',
            'cover_path' => 'events/99/cover/show.png',
            'organizer_id' => $organizer->id_organizer,
            'inserted_at' => $now - 1800,
            'registration_allowed_until' => $now + 86400,
            'cancellation_allowed_until' => $now + 86400,
        ]);
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 16,
        ]);
        EventRegistration::factory()->forEvent($event)->paid()->create([
            'player_id' => $player->id_player,
            'event_division_id' => $division->id_event_division,
            'payment' => 55,
        ]);

        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/app/events/' . $event->id_event);

        $response->assertOk()
            ->assertJsonPath('data.description', '<p>Show description</p>')
            ->assertJsonPath('data.organizer.name', 'Show Org')
            ->assertJsonStructure(['data' => ['description', 'cover_url', 'organizer', 'badges']]);

        $this->assertNotNull($response->json('data.cover_url'));
        $keys = collect($response->json('data.badges'))->pluck('key')->all();
        $this->assertContains(EventBadgeService::KEY_REGISTERED, $keys);
        $this->assertContains(EventBadgeService::KEY_NEW, $keys);
    }

    public function test_authenticated_player_can_show_edition_with_badges(): void
    {
        $player = Player::factory()->playerOnly()->create();
        $now = time();
        $event = PadelEvent::factory()->create([
            'date' => Carbon::now($this->tz)->addDays(14)->format('Y-m-d'),
            'inserted_at' => $now - 1800,
            'registration_allowed_until' => $now + 86400,
            'cancellation_allowed_until' => $now + 86400,
        ]);
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 16,
        ]);
        EventRegistration::factory()->forEvent($event)->paid()->create([
            'player_id' => $player->id_player,
            'event_division_id' => $division->id_event_division,
            'payment' => 55,
        ]);

        Sanctum::actingAs($player);

        $response = $this->getJson('/api/v1/app/events/' . $event->id_event);

        $response->assertOk()
            ->assertJsonPath('data.id_event', $event->id_event)
            ->assertJsonStructure(['data' => ['badges']]);

        $keys = collect($response->json('data.badges'))->pluck('key')->all();
        $this->assertContains(EventBadgeService::KEY_REGISTERED, $keys);
        $this->assertContains(EventBadgeService::KEY_NEW, $keys);
    }

    public function test_participated_filter_requires_authentication(): void
    {
        $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'participated' => 1,
        ]))
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Authentication required',
            ]);
    }

    public function test_registered_filter_returns_only_active_registrations(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $registered = PadelEvent::factory()->create(['date' => '2026-04-12']);
        $division = EventDivision::factory()->create(['event_id' => $registered->id_event]);
        EventRegistration::factory()->forEvent($registered)->create([
            'player_id' => $player->id_player,
            'event_division_id' => $division->id_event_division,
            'payment' => 55,
        ]);

        $other = PadelEvent::factory()->create(['date' => '2026-04-18']);
        EventDivision::factory()->create(['event_id' => $other->id_event]);

        $response = $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'registered' => 1,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_event', $registered->id_event);
    }

    public function test_participated_filter_returns_editions_with_player_participation(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $participated = PadelEvent::factory()->create(['date' => '2026-04-15']);
        $division = EventDivision::factory()->create([
            'event_id' => $participated->id_event,
            'division_id' => Division::factory()->create()->id_division,
        ]);
        EventParticipation::factory()->create([
            'event_id' => $participated->id_event,
            'event_division_id' => $division->id_event_division,
            'player_id' => $player->id_player,
        ]);

        PadelEvent::factory()->create(['date' => '2026-04-16']);

        $response = $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'participated' => true,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_event', $participated->id_event);
    }

    public function test_unpaid_filter_includes_unpaid_and_awaiting_proof(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $unpaid = PadelEvent::factory()->create(['date' => '2026-04-10']);
        $unpaidDivision = EventDivision::factory()->create(['event_id' => $unpaid->id_event]);
        EventRegistration::factory()->forEvent($unpaid)->create([
            'player_id' => $player->id_player,
            'event_division_id' => $unpaidDivision->id_event_division,
            'payment_status' => RegistrationPaymentStatus::Unpaid,
            'payment' => 55,
            'payment_due_at' => time() + 3600,
        ]);

        $awaiting = PadelEvent::factory()->create(['date' => '2026-04-11']);
        $awaitingDivision = EventDivision::factory()->create(['event_id' => $awaiting->id_event]);
        EventRegistration::factory()->forEvent($awaiting)->create([
            'player_id' => $player->id_player,
            'event_division_id' => $awaitingDivision->id_event_division,
            'payment_status' => RegistrationPaymentStatus::AwaitingProofReview,
            'payment' => 55,
            'payment_due_at' => time() + 3600,
        ]);

        $paid = PadelEvent::factory()->create(['date' => '2026-04-12']);
        $paidDivision = EventDivision::factory()->create(['event_id' => $paid->id_event]);
        EventRegistration::factory()->forEvent($paid)->paid()->create([
            'player_id' => $player->id_player,
            'event_division_id' => $paidDivision->id_event_division,
            'payment' => 55,
        ]);

        $response = $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'unpaid' => 1,
        ]));

        $response->assertOk()->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('id_event')->all();
        $this->assertContains($unpaid->id_event, $ids);
        $this->assertContains($awaiting->id_event, $ids);
        $this->assertNotContains($paid->id_event, $ids);
    }

    public function test_lottery_winner_filter_requires_accepted_draw(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $winnerEdition = PadelEvent::factory()->create(['date' => '2026-04-20']);
        $winnerDivision = EventDivision::factory()->create(['event_id' => $winnerEdition->id_event]);
        $participation = EventParticipation::factory()->create([
            'event_id' => $winnerEdition->id_event,
            'event_division_id' => $winnerDivision->id_event_division,
            'player_id' => $player->id_player,
        ]);
        EventLotteryLucky::factory()->create([
            'drawn_participation_id' => $participation->id_participation,
            'accepted_at' => time() - 30,
            'rejected_at' => null,
        ]);

        $pendingEdition = PadelEvent::factory()->create(['date' => '2026-04-21']);
        $pendingDivision = EventDivision::factory()->create(['event_id' => $pendingEdition->id_event]);
        $pendingParticipation = EventParticipation::factory()->create([
            'event_id' => $pendingEdition->id_event,
            'event_division_id' => $pendingDivision->id_event_division,
            'player_id' => $player->id_player,
        ]);
        EventLotteryLucky::factory()->create([
            'drawn_participation_id' => $pendingParticipation->id_participation,
            'accepted_at' => null,
            'rejected_at' => null,
        ]);

        $response = $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'lottery_winner' => 1,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_event', $winnerEdition->id_event);
    }

    public function test_club_ids_filter_limits_editions_by_location_club(): void
    {
        $clubA = Club::factory()->create(['name' => 'Alpha Club']);
        $clubB = Club::factory()->create(['name' => 'Beta Club']);
        $locationA = Location::factory()->forClub($clubA)->create();
        $locationB = Location::factory()->forClub($clubB)->create();

        $editionA = PadelEvent::factory()->forLocation($locationA)->create(['date' => '2026-04-14']);
        PadelEvent::factory()->forLocation($locationB)->create(['date' => '2026-04-15']);

        $response = $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'club_ids' => [$clubA->id_club],
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_event', $editionA->id_event);
    }

    public function test_authenticated_list_includes_badges(): void
    {
        $player = Player::factory()->playerOnly()->create();
        $now = time();
        $event = PadelEvent::factory()->create([
            'date' => Carbon::now($this->tz)->addDays(8)->format('Y-m-d'),
            'inserted_at' => $now - (72 * 3600),
            'registration_allowed_until' => $now + 86400,
            'cancellation_allowed_until' => $now + 86400,
        ]);
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 16,
        ]);
        EventRegistration::factory()->forEvent($event)->create([
            'player_id' => $player->id_player,
            'event_division_id' => $division->id_event_division,
            'payment_status' => RegistrationPaymentStatus::Unpaid,
            'payment' => 55,
            'payment_due_at' => $now + 3600,
        ]);

        Sanctum::actingAs($player);

        $date = $event->fresh()->formatted_date;
        $response = $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => $date,
            'date_to' => $date,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [['badges']]]);

        $keys = collect($response->json('data.0.badges'))->pluck('key')->all();
        $this->assertContains(EventBadgeService::KEY_PENDING_PAYMENT, $keys);
    }
}
