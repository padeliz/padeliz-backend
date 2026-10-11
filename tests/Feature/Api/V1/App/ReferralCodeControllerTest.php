<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\EventDivision;
use App\Models\EventParticipation;
use App\Models\EventReferralCode;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

class ReferralCodeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $referrer;

    private PadelEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->referrer = User::factory()->playerOnly()->create();
        $this->event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(now()->addDays(25)->format('Y-m-d')),
        ]);
        $pastEdition = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(now()->subDays(50)->format('Y-m-d')),
        ]);
        EventParticipation::factory()->create([
            'event_id' => $pastEdition->id_event,
            'event_division_id' => EventDivision::factory()->create([
                'event_id' => $pastEdition->id_event,
            ])->id_event_division,
            'player_user_id' => $this->referrer->id_user,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);
    }

    public function test_store_creates_code_with_allowlisted_invitees(): void
    {
        $invitee = User::factory()->playerOnly()->withPrimaryEmail()->create();
        $email = UserEmail::query()->where('user_id', $invitee->id_user)->value('email');

        Sanctum::actingAs($this->referrer);

        $response = $this->postJson("/api/v1/app/events/{$this->event->id_event}/referral-codes", [
            'invitees' => [
                ['email' => $email],
                ['player_user_id' => User::factory()->playerOnly()->withContacts()->create()->id_user],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.remaining_slots', 6)
            ->assertJsonPath('data.code.event_id', $this->event->id_event)
            ->assertJsonPath('data.code.referrer_player_user_id', $this->referrer->id_user)
            ->assertJsonCount(2, 'data.code.invitees');

        $this->assertDatabaseCount('event_referral_codes', 1);
        $this->assertDatabaseCount('event_referral_code_invitees', 2);
    }

    public function test_store_rejects_empty_invitees_and_includes_remaining_slots(): void
    {
        Sanctum::actingAs($this->referrer);

        $this->postJson("/api/v1/app/events/{$this->event->id_event}/referral-codes", [
            'invitees' => [],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['invitees']);
    }

    public function test_index_lists_own_codes_with_remaining_slots(): void
    {
        EventReferralCode::factory()->create([
            'event_id' => $this->event->id_event,
            'referrer_player_user_id' => $this->referrer->id_user,
        ]);
        EventReferralCode::factory()->create([
            'event_id' => $this->event->id_event,
            'referrer_player_user_id' => User::factory()->playerOnly()->create()->id_user,
        ]);

        Sanctum::actingAs($this->referrer);

        $this->getJson("/api/v1/app/events/{$this->event->id_event}/referral-codes")
            ->assertStatus(200)
            ->assertJsonPath('data.remaining_slots', 6)
            ->assertJsonCount(1, 'data.codes');
    }

    public function test_revoke_marks_code_revoked(): void
    {
        $code = EventReferralCode::factory()->create([
            'event_id' => $this->event->id_event,
            'referrer_player_user_id' => $this->referrer->id_user,
            'revoked_at' => null,
        ]);

        Sanctum::actingAs($this->referrer);

        $this->postJson("/api/v1/app/referral-codes/{$code->id_referral_code}/revoke")
            ->assertStatus(200)
            ->assertJsonPath('data.remaining_slots', 6);

        $this->assertNotNull($code->fresh()->revoked_at);
    }

    public function test_store_requires_clean_participation(): void
    {
        $newbie = User::factory()->playerOnly()->create();
        Sanctum::actingAs($newbie);

        $this->postJson("/api/v1/app/events/{$this->event->id_event}/referral-codes", [
            'invitees' => [['email' => 'someone@example.test']],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['invitees'])
            ->assertJsonPath('errors.remaining_slots.0', 6);
    }

    public function test_store_rejects_after_registration_closes(): void
    {
        $this->event->forceFill(['registration_allowed_until' => time() - 60])->save();

        Sanctum::actingAs($this->referrer);

        $this->postJson("/api/v1/app/events/{$this->event->id_event}/referral-codes", [
            'invitees' => [['email' => 'late@example.test']],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event']);
    }

    public function test_revoke_rejects_after_event_has_started(): void
    {
        $this->event->forceFill(['event_starts_at' => time() - 60])->save();

        $code = EventReferralCode::factory()->create([
            'event_id' => $this->event->id_event,
            'referrer_player_user_id' => $this->referrer->id_user,
            'revoked_at' => null,
        ]);

        Sanctum::actingAs($this->referrer);

        $this->postJson("/api/v1/app/referral-codes/{$code->id_referral_code}/revoke")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event']);

        $this->assertNull($code->fresh()->revoked_at);
    }
}
