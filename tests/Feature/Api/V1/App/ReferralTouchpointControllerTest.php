<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\EventDivision;
use App\Models\EventParticipation;
use App\Models\EventReferralCode;
use App\Models\EventReferralCodeInvitee;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

class ReferralTouchpointControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_records_touchpoint_for_allowlisted_player(): void
    {
        $event = PadelEvent::factory()->create(UnixEventFactory::eventBounds(now()->addDays(20)->format('Y-m-d')));
        $referrer = User::factory()->playerOnly()->create();
        $pastEdition = PadelEvent::factory()->create(UnixEventFactory::eventBounds(now()->subDays(40)->format('Y-m-d')));
        EventParticipation::factory()->create([
            'event_id' => $pastEdition->id_event,
            'event_division_id' => EventDivision::factory()->create([
                'event_id' => $pastEdition->id_event,
            ])->id_event_division,
            'player_user_id' => $referrer->id_user,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);

        $invitee = User::factory()->playerOnly()->withPrimaryEmail()->create();
        $email = UserEmail::query()->where('user_id', $invitee->id_user)->value('email');
        $code = EventReferralCode::factory()->create([
            'event_id' => $event->id_event,
            'referrer_player_user_id' => $referrer->id_user,
            'expires_at' => time() + 86400,
        ]);
        EventReferralCodeInvitee::query()->create([
            'referral_code_id' => $code->id_referral_code,
            'email' => $email,
            'phone' => null,
            'player_user_id' => $invitee->id_user,
        ]);

        Sanctum::actingAs($invitee);

        $this->postJson('/api/v1/app/referral-touchpoints', [
            'event_id' => $event->id_event,
            'code' => $code->code,
            'client_metadata' => ['source' => 'deep_link'],
        ])->assertStatus(201)
            ->assertJsonPath('data.event_id', $event->id_event)
            ->assertJsonPath('data.referred_player_user_id', $invitee->id_user)
            ->assertJsonPath('data.referrer_player_user_id', $referrer->id_user)
            ->assertJsonPath('data.discount_eligible', true);

        $this->assertDatabaseHas('referral_touchpoints', [
            'event_id' => $event->id_event,
            'referred_player_user_id' => $invitee->id_user,
            'referrer_player_user_id' => $referrer->id_user,
            'discount_eligible' => 1,
        ]);
    }

    public function test_store_rejects_unknown_code(): void
    {
        $event = PadelEvent::factory()->create(UnixEventFactory::eventBounds(now()->addDays(20)->format('Y-m-d')));
        $player = User::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $this->postJson('/api/v1/app/referral-touchpoints', [
            'event_id' => $event->id_event,
            'code' => 'NOPECODE',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_store_rejects_self_referral(): void
    {
        $event = PadelEvent::factory()->create(UnixEventFactory::eventBounds(now()->addDays(20)->format('Y-m-d')));
        $referrer = User::factory()->playerOnly()->create();
        $pastEdition = PadelEvent::factory()->create(UnixEventFactory::eventBounds(now()->subDays(40)->format('Y-m-d')));
        EventParticipation::factory()->create([
            'event_id' => $pastEdition->id_event,
            'event_division_id' => EventDivision::factory()->create([
                'event_id' => $pastEdition->id_event,
            ])->id_event_division,
            'player_user_id' => $referrer->id_user,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);
        $code = EventReferralCode::factory()->create([
            'event_id' => $event->id_event,
            'referrer_player_user_id' => $referrer->id_user,
            'expires_at' => time() + 86400,
        ]);

        Sanctum::actingAs($referrer);

        $this->postJson('/api/v1/app/referral-touchpoints', [
            'event_id' => $event->id_event,
            'code' => $code->code,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }
}
