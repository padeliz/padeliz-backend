<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\Club;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventLotteryLucky;
use App\Models\EventParticipation;
use App\Models\EventRegistration;
use App\Models\Location;
use App\Models\Organizer;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\PlayerLevel;
use App\Services\EventBadgeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\UnixEventFactory;
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
        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-05'));
        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-15'));
        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-25'));

        $response = $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-20',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');

        $startsAt = (int) $response->json('data.0.event_starts_at');
        $this->assertSame('2026-04-15', \App\Services\EventCalendarTiming::dayYmd($startsAt));
    }

    public function test_app_index_omits_description_and_includes_cover_organizer_badges(): void
    {
        $organizer = Organizer::factory()->create(['name' => 'Tour Org']);
        $date = Carbon::now($this->tz)->addDays(14)->format('Y-m-d');
        $event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds($date),
            'description' => '<p>Secret description</p>',
            'organizer_id' => $organizer->id_organizer,
            'inserted_at' => time() - 1800,
            'registration_allowed_until' => time() + 86400,
            'cancellation_allowed_until' => time() + 86400,
        ]);
        UnixEventFactory::withCover($event);

        $player = User::factory()->playerOnly()->create();
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
        $event = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-15'));

        $response = $this->getJson('/api/v1/app/events/' . $event->id_event)
            ->assertOk()
            ->assertJsonPath('data.id_event', $event->id_event);

        $this->assertSame(
            '2026-04-15',
            \App\Services\EventCalendarTiming::dayYmd((int) $response->json('data.event_starts_at'))
        );
    }

    public function test_app_show_includes_description_cover_organizer_and_badges(): void
    {
        $organizer = Organizer::factory()->create(['name' => 'Show Org']);
        $player = User::factory()->playerOnly()->create();
        $now = time();
        $event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(Carbon::now($this->tz)->addDays(14)->format('Y-m-d')),
            'description' => '<p>Show description</p>',
            'organizer_id' => $organizer->id_organizer,
            'inserted_at' => $now - 1800,
            'registration_allowed_until' => $now + 86400,
            'cancellation_allowed_until' => $now + 86400,
        ]);
        UnixEventFactory::withCover($event);
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 16,
        ]);
        EventRegistration::factory()->forEvent($event)->paid()->create([
            'player_user_id' => $player->id_user,
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
        $player = User::factory()->playerOnly()->create();
        $now = time();
        $event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(Carbon::now($this->tz)->addDays(14)->format('Y-m-d')),
            'inserted_at' => $now - 1800,
            'registration_allowed_until' => $now + 86400,
            'cancellation_allowed_until' => $now + 86400,
        ]);
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 16,
        ]);
        EventRegistration::factory()->forEvent($event)->paid()->create([
            'player_user_id' => $player->id_user,
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
        $player = User::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $registered = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-12'));
        $division = EventDivision::factory()->create(['event_id' => $registered->id_event]);
        EventRegistration::factory()->forEvent($registered)->create([
            'player_user_id' => $player->id_user,
            'event_division_id' => $division->id_event_division,
            'payment' => 55,
        ]);

        $other = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-18'));
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
        $player = User::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $participated = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-15'));
        $division = EventDivision::factory()->create([
            'event_id' => $participated->id_event,
            'division_id' => Division::factory()->create()->id_division,
        ]);
        EventParticipation::factory()->create([
            'event_id' => $participated->id_event,
            'event_division_id' => $division->id_event_division,
            'player_user_id' => $player->id_user,
        ]);

        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-16'));

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
        $player = User::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $unpaid = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-10'));
        $unpaidDivision = EventDivision::factory()->create(['event_id' => $unpaid->id_event]);
        EventRegistration::factory()->forEvent($unpaid)->create([
            'player_user_id' => $player->id_user,
            'event_division_id' => $unpaidDivision->id_event_division,
            'payment_status' => RegistrationPaymentStatus::Unpaid,
            'payment' => 55,
            'payment_due_at' => time() + 3600,
        ]);

        $awaiting = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-11'));
        $awaitingDivision = EventDivision::factory()->create(['event_id' => $awaiting->id_event]);
        EventRegistration::factory()->forEvent($awaiting)->create([
            'player_user_id' => $player->id_user,
            'event_division_id' => $awaitingDivision->id_event_division,
            'payment_status' => RegistrationPaymentStatus::AwaitingProofReview,
            'payment' => 55,
            'payment_due_at' => time() + 3600,
        ]);

        $paid = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-12'));
        $paidDivision = EventDivision::factory()->create(['event_id' => $paid->id_event]);
        EventRegistration::factory()->forEvent($paid)->paid()->create([
            'player_user_id' => $player->id_user,
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
        $player = User::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $winnerEdition = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-20'));
        $winnerDivision = EventDivision::factory()->create(['event_id' => $winnerEdition->id_event]);
        $participation = EventParticipation::factory()->create([
            'event_id' => $winnerEdition->id_event,
            'event_division_id' => $winnerDivision->id_event_division,
            'player_user_id' => $player->id_user,
        ]);
        EventLotteryLucky::factory()->create([
            'drawn_participation_id' => $participation->id_participation,
            'accepted_at' => time() - 30,
            'rejected_at' => null,
        ]);

        $pendingEdition = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-21'));
        $pendingDivision = EventDivision::factory()->create(['event_id' => $pendingEdition->id_event]);
        $pendingParticipation = EventParticipation::factory()->create([
            'event_id' => $pendingEdition->id_event,
            'event_division_id' => $pendingDivision->id_event_division,
            'player_user_id' => $player->id_user,
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

        $editionA = PadelEvent::factory()->forLocation($locationA)->create(UnixEventFactory::eventBounds('2026-04-14'));
        PadelEvent::factory()->forLocation($locationB)->create(UnixEventFactory::eventBounds('2026-04-15'));

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
        $player = User::factory()->playerOnly()->create();
        $now = time();
        $event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(Carbon::now($this->tz)->addDays(8)->format('Y-m-d')),
            'inserted_at' => $now - (72 * 3600),
            'registration_allowed_until' => $now + 86400,
            'cancellation_allowed_until' => $now + 86400,
        ]);
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 16,
        ]);
        EventRegistration::factory()->forEvent($event)->create([
            'player_user_id' => $player->id_user,
            'event_division_id' => $division->id_event_division,
            'payment_status' => RegistrationPaymentStatus::Unpaid,
            'payment' => 55,
            'payment_due_at' => $now + 3600,
        ]);

        Sanctum::actingAs($player);

        $date = \App\Services\EventCalendarTiming::dayYmd((int) $event->fresh()->event_starts_at);
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

    /**
     * @return array<string, mixed>  is_eligible keyed by division title
     */
    private function listEligibility(PadelEvent $event, ?User $player): array
    {
        if ($player !== null) {
            Sanctum::actingAs($player);
        }

        $date = \App\Services\EventCalendarTiming::dayYmd((int) $event->fresh()->event_starts_at);
        $response = $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => $date,
            'date_to' => $date,
        ]))->assertOk();

        return collect($response->json('data.0.event_divisions'))
            ->mapWithKeys(fn (array $row): array => [
                $row['division_title'] => array_key_exists('is_eligible', $row) ? $row['is_eligible'] : 'missing',
            ])
            ->all();
    }

    private function eventWithBoundedDivisions(): PadelEvent
    {
        $event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(Carbon::now($this->tz)->addDays(8)->format('Y-m-d')),
        ]);
        foreach ([
            'inside' => ['4.99', '4.00'],
            'too-strong' => ['3.99', '3.00'],
            'open' => [null, null],
        ] as $title => [$min, $max]) {
            EventDivision::factory()->create([
                'event_id' => $event->id_event,
                'division_id' => Division::factory()->create()->id_division,
                'division_title' => $title,
                'level_min' => $min,
                'level_max' => $max,
            ]);
        }

        return $event;
    }

    public function test_authenticated_list_attaches_per_division_is_eligible_like_show(): void
    {
        $event = $this->eventWithBoundedDivisions();
        $player = User::factory()->playerOnly()->create();
        PlayerLevel::factory()->level('4.50')->create(['player_user_id' => $player->id_user]);

        $this->assertSame(
            ['inside' => true, 'too-strong' => false, 'open' => true],
            $this->listEligibility($event, $player)
        );

        $show = $this->getJson('/api/v1/app/events/' . $event->id_event)->assertOk();
        $this->assertSame(
            ['inside' => true, 'too-strong' => false, 'open' => true],
            collect($show->json('data.event_divisions'))->pluck('is_eligible', 'division_title')->all()
        );
    }

    public function test_authenticated_list_marks_every_division_ineligible_without_a_level(): void
    {
        $event = $this->eventWithBoundedDivisions();
        $player = User::factory()->playerOnly()->create();

        $this->assertSame(
            ['inside' => false, 'too-strong' => false, 'open' => false],
            $this->listEligibility($event, $player)
        );
    }

    public function test_guest_list_does_not_include_is_eligible(): void
    {
        $event = $this->eventWithBoundedDivisions();

        $this->assertSame(
            ['inside' => 'missing', 'too-strong' => 'missing', 'open' => 'missing'],
            $this->listEligibility($event, null)
        );
    }

    /**
     * @return list<int>
     */
    private function sortedIds(string $query): array
    {
        $response = $this->getJson('/api/v1/app/events?' . $query)->assertOk();

        return array_map('intval', $response->json('data.*.id_event'));
    }

    public function test_list_defaults_to_event_starts_at_ascending(): void
    {
        $lateSameDay = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-12', '18:00:00'));
        $earlySameDay = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-12', '10:00:00'));
        $earlierDay = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-11', '20:00:00'));

        $ids = $this->sortedIds(http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]));

        $this->assertSame(
            [$earlierDay->id_event, $earlySameDay->id_event, $lateSameDay->id_event],
            $ids
        );
    }

    public function test_sort_asc_orders_event_starts_at_ascending(): void
    {
        $lateSameDay = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-12', '18:00:00'));
        $earlySameDay = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-12', '10:00:00'));
        $laterDay = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-13', '09:00:00'));

        $ids = $this->sortedIds(http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'sort' => 'asc',
        ]));

        $this->assertSame(
            [$earlySameDay->id_event, $lateSameDay->id_event, $laterDay->id_event],
            $ids
        );
    }

    public function test_sort_desc_orders_event_starts_at_descending_for_past_tab(): void
    {
        $lateSameDay = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-12', '18:00:00'));
        $earlySameDay = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-12', '10:00:00'));
        $laterDay = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-13', '09:00:00'));
        $earlierDay = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-11', '20:00:00'));

        $ids = $this->sortedIds(http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'sort' => 'desc',
        ]));

        $this->assertSame(
            [
                $laterDay->id_event,
                $lateSameDay->id_event,
                $earlySameDay->id_event,
                $earlierDay->id_event,
            ],
            $ids
        );
    }

    public function test_rejects_invalid_sort_value(): void
    {
        $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'sort' => 'sideways',
        ]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['sort']]);
    }

    public function test_show_has_participated_true_when_player_has_participation_row(): void
    {
        $player = User::factory()->playerOnly()->create();
        $event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(Carbon::now($this->tz)->subDays(5)->format('Y-m-d')),
        ]);
        $division = EventDivision::factory()->create(['event_id' => $event->id_event]);
        EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'player_user_id' => $player->id_user,
        ]);
        Sanctum::actingAs($player);

        $this->getJson('/api/v1/app/events/' . $event->id_event)
            ->assertOk()
            ->assertJsonPath('data.has_participated', true);
    }

    public function test_show_has_participated_false_for_paid_registration_without_participation(): void
    {
        $player = User::factory()->playerOnly()->create();
        $event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(Carbon::now($this->tz)->subDays(5)->format('Y-m-d')),
        ]);
        $division = EventDivision::factory()->create(['event_id' => $event->id_event]);
        EventRegistration::factory()->forEvent($event)->paid()->create([
            'player_user_id' => $player->id_user,
            'event_division_id' => $division->id_event_division,
            'payment' => 55,
        ]);
        Sanctum::actingAs($player);

        $this->getJson('/api/v1/app/events/' . $event->id_event)
            ->assertOk()
            ->assertJsonPath('data.has_participated', false);
    }

    public function test_show_has_participated_ignores_other_players_participation(): void
    {
        $player = User::factory()->playerOnly()->create();
        $other = User::factory()->playerOnly()->create();
        $event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(Carbon::now($this->tz)->subDays(5)->format('Y-m-d')),
        ]);
        $division = EventDivision::factory()->create(['event_id' => $event->id_event]);
        EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'player_user_id' => $other->id_user,
        ]);
        Sanctum::actingAs($player);

        $this->getJson('/api/v1/app/events/' . $event->id_event)
            ->assertOk()
            ->assertJsonPath('data.has_participated', false);
    }

    public function test_guest_show_has_participated_false(): void
    {
        $event = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-15'));

        $this->getJson('/api/v1/app/events/' . $event->id_event)
            ->assertOk()
            ->assertJsonPath('data.has_participated', false);
    }

    public function test_list_does_not_include_has_participated(): void
    {
        PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-04-15'));

        $response = $this->getJson('/api/v1/app/events?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]))->assertOk();

        $this->assertArrayNotHasKey('has_participated', $response->json('data.0'));
    }
}
