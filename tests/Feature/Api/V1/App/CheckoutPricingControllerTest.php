<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Enums\Benefit\BenefitAmountType;
use App\Enums\Benefit\BenefitConditionType;
use App\Enums\Benefit\BenefitExclusivity;
use App\Enums\Benefit\BenefitScopeType;
use App\Enums\Referral\ReferralAttributionStatus;
use App\Models\BenefitCondition;
use App\Models\BenefitDefinition;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventReferralCode;
use App\Models\EventReferralCodeInvitee;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\UserEmail;
use App\Models\PlayerLevel;
use App\Models\ReferralTouchpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

class CheckoutPricingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $player;

    private PadelEvent $event;

    private EventDivision $eventDivision;

    private BenefitDefinition $referredBenefit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = User::factory()->playerOnly()->withPrimaryEmail()->create();
        $this->event = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(now()->addDays(30)->format('Y-m-d')),
        ]);
        $this->eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => Division::factory()->create()->id_division,
            'players_limit' => 10,
            'entry_fee' => 55,
        ]);
        PlayerLevel::factory()->level('4.50')->create(['player_user_id' => $this->player->id_user]);

        $this->referredBenefit = BenefitDefinition::factory()->create([
            'slug' => 'referred-test-' . uniqid(),
            'name' => 'Referred',
            'is_active' => true,
            'amount_type' => BenefitAmountType::FixedRon,
            'amount_value' => 10,
            'scope_type' => BenefitScopeType::Lifetime,
            'player_optional' => false,
            'exclusivity' => BenefitExclusivity::Stackable,
            'priority' => 20,
        ]);
        BenefitCondition::query()->create([
            'benefit_definition_id' => $this->referredBenefit->id_benefit,
            'condition_type' => BenefitConditionType::ReferrerSelected,
            'parameters' => [],
        ]);
        BenefitCondition::query()->create([
            'benefit_definition_id' => $this->referredBenefit->id_benefit,
            'condition_type' => BenefitConditionType::NoPriorPaidRegistrationInEdition,
            'parameters' => [],
        ]);
    }

    public function test_quote_returns_eligible_and_applied_per_line(): void
    {
        Sanctum::actingAs($this->player);
        $checkoutId = $this->createCheckout();

        $response = $this->getJson("/api/v1/app/registration-checkouts/{$checkoutId}/quote");

        $response->assertStatus(200)
            ->assertJsonPath('data.checkout_id', $checkoutId)
            ->assertJsonPath('data.referrer_candidates', [])
            ->assertJsonCount(1, 'data.lines')
            ->assertJsonPath('data.lines.0.base_amount_ron', 55)
            ->assertJsonPath('data.lines.0.quote.base_fee', 55)
            ->assertJsonPath('data.lines.0.quote.amount_due', 55);
    }

    public function test_benefit_selections_with_zero_referrer_candidates(): void
    {
        Sanctum::actingAs($this->player);
        $checkoutId = $this->createCheckout();

        $this->patchJson("/api/v1/app/registration-checkouts/{$checkoutId}/benefit-selections", [
            'selected_benefit_ids' => [],
        ])->assertStatus(200)
            ->assertJsonPath('data.items.0.base_amount_ron', 55)
            ->assertJsonPath('data.items.0.amount', 55)
            ->assertJsonPath('data.items.0.redemptions', []);

        $this->assertDatabaseMissing('event_referral_attributions', [
            'event_id' => $this->event->id_event,
            'referred_player_user_id' => $this->player->id_user,
        ]);
    }

    public function test_benefit_selections_auto_picks_single_referrer_candidate(): void
    {
        $referrer = $this->makeEligibleReferrer();
        $this->recordEligibleTouchpoint($referrer);

        Sanctum::actingAs($this->player);
        $checkoutId = $this->createCheckout();

        $this->patchJson("/api/v1/app/registration-checkouts/{$checkoutId}/benefit-selections", [
            'selected_benefit_ids' => [],
        ])->assertStatus(200)
            ->assertJsonPath('data.items.0.amount', 45);

        $this->assertDatabaseHas('event_referral_attributions', [
            'event_id' => $this->event->id_event,
            'referred_player_user_id' => $this->player->id_user,
            'referrer_player_user_id' => $referrer->id_user,
            'status' => ReferralAttributionStatus::Pending->value,
        ]);
    }

    public function test_benefit_selections_requires_referrer_when_two_or_more_candidates(): void
    {
        $referrerA = $this->makeEligibleReferrer();
        $referrerB = $this->makeEligibleReferrer();
        $this->recordEligibleTouchpoint($referrerA);
        $this->recordEligibleTouchpoint($referrerB);

        Sanctum::actingAs($this->player);
        $checkoutId = $this->createCheckout();

        $this->patchJson("/api/v1/app/registration-checkouts/{$checkoutId}/benefit-selections", [
            'selected_benefit_ids' => [],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['referrer_player_user_id']);

        $this->patchJson("/api/v1/app/registration-checkouts/{$checkoutId}/benefit-selections", [
            'selected_benefit_ids' => [],
            'referrer_player_user_id' => $referrerB->id_user,
        ])->assertStatus(200)
            ->assertJsonPath('data.items.0.amount', 45);

        $this->assertDatabaseHas('event_referral_attributions', [
            'event_id' => $this->event->id_event,
            'referred_player_user_id' => $this->player->id_user,
            'referrer_player_user_id' => $referrerB->id_user,
            'status' => ReferralAttributionStatus::Pending->value,
        ]);
    }

    public function test_quote_includes_referrer_candidates(): void
    {
        $referrer = $this->makeEligibleReferrer();
        $this->recordEligibleTouchpoint($referrer);

        Sanctum::actingAs($this->player);
        $checkoutId = $this->createCheckout();

        $this->getJson("/api/v1/app/registration-checkouts/{$checkoutId}/quote")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.referrer_candidates')
            ->assertJsonPath('data.referrer_candidates.0.referrer_player_user_id', $referrer->id_user);
    }

    private function createCheckout(): int
    {
        return (int) $this->postJson('/api/v1/app/event-registrations', [
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ])->assertStatus(201)->json('data.checkout_id');
    }

    private function makeEligibleReferrer(): User
    {
        $referrer = User::factory()->playerOnly()->create();
        $pastEdition = PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(now()->subDays(60)->format('Y-m-d')),
        ]);
        $pastDivision = EventDivision::factory()->create([
            'event_id' => $pastEdition->id_event,
        ]);
        \App\Models\EventParticipation::factory()->create([
            'event_id' => $pastEdition->id_event,
            'event_division_id' => $pastDivision->id_event_division,
            'player_user_id' => $referrer->id_user,
            'absence_reason' => null,
            'ban_reason' => null,
        ]);

        return $referrer;
    }

    private function recordEligibleTouchpoint(User $referrer): void
    {
        $email = UserEmail::query()->where('user_id', $this->player->id_user)->value('email');
        $code = EventReferralCode::factory()->create([
            'event_id' => $this->event->id_event,
            'referrer_player_user_id' => $referrer->id_user,
            'expires_at' => time() + 86400,
            'revoked_at' => null,
        ]);
        EventReferralCodeInvitee::query()->create([
            'referral_code_id' => $code->id_referral_code,
            'email' => $email,
            'phone' => null,
            'player_user_id' => $this->player->id_user,
        ]);
        ReferralTouchpoint::query()->create([
            'event_id' => $this->event->id_event,
            'referred_player_user_id' => $this->player->id_user,
            'referrer_player_user_id' => $referrer->id_user,
            'referral_code_id' => $code->id_referral_code,
            'discount_eligible' => true,
            'touched_at' => time(),
            'client_metadata' => null,
        ]);
    }
}
