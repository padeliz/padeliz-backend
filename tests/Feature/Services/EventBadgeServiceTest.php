<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\EventDivision;
use App\Models\EventLotteryLucky;
use App\Models\EventParticipation;
use App\Models\EventRegistration;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\PlayerLevelAssessment;
use App\Services\EventBadgeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

final class EventBadgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventBadgeService $service;

    private int $now;

    private string $tz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EventBadgeService();
        $this->now = time();
        $this->tz = (string) config('app.timezone', 'Europe/Bucharest');
    }

    public function test_pending_payment_for_unpaid_with_amount_due(): void
    {
        $player = User::factory()->playerOnly()->create();
        $event = $this->upcomingEdition(['inserted_at' => $this->now - (72 * 3600)]);
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 16,
        ]);

        EventRegistration::factory()->forEvent($event)->create([
            'player_user_id' => $player->id_user,
            'event_division_id' => $division->id_event_division,
            'payment_status' => RegistrationPaymentStatus::Unpaid,
            'payment' => 55,
            'payment_due_at' => $this->now + 3600,
        ]);

        $badges = $this->badgesFor($event, $player);

        $this->assertSame(
            [['key' => EventBadgeService::KEY_PENDING_PAYMENT, 'label' => 'Pending Payment']],
            $badges
        );
    }

    public function test_pending_payment_for_overdue_registration(): void
    {
        $player = User::factory()->playerOnly()->create();
        $event = $this->upcomingEdition(['inserted_at' => $this->now - (72 * 3600)]);
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 16,
        ]);

        EventRegistration::factory()->forEvent($event)->create([
            'player_user_id' => $player->id_user,
            'event_division_id' => $division->id_event_division,
            'payment_status' => RegistrationPaymentStatus::AwaitingProofReview,
            'payment' => 0,
            'payment_due_at' => $this->now - 10,
        ]);

        $badges = $this->badgesFor($event, $player);

        $this->assertSame(
            [['key' => EventBadgeService::KEY_PENDING_PAYMENT, 'label' => 'Pending Payment']],
            $badges
        );
    }

    public function test_registration_pending_on_upcoming_omitted_on_past(): void
    {
        $player = User::factory()->playerOnly()->create();
        $upcoming = $this->upcomingEdition(['inserted_at' => $this->now - (72 * 3600)]);
        $past = $this->pastEdition(['inserted_at' => $this->now - (72 * 3600)]);

        foreach ([$upcoming, $past] as $event) {
            $division = EventDivision::factory()->create([
                'event_id' => $event->id_event,
                'players_limit' => 16,
            ]);
            EventRegistration::factory()->forEvent($event)->create([
                'player_user_id' => $player->id_user,
                'event_division_id' => $division->id_event_division,
                'payment_status' => RegistrationPaymentStatus::AwaitingProofReview,
                'payment' => 55,
                'payment_due_at' => $this->now + 3600,
            ]);
        }

        $this->assertSame(
            [['key' => EventBadgeService::KEY_REGISTRATION_PENDING, 'label' => 'Registration Pending']],
            $this->badgesFor($upcoming, $player)
        );
        $this->assertSame([], $this->badgesFor($past, $player));
    }

    public function test_level_assessment_pending_only_when_pending_and_ready_for_review(): void
    {
        $player = User::factory()->playerOnly()->create();
        $event = $this->upcomingEdition(['inserted_at' => $this->now - (72 * 3600)]);
        EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 16,
        ]);

        $draft = PlayerLevelAssessment::factory()->forPlayer($player)->create();
        $this->assertSame([], $this->badgesFor($event, $player));

        $draft->delete();
        PlayerLevelAssessment::factory()->forPlayer($player)->approved()->create();
        $this->assertSame([], $this->badgesFor($event, $player));

        PlayerLevelAssessment::query()->where('player_user_id', $player->id_user)->delete();
        PlayerLevelAssessment::factory()->forPlayer($player)->rejected()->create();
        $this->assertSame([], $this->badgesFor($event, $player));

        PlayerLevelAssessment::query()->where('player_user_id', $player->id_user)->delete();
        PlayerLevelAssessment::factory()->forPlayer($player)->readyForReview()->create();
        $this->assertSame(
            [['key' => EventBadgeService::KEY_LEVEL_ASSESSMENT_PENDING, 'label' => 'Level Assessment Pending']],
            $this->badgesFor($event, $player)
        );
    }

    public function test_level_assessment_pending_omitted_on_past_edition(): void
    {
        $player = User::factory()->playerOnly()->create();
        $past = $this->pastEdition(['inserted_at' => $this->now - (72 * 3600)]);
        EventDivision::factory()->create([
            'event_id' => $past->id_event,
            'players_limit' => 16,
        ]);

        PlayerLevelAssessment::factory()->forPlayer($player)->readyForReview()->create();

        $this->assertSame([], $this->badgesFor($past, $player));
    }

    public function test_registered_vs_participated_by_date(): void
    {
        $player = User::factory()->playerOnly()->create();
        $upcoming = $this->upcomingEdition(['inserted_at' => $this->now - (72 * 3600)]);
        $past = $this->pastEdition(['inserted_at' => $this->now - (72 * 3600)]);

        foreach ([$upcoming, $past] as $event) {
            $division = EventDivision::factory()->create([
                'event_id' => $event->id_event,
                'players_limit' => 16,
            ]);
            EventRegistration::factory()->forEvent($event)->paid()->create([
                'player_user_id' => $player->id_user,
                'event_division_id' => $division->id_event_division,
                'payment' => 55,
            ]);
        }

        $this->assertSame(
            [['key' => EventBadgeService::KEY_REGISTERED, 'label' => 'Registered']],
            $this->badgesFor($upcoming, $player)
        );
        $this->assertSame(
            [['key' => EventBadgeService::KEY_PARTICIPATED, 'label' => 'Participated']],
            $this->badgesFor($past, $player)
        );
    }

    public function test_lottery_winner_when_accepted_at_set(): void
    {
        $player = User::factory()->playerOnly()->create();
        $event = $this->upcomingEdition(['inserted_at' => $this->now - (72 * 3600)]);
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 16,
        ]);
        $participation = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'player_user_id' => $player->id_user,
        ]);

        EventLotteryLucky::factory()->create([
            'drawn_participation_id' => $participation->id_participation,
            'accepted_at' => $this->now - 60,
            'rejected_at' => null,
        ]);

        $keys = array_column($this->badgesFor($event, $player), 'key');
        $this->assertContains(EventBadgeService::KEY_LOTTERY_WINNER, $keys);

        EventLotteryLucky::query()->delete();
        EventLotteryLucky::factory()->create([
            'drawn_participation_id' => $participation->id_participation,
            'accepted_at' => null,
            'rejected_at' => null,
        ]);

        $keysWithoutAccept = array_column($this->badgesFor($event, $player), 'key');
        $this->assertNotContains(EventBadgeService::KEY_LOTTERY_WINNER, $keysWithoutAccept);
    }

    public function test_new_badge_within_48h_upcoming_with_open_registration_window(): void
    {
        $event = $this->upcomingEdition([
            'inserted_at' => $this->now - 3600,
            'registration_allowed_until' => $this->now + 86400,
            'cancellation_allowed_until' => $this->now + 86400,
        ]);
        EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 16,
        ]);

        $keys = array_column($this->badgesFor($event, null), 'key');
        $this->assertContains(EventBadgeService::KEY_NEW, $keys);

        $old = $this->upcomingEdition([
            'inserted_at' => $this->now - (49 * 3600),
            'registration_allowed_until' => $this->now + 86400,
            'cancellation_allowed_until' => $this->now + 86400,
        ]);
        EventDivision::factory()->create([
            'event_id' => $old->id_event,
            'players_limit' => 16,
        ]);
        $this->assertNotContains(
            EventBadgeService::KEY_NEW,
            array_column($this->badgesFor($old, null), 'key')
        );
    }

    public function test_few_spots_when_remaining_at_most_25_percent(): void
    {
        $event = $this->upcomingEdition([
            'inserted_at' => $this->now - (72 * 3600),
            'registration_allowed_until' => $this->now + 86400,
            'cancellation_allowed_until' => $this->now + 86400,
        ]);
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 8,
        ]);

        for ($i = 0; $i < 6; $i++) {
            EventParticipation::factory()->create([
                'event_id' => $event->id_event,
                'event_division_id' => $division->id_event_division,
                'player_user_id' => User::factory()->playerOnly()->create()->id_user,
            ]);
        }

        $event->load(['divisions', 'participations']);
        $event->loadCount('participations');

        $keys = array_column($this->badgesFor($event, null), 'key');
        $this->assertContains(EventBadgeService::KEY_FEW_SPOTS, $keys);
    }

    public function test_spots_total_zero_does_not_yield_few_spots(): void
    {
        $event = $this->upcomingEdition([
            'inserted_at' => $this->now - (72 * 3600),
            'registration_allowed_until' => $this->now + 86400,
            'cancellation_allowed_until' => $this->now + 86400,
        ]);
        EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 0,
        ]);
        $event->load(['divisions', 'participations']);
        $event->loadCount('participations');

        $keys = array_column($this->badgesFor($event, null), 'key');
        $this->assertNotContains(EventBadgeService::KEY_FEW_SPOTS, $keys);
    }

    public function test_past_registration_until_blocks_new_and_few_spots(): void
    {
        $event = $this->upcomingEdition([
            'inserted_at' => $this->now - 1800,
            'registration_allowed_until' => $this->now - 10,
            'cancellation_allowed_until' => $this->now - 10,
        ]);
        $division = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 4,
        ]);
        for ($i = 0; $i < 3; $i++) {
            EventParticipation::factory()->create([
                'event_id' => $event->id_event,
                'event_division_id' => $division->id_event_division,
                'player_user_id' => User::factory()->playerOnly()->create()->id_user,
            ]);
        }
        $event->load(['divisions', 'participations']);
        $event->loadCount('participations');

        $keys = array_column($this->badgesFor($event, null), 'key');
        $this->assertNotContains(EventBadgeService::KEY_NEW, $keys);
        $this->assertNotContains(EventBadgeService::KEY_FEW_SPOTS, $keys);
    }

    public function test_guest_receives_no_player_relative_badges(): void
    {
        $event = $this->pastEdition(['inserted_at' => $this->now - (72 * 3600)]);
        EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'players_limit' => 16,
        ]);

        $this->assertSame([], $this->badgesFor($event, null));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function upcomingEdition(array $overrides = []): PadelEvent
    {
        return PadelEvent::factory()->create(array_merge([
            ...UnixEventFactory::eventBounds(Carbon::now($this->tz)->addDays(10)->format('Y-m-d')),
            'registration_allowed_until' => $this->now + 86400,
            'cancellation_allowed_until' => $this->now + 86400,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pastEdition(array $overrides = []): PadelEvent
    {
        return PadelEvent::factory()->create(array_merge([
            ...UnixEventFactory::eventBounds(Carbon::now($this->tz)->subDays(10)->format('Y-m-d')),
            'registration_allowed_until' => $this->now - 86400,
            'cancellation_allowed_until' => $this->now - 86400,
        ], $overrides));
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function badgesFor(PadelEvent $event, ?User $player): array
    {
        $result = $this->service->forEvents(new Collection([$event]), $player, $this->now);

        return $result[(int) $event->id_event] ?? [];
    }
}
