<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Enums\Registration\PaymentProofReviewStatus;
use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\EventRegistrationPaymentProof;
use App\Models\PadelEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

class EventRegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private PadelEvent $event;

    private EventDivision $eventDivision;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        $this->event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(now()->addDays(30)->format('Y-m-d')),
        ]);
        $division = Division::factory()->create();
        $this->eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'division_title' => $division->name,
            'players_limit' => 1,
            'entry_fee' => 55,
        ]);
        $this->player = User::factory()->create();
    }

    public function test_manager_can_index_show_store_update_patch_destroy(): void
    {
        Sanctum::actingAs($this->manager);

        $created = $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);
        $created->assertStatus(201)
            ->assertJsonPath('data.is_minor', false)
            ->assertJsonPath('data.is_student', false)
            ->assertJsonPath('data.payment', 55)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.lifecycle_status', 'active')
            ->assertJsonPath('data.payment_due_at', null);
        $id = $created->json('data.id_registration');

        $this->getJson('/api/v1/manager/event-registrations')->assertStatus(200)
            ->assertJsonMissingPath('data.0.player_history');

        $this->getJson("/api/v1/manager/event-registrations/{$id}")->assertStatus(200)
            ->assertJsonPath('data.id_registration', $id);

        $this->putJson("/api/v1/manager/event-registrations/{$id}", $this->putPayload([
            'payment' => 40,
        ]))->assertStatus(422);

        $this->patchJson("/api/v1/manager/event-registrations/{$id}", [
            'is_student' => true,
        ])->assertStatus(200)->assertJsonPath('data.is_student', true);

        $this->deleteJson("/api/v1/manager/event-registrations/{$id}")
            ->assertStatus(405);

        $this->postJson("/api/v1/manager/event-registrations/{$id}/cancel", [
            'cancel_reason' => 'administrative',
        ])->assertStatus(422);

        $this->postJson("/api/v1/manager/event-registrations/{$id}/cancel", [
            'cancel_reason' => 'personal_reasons',
        ])->assertStatus(200)->assertJsonPath('data.lifecycle_status', 'cancelled');
    }

    public function test_manager_bulk_delete_is_method_not_allowed(): void
    {
        Sanctum::actingAs($this->manager);
        $rows = EventRegistration::factory()->count(2)->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
            'is_student' => false,
            'payment' => 0,
        ]);

        $this->deleteJson('/api/v1/manager/event-registrations/bulk', [
            'registration_ids' => $rows->pluck('id_registration')->all(),
        ])->assertStatus(405);
    }

    public function test_pagination_and_auth(): void
    {
        EventRegistration::factory()->count(3)->create([
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
            'is_student' => false,
            'payment' => 0,
        ]);

        $this->getJson('/api/v1/manager/event-registrations')->assertStatus(401);

        $player = User::factory()->playerOnly()->create();
        Sanctum::actingAs($player);
        $this->getJson('/api/v1/manager/event-registrations')->assertStatus(403);

        Sanctum::actingAs($this->manager);
        $this->getJson('/api/v1/manager/event-registrations/99999')->assertStatus(404);
        $this->getJson('/api/v1/manager/event-registrations?per_page=2')
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_store_validation(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/event-registrations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_id', 'player_user_id', 'event_division_id']);

        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => 99999,
            'player_user_id' => 99999,
            'event_division_id' => 99999,
        ])->assertStatus(422);
    }

    public function test_store_rejects_null_and_omitted_edition_division(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => null,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_division_id']);

        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_division_id']);
    }

    public function test_index_filters_by_edition_player_and_division(): void
    {
        Sanctum::actingAs($this->manager);

        $created = $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);
        $created->assertStatus(201);
        $id = $created->json('data.id_registration');

        $byEdition = $this->getJson(
            '/api/v1/manager/event-registrations?filters[event_id]=' . $this->event->id_event
        );
        $byEdition->assertStatus(200);
        $this->assertNotNull(collect($byEdition->json('data'))->firstWhere('id_registration', $id));

        $byPlayer = $this->getJson(
            '/api/v1/manager/event-registrations?filters[player_user_id]=' . $this->player->id_user
        );
        $this->assertNotNull(collect($byPlayer->json('data'))->firstWhere('id_registration', $id));

        $byEd = $this->getJson(
            '/api/v1/manager/event-registrations?filters[event_division_id]=' . $this->eventDivision->id_event_division
        );
        $this->assertNotNull(collect($byEd->json('data'))->firstWhere('id_registration', $id));

        $second = $this->secondEventDivision();
        $byOther = $this->getJson(
            '/api/v1/manager/event-registrations?filters[event_division_id]=' . $second->id_event_division
        );
        $this->assertNull(collect($byOther->json('data'))->firstWhere('id_registration', $id));
    }

    public function test_store_rejects_edition_division_from_another_edition(): void
    {
        Sanctum::actingAs($this->manager);
        $other = EventDivision::factory()->create();

        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $other->id_event_division,
        ])->assertStatus(422);
    }

    public function test_xor_store_cases(): void
    {
        Sanctum::actingAs($this->manager);
        $second = $this->secondEventDivision();

        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);

        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(422);

        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $second->id_event_division,
        ])->assertStatus(201);

        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => null,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_division_id']);
    }

    public function test_same_player_can_register_on_two_different_events(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);

        $otherEdition = PadelEvent::factory()->create(UnixEventFactory::eventBounds(now()->addDays(40)->format('Y-m-d')));
        $otherDivision = EventDivision::factory()->create([
            'event_id' => $otherEdition->id_event,
            'players_limit' => 8,
            'entry_fee' => 55,
        ]);

        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $otherEdition->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $otherDivision->id_event_division,
        ])->assertStatus(201);
    }

    public function test_update_and_patch_require_division_and_enforce_xor(): void
    {
        Sanctum::actingAs($this->manager);
        $second = $this->secondEventDivision();

        $created = $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);
        $created->assertStatus(201);
        $id = $created->json('data.id_registration');

        $this->patchJson("/api/v1/manager/event-registrations/{$id}", [
            'is_minor' => true,
        ])->assertStatus(200)
            ->assertJsonPath('data.event_division_id', $this->eventDivision->id_event_division);

        $this->putJson("/api/v1/manager/event-registrations/{$id}", $this->putPayload([
            'event_division_id' => $second->id_event_division,
            'payment' => 55,
        ]))->assertStatus(200)
            ->assertJsonPath('data.event_division_id', $second->id_event_division);

        $this->patchJson("/api/v1/manager/event-registrations/{$id}", [
            'event_division_id' => null,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_division_id']);

        $this->patchJson("/api/v1/manager/event-registrations/{$id}", [
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(200);

        $secondRow = $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $second->id_event_division,
        ]);
        $secondRow->assertStatus(201);

        $this->putJson("/api/v1/manager/event-registrations/{$secondRow->json('data.id_registration')}", $this->putPayload([
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]))->assertStatus(422);
    }

    public function test_finalize_blocks_mutations_but_allows_index_show(): void
    {
        Sanctum::actingAs($this->manager);
        $registration = EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
            'is_student' => false,
            'payment' => 0,
        ]);
        $this->event->update(['finalized_at' => time()]);

        $this->getJson('/api/v1/manager/event-registrations')->assertStatus(200);
        $this->getJson("/api/v1/manager/event-registrations/{$registration->id_registration}")->assertStatus(200);

        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => User::factory()->create()->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(409);

        $this->putJson("/api/v1/manager/event-registrations/{$registration->id_registration}", $this->putPayload())
            ->assertStatus(409);
        $this->patchJson("/api/v1/manager/event-registrations/{$registration->id_registration}", ['payment' => 1])
            ->assertStatus(409);
        $this->deleteJson("/api/v1/manager/event-registrations/{$registration->id_registration}")
            ->assertStatus(405);
        $this->deleteJson('/api/v1/manager/event-registrations/bulk', [
            'registration_ids' => [$registration->id_registration],
        ])->assertStatus(405);
        $this->postJson("/api/v1/manager/event-registrations/{$registration->id_registration}/cancel", [
            'cancel_reason' => 'change_of_plans',
        ])->assertStatus(409);
    }

    public function test_manager_can_store_for_past_date_and_over_players_limit(): void
    {
        Sanctum::actingAs($this->manager);
        $bounds = \Tests\Support\UnixEventFactory::eventBounds(now()->subDay()->format('Y-m-d'));
        $this->event->update([
            'event_starts_at' => $bounds['event_starts_at'],
            'event_ends_at' => $bounds['event_ends_at'],
            'registration_allowed_until' => $bounds['registration_allowed_until'],
            'cancellation_allowed_until' => $bounds['cancellation_allowed_until'],
        ]);

        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);

        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => User::factory()->create()->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);
    }

    public function test_reorder_is_not_registered_and_index_omits_player_history(): void
    {
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/v1/manager/event-registrations', [
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201);

        $this->patchJson('/api/v1/manager/event-registrations/reorder', [])
            ->assertStatus(404);

        $index = $this->getJson(
            '/api/v1/manager/event-registrations?filters[event_id]=' . $this->event->id_event
        );
        $index->assertStatus(200);
        $this->assertArrayNotHasKey('player_history', $index->json('data.0'));
    }

    public function test_show_includes_payment_proofs_for_manager_review(): void
    {
        Sanctum::actingAs($this->manager);

        $registration = EventRegistration::query()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
            'is_minor' => false,
            'is_student' => false,
            'payment' => 50,
            'lifecycle_status' => RegistrationLifecycleStatus::Active,
            'payment_status' => RegistrationPaymentStatus::AwaitingProofReview,
        ]);
        $checkout = \App\Models\EventRegistrationCheckout::query()->create([
            'event_id' => $registration->event_id,
            'player_user_id' => $registration->player_user_id,
            'status' => \App\Enums\Registration\CheckoutStatus::AwaitingProof,
            'total_amount' => 50,
        ]);
        \App\Models\EventRegistrationCheckoutItem::query()->create([
            'checkout_id' => $checkout->id_checkout,
            'registration_id' => $registration->id_registration,
            'amount' => 50,
            'item_status' => 'active',
        ]);
        EventRegistrationPaymentProof::query()->create([
            'checkout_id' => $checkout->id_checkout,
            'proof_path' => 'registration-payment-proofs/1/proof.png',
            'review_status' => PaymentProofReviewStatus::Pending,
        ]);

        $this->getJson('/api/v1/manager/event-registrations/' . $registration->id_registration)
            ->assertStatus(200)
            ->assertJsonPath('data.payment_status', RegistrationPaymentStatus::AwaitingProofReview->value)
            ->assertJsonPath('data.amount_due', 50)
            ->assertJsonCount(1, 'data.payment_proofs')
            ->assertJsonPath('data.payment_proofs.0.review_status', PaymentProofReviewStatus::Pending->value);
    }

    public function test_edition_metadata_exposes_registration_and_cancellation_deadlines(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/manager/metadata/PadelEvent')
            ->assertStatus(200)
            ->assertJsonPath(
                'data.swagger_schema.properties.registration_allowed_until.type',
                'integer'
            )
            ->assertJsonPath(
                'data.swagger_schema.properties.cancellation_allowed_until.type',
                'integer'
            );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function putPayload(array $overrides = []): array
    {
        return array_merge([
            'event_id' => $this->event->id_event,
            'player_user_id' => $this->player->id_user,
            'event_division_id' => $this->eventDivision->id_event_division,
            'is_minor' => false,
            'is_student' => false,
            'payment' => 0,
        ], $overrides);
    }

    private function secondEventDivision(): EventDivision
    {
        return EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => Division::factory(),
            'players_limit' => 8,
            'entry_fee' => 55,
        ]);
    }
}
