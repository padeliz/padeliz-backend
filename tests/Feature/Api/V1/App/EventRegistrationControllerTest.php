<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventParticipation;
use App\Models\EventRegistration;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\PlayerLevel;
use App\Models\PlayerStudentProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;
use Carbon\Carbon;

class EventRegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $player;

    protected PadelEvent $event;

    protected EventDivision $eventDivision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = User::factory()->playerOnly()->create();
        $this->event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(now()->addDays(30)->format('Y-m-d')),
        ]);
        $division = Division::factory()->create();
        $this->eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'division_title' => $division->name,
            'players_limit' => 10,
            'entry_fee' => 55,
        ]);

        PlayerLevel::factory()->level('4.50')->create(['player_user_id' => $this->player->id_user]);
    }

    public function test_store_rejects_when_registration_deadline_passed(): void
    {
        Sanctum::actingAs($this->player);
        $this->event->update([
            'registration_allowed_until' => time() - 10,
            'cancellation_allowed_until' => time() - 20,
        ]);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event']);
    }

    public function test_store_creates_registration_with_edition_division(): void
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Registration created',
                'data' => [
                    'player_user_id' => $this->player->id_user,
                    'event_id' => $this->event->id_event,
                    'event_division_id' => $this->eventDivision->id_event_division,
                    'is_minor' => false,
                    'is_student' => false,
                    'payment' => 55,
                ],
            ]);
        $this->assertNotNull($response->json('data.payment_due_at'));
        $this->assertDatabaseCount('event_participations', 0);
    }

    public function test_store_rejects_omitted_edition_division(): void
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_explicit_null_edition_division(): void
    {
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => null,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_division_id']);
    }

    public function test_store_ignores_player_id_in_body(): void
    {
        Sanctum::actingAs($this->player);
        $other = User::factory()->playerOnly()->create();

        $response = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $other->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.player_user_id', $this->player->id_user);
    }

    public function test_store_snapshots_division_entry_fee_and_marks_student_flag(): void
    {
        PlayerStudentProof::factory()->approved()->create(['player_user_id' => $this->player->id_user]);
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_student', true)
            ->assertJsonPath('data.payment', 55)
            ->assertJsonPath('data.amount_due', 55)
            ->assertJsonStructure(['data' => ['payment_due_at', 'is_overdue', 'redemptions']]);
    }

    public function test_store_does_not_create_or_change_participation(): void
    {
        EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);

        $this->assertDatabaseCount('event_participations', 1);
    }

    public function test_store_allows_when_registration_open_even_if_event_day_is_today(): void
    {
        Sanctum::actingAs($this->player);

        $tz = (string) config('app.timezone', 'Europe/Bucharest');
        $now = time();
        $startsAt = \Carbon\Carbon::now($tz)->setTime(18, 0)->timestamp;
        $endsAt = $startsAt + 3600;
        $this->event->update([
            'event_starts_at' => $startsAt,
            'event_ends_at' => $endsAt,
            'registration_allowed_until' => $now + 3600,
            'cancellation_allowed_until' => $now + 3600,
        ]);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);
    }

    public function test_store_allows_future_date_even_if_finalized(): void
    {
        $this->event->update(['finalized_at' => time()]);
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);
    }

    public function test_store_forbidden_without_player_level(): void
    {
        PlayerLevel::query()->where('player_user_id', $this->player->id_user)->delete();
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(403);
    }

    public function test_unauthenticated_post_and_delete_are_401(): void
    {
        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
        ])->assertStatus(401);

        $this->deleteJson('/api/v1/app/event-registrations/1', [
            'cancel_reason' => 'change_of_plans',
        ])->assertStatus(401);
    }

    public function test_store_requires_event_id(): void
    {
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/event-registrations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_id']);
    }

    public function test_store_rejects_edition_division_from_another_edition(): void
    {
        Sanctum::actingAs($this->player);
        $otherEd = EventDivision::factory()->create();

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $otherEd->id_event_division,
        ])->assertStatus(422);
    }

    public function test_xor_omitted_division_is_422(): void
    {
        Sanctum::actingAs($this->player);
        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_division_id']);
    }

    public function test_xor_null_when_specific_exists_is_422(): void
    {
        Sanctum::actingAs($this->player);
        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => null,
        ])->assertStatus(422);
    }

    public function test_xor_two_different_specific_event_divisions_are_allowed(): void
    {
        Sanctum::actingAs($this->player);
        $second = $this->secondEventDivision();

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $second->id_event_division,
        ])->assertStatus(201);
    }

    public function test_xor_same_specific_edition_division_is_422(): void
    {
        Sanctum::actingAs($this->player);
        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(422);
    }

    public function test_same_player_can_register_on_two_different_events(): void
    {
        Sanctum::actingAs($this->player);
        $otherEdition = PadelEvent::factory()->create(UnixEventFactory::eventBounds(now()->addDays(40)->format('Y-m-d')));
        $otherDivision = EventDivision::factory()->create([
            'event_id' => $otherEdition->id_event,
            'players_limit' => 10,
            'entry_fee' => 55,
        ]);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $otherEdition->id_event,
            'event_division_id' => $otherDivision->id_event_division,
        ])->assertStatus(201);
    }

    public function test_capacity_full_registrations_is_422(): void
    {
        $this->eventDivision->update(['players_limit' => 1]);
        EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_user_id' => User::factory(),
            'is_student' => false,
            'payment' => 55,
        ]);
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(422);
    }

    public function test_capacity_rejects_when_division_full_even_without_other_open_slots(): void
    {
        $this->eventDivision->update(['players_limit' => 1]);
        EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_user_id' => User::factory(),
            'is_student' => false,
            'payment' => 55,
        ]);
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(422);
    }

    public function test_capacity_ignores_full_participation_roster(): void
    {
        $this->eventDivision->update(['players_limit' => 1]);
        EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
            'player_user_id' => User::factory(),
        ]);
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);
    }

    public function test_destroy_own_registration_soft_cancels_and_removes_participation(): void
    {
        Sanctum::actingAs($this->player);
        $this->event->update([
            'registration_allowed_until' => time() + 3600,
            'cancellation_allowed_until' => time() + 3600,
        ]);
        $participation = EventParticipation::factory()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);
        $registration = EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
            'is_student' => false,
            'payment' => 55,
        ]);

        $this->deleteJson("/api/v1/app/event-registrations/{$registration->id_registration}", [
            'cancel_reason' => 'change_of_plans',
        ])
            ->assertStatus(200)
            ->assertJson(['message' => 'Registration cancelled'])
            ->assertJsonPath('data.lifecycle_status', 'cancelled');

        $this->assertDatabaseHas('event_registrations', [
            'id_registration' => $registration->id_registration,
            'lifecycle_status' => 'cancelled',
        ]);
        $this->assertDatabaseMissing('event_participations', [
            'id_participation' => $participation->id_participation,
        ]);
    }

    public function test_destroy_missing_is_404(): void
    {
        Sanctum::actingAs($this->player);

        $this->deleteJson('/api/v1/app/event-registrations/99999')->assertStatus(404);
    }

    public function test_destroy_other_players_registration_is_404(): void
    {
        $other = User::factory()->playerOnly()->create();
        $registration = EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $other->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
            'is_student' => false,
            'payment' => 55,
        ]);
        Sanctum::actingAs($this->player);

        $this->deleteJson("/api/v1/app/event-registrations/{$registration->id_registration}", [
            'cancel_reason' => 'change_of_plans',
        ])->assertStatus(404);
        $this->assertDatabaseHas('event_registrations', [
            'id_registration' => $registration->id_registration,
        ]);
    }

    public function test_destroy_rejects_without_cancel_window(): void
    {
        Sanctum::actingAs($this->player);
        $registration = EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
            'is_student' => false,
            'payment' => 55,
        ]);

        $this->event->update([
            'cancellation_allowed_until' => time() - 10,
            'registration_allowed_until' => time() + 3600,
        ]);
        $this->deleteJson("/api/v1/app/event-registrations/{$registration->id_registration}", [
            'cancel_reason' => 'change_of_plans',
        ])->assertStatus(422);
        $this->assertDatabaseHas('event_registrations', [
            'id_registration' => $registration->id_registration,
            'lifecycle_status' => 'active',
        ]);
    }

    public function test_destroy_allows_within_cancel_window_even_if_finalized(): void
    {
        Sanctum::actingAs($this->player);
        $registration = EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
            'is_student' => false,
            'payment' => 55,
        ]);
        $this->event->update([
            'finalized_at' => time(),
            'registration_allowed_until' => time() + 3600,
            'cancellation_allowed_until' => time() + 3600,
        ]);

        $this->deleteJson("/api/v1/app/event-registrations/{$registration->id_registration}", [
            'cancel_reason' => 'personal_reasons',
        ])->assertStatus(200)->assertJsonPath('data.lifecycle_status', 'cancelled');
    }

    public function test_old_participate_and_withdraw_are_unmatched(): void
    {
        Sanctum::actingAs($this->player);

        $this->postJson("/api/v1/app/events/{$this->event->id_event}/participate", [
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(404);

        $this->deleteJson("/api/v1/app/events/{$this->event->id_event}/withdraw")
            ->assertStatus(404);
    }

    public function test_app_get_patch_put_must_not_succeed(): void
    {
        Sanctum::actingAs($this->player);
        $registration = EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
            'is_student' => false,
            'payment' => 55,
        ]);

        foreach ([
            $this->getJson('/api/v1/app/event-registrations'),
            $this->getJson("/api/v1/app/event-registrations/{$registration->id_registration}"),
            $this->patchJson("/api/v1/app/event-registrations/{$registration->id_registration}", []),
            $this->putJson("/api/v1/app/event-registrations/{$registration->id_registration}", []),
        ] as $response) {
            $this->assertContains($response->status(), [404, 405]);
        }
    }

    public function test_index_embeds_event_divisions_on_edition(): void
    {
        Sanctum::actingAs($this->player);
        EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
            'is_student' => false,
            'payment' => 55,
        ]);

        $response = $this->getJson('/api/v1/app/my/event-registrations');

        $response->assertStatus(200);
        $event = $response->json('data.0.event');
        $this->assertIsArray($event);
        $this->assertArrayHasKey('event_divisions', $event);
        $this->assertNotEmpty($event['event_divisions']);
        $this->assertSame(
            $this->eventDivision->id_event_division,
            $event['event_divisions'][0]['id_event_division']
        );
        $this->assertArrayHasKey('entry_fee', $event['event_divisions'][0]);
        $this->assertArrayNotHasKey('entry_fee', $event);
        $this->assertArrayNotHasKey('students_entry_fee', $event);
    }

    public function test_store_embeds_event_divisions_on_edition(): void
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response->assertStatus(201);
        $event = $response->json('data.event');
        $this->assertIsArray($event);
        $this->assertArrayHasKey('event_divisions', $event);
        $this->assertNotEmpty($event['event_divisions']);
        $this->assertSame(
            $this->eventDivision->id_event_division,
            $event['event_divisions'][0]['id_event_division']
        );
        $this->assertSame(55, $event['event_divisions'][0]['entry_fee']);
        $this->assertArrayNotHasKey('entry_fee', $event);
        $this->assertArrayNotHasKey('students_entry_fee', $event);
    }

    private function secondEventDivision(): EventDivision
    {
        return EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => Division::factory(),
            'players_limit' => 10,
        ]);
    }
}
