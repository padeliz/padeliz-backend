<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\PadelEvent;
use App\Models\EventDivision;
use App\Models\EventParticipation;
use App\Models\EventReferralCode;
use App\Models\Player;
use App\Models\PlayerEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralCodeControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $referrer;

    private PadelEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->referrer = Player::factory()->playerOnly()->create();
        $this->event = PadelEvent::factory()->create([
            'date' => now()->addDays(25)->format('Y-m-d'),
        ]);
        $pastEdition = PadelEvent::factory()->create([
            'date' => now()->subDays(50)->format('Y-m-d'),
        ]);
        EventParticipation::factory()->create([
            'event_id' => $pastEdition->id_event,
            'event_division_id' => EventDivision::factory()->create([
                'event_id' => $pastEdition->id_event,
            ])->id_event_division,
            'player_id' => $this->referrer->id_player,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);
    }

    public function test_store_creates_code_with_allowlisted_invitees(): void
    {
        $invitee = Player::factory()->playerOnly()->create();
        $email = PlayerEmail::query()->where('player_id', $invitee->id_player)->value('email');

        Sanctum::actingAs($this->referrer);

        $response = $this->postJson("/api/v1/app/events/{$this->event->id_event}/referral-codes", [
            'invitees' => [
                ['email' => $email],
                ['player_id' => Player::factory()->playerOnly()->create()->id_player],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.remaining_slots', 6)
            ->assertJsonPath('data.code.event_id', $this->event->id_event)
            ->assertJsonPath('data.code.referrer_player_id', $this->referrer->id_player)
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
            'referrer_player_id' => $this->referrer->id_player,
        ]);
        EventReferralCode::factory()->create([
            'event_id' => $this->event->id_event,
            'referrer_player_id' => Player::factory()->playerOnly()->create()->id_player,
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
            'referrer_player_id' => $this->referrer->id_player,
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
        $newbie = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($newbie);

        $this->postJson("/api/v1/app/events/{$this->event->id_event}/referral-codes", [
            'invitees' => [['email' => 'someone@example.test']],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['invitees'])
            ->assertJsonPath('errors.remaining_slots.0', 6);
    }
}
