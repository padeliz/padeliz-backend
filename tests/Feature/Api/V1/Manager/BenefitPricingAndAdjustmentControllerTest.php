<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Enums\Benefit\BenefitAmountType;
use App\Enums\Benefit\BenefitScopeType;
use App\Enums\Registration\CheckoutItemStatus;
use App\Enums\Registration\CheckoutStatus;
use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\BenefitDefinition;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\EventRegistrationCheckout;
use App\Models\EventRegistrationCheckoutItem;
use App\Models\PadelEvent;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BenefitPricingAndAdjustmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private Player $player;

    private PadelEvent $event;

    private EventDivision $eventDivision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        $this->player = Player::factory()->create();
        $this->event = PadelEvent::factory()->create([
            'date' => now()->addDays(30)->format('Y-m-d'),
        ]);
        $division = Division::factory()->create();
        $this->eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'entry_fee' => 55,
        ]);

        BenefitDefinition::query()->firstOrCreate(
            ['slug' => 'manual_adjustment'],
            [
                'name' => 'Manual adjustment',
                'description' => 'Manager-only fixed RON reduction',
                'is_active' => true,
                'is_system' => true,
                'amount_type' => BenefitAmountType::FixedRon,
                'amount_value' => 0,
                'scope_type' => BenefitScopeType::Lifetime,
                'player_optional' => false,
                'exclusivity' => 'stackable',
                'priority' => 1000,
            ]
        );
    }

    public function test_pricing_preview_returns_quote(): void
    {
        Sanctum::actingAs($this->manager);

        BenefitDefinition::factory()->create([
            'slug' => 'early_bird_preview',
            'name' => 'Early bird',
            'is_active' => true,
            'amount_type' => BenefitAmountType::FixedRon,
            'amount_value' => 10,
            'scope_type' => BenefitScopeType::Lifetime,
            'player_optional' => false,
            'priority' => 40,
        ]);

        $response = $this->postJson('/api/v1/manager/pricing/preview', [
            'player_id' => $this->player->id_player,
            'event_id' => $this->event->id_event,
            'event_division_id' => $this->eventDivision->id_event_division,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.base_fee', 55)
            ->assertJsonStructure([
                'data' => [
                    'base_fee',
                    'amount_due',
                    'eligible',
                    'applied',
                ],
            ]);
    }

    public function test_manual_adjustment_applies_on_unpaid_only(): void
    {
        Sanctum::actingAs($this->manager);

        $registration = EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
            'payment' => 55,
            'lifecycle_status' => RegistrationLifecycleStatus::Active,
            'payment_status' => RegistrationPaymentStatus::Unpaid,
            'payment_due_at' => time() + 86400,
        ]);

        $checkout = EventRegistrationCheckout::query()->create([
            'event_id' => $this->event->id_event,
            'player_id' => $this->player->id_player,
            'status' => CheckoutStatus::Open,
            'total_amount' => 55,
        ]);

        EventRegistrationCheckoutItem::query()->create([
            'checkout_id' => $checkout->id_checkout,
            'registration_id' => $registration->id_registration,
            'base_amount_ron' => 55,
            'amount' => 55,
            'item_status' => CheckoutItemStatus::Active,
        ]);

        $this->postJson("/api/v1/manager/event-registrations/{$registration->id_registration}/manual-adjustment", [
            'amount_ron' => 15,
            'reason' => 'Courtesy discount',
        ])->assertStatus(200)
            ->assertJsonPath('data.redemption.amount_ron', 15)
            ->assertJsonPath('data.registration.amount_due', 40);

        $paid = EventRegistration::factory()->paid()->create([
            'event_id' => $this->event->id_event,
            'player_id' => Player::factory()->create()->id_player,
            'event_division_id' => $this->eventDivision->id_event_division,
            'payment' => 55,
        ]);

        $this->postJson("/api/v1/manager/event-registrations/{$paid->id_registration}/manual-adjustment", [
            'amount_ron' => 5,
            'reason' => 'Should fail',
        ])->assertStatus(422);
    }

    public function test_edition_division_requires_entry_fee_on_create(): void
    {
        Sanctum::actingAs($this->manager);
        $division = Division::factory()->create();

        $this->postJson('/api/v1/manager/event-divisions', [
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'players_limit' => 16,
            'playing_start_time' => '10:00',
            'playing_end_time' => '18:00',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['entry_fee']);

        $this->postJson('/api/v1/manager/event-divisions', [
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'players_limit' => 16,
            'entry_fee' => 60,
            'playing_start_time' => '10:00',
            'playing_end_time' => '18:00',
        ])->assertStatus(201)
            ->assertJsonPath('data.entry_fee', 60);
    }

    public function test_event_referral_settings_upsert(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/manager/events/{$this->event->id_event}/referral-settings")
            ->assertStatus(200)
            ->assertJsonPath('data.event_id', $this->event->id_event)
            ->assertJsonPath('data.code_ttl_hours', 24);

        $this->putJson("/api/v1/manager/events/{$this->event->id_event}/referral-settings", [
            'code_ttl_hours' => 48,
        ])->assertStatus(200)
            ->assertJsonPath('data.code_ttl_hours', 48);

        $this->getJson("/api/v1/manager/events/{$this->event->id_event}/referral-settings")
            ->assertStatus(200)
            ->assertJsonPath('data.code_ttl_hours', 48);
    }
}
