<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Enums\Registration\CheckoutItemStatus;
use App\Enums\Registration\CheckoutStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\Club;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\EventRegistrationCheckout;
use App\Models\EventRegistrationCheckoutItem;
use App\Models\Location;
use App\Models\PadelEvent;
use App\Models\User;
use App\Services\Pricing\BenefitDiscoveryService;
use App\Services\Pricing\PricingContext;
use App\Services\Pricing\PricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

abstract class PricingTestCase extends TestCase
{
    use RefreshDatabase;

    protected PricingService $pricing;

    protected BenefitDiscoveryService $discovery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discovery = new BenefitDiscoveryService();
        $this->pricing = new PricingService($this->discovery);
    }

    /**
     * @return array{
     *   player: Player,
     *   event: PadelEvent,
     *   eventDivision: EventDivision,
     *   registration: EventRegistration,
     *   checkout: EventRegistrationCheckout,
     *   item: EventRegistrationCheckoutItem,
     *   club: Club,
     *   location: Location
     * }
     */
    protected function seedRegistrationFixture(
        int $payment = 100,
        ?string $editionDate = null,
        RegistrationPaymentStatus $paymentStatus = RegistrationPaymentStatus::Unpaid,
        ?Club $club = null,
    ): array {
        $club ??= Club::factory()->create();
        $location = Location::factory()->forClub($club)->create();
        $event = PadelEvent::factory()->forLocation($location)->create([
            ...UnixEventFactory::eventBounds($editionDate ?? Carbon::now()->addDays(14)->format('Y-m-d')),
        ]);
        $eventDivision = EventDivision::factory()->create([
            'event_id' => $event->id_event,
            'entry_fee' => $payment,
        ]);
        $player = User::factory()->create();
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $eventDivision->id_event_division,
            'player_user_id' => $player->id_user,
            'payment' => $payment,
            'payment_status' => $paymentStatus,
            'is_student' => false,
        ]);
        $checkout = EventRegistrationCheckout::query()->create([
            'event_id' => $event->id_event,
            'player_user_id' => $player->id_user,
            'status' => CheckoutStatus::Open,
            'total_amount' => $payment,
            'payment_method' => null,
            'paid_at' => null,
        ]);
        $item = EventRegistrationCheckoutItem::query()->create([
            'checkout_id' => $checkout->id_checkout,
            'registration_id' => $registration->id_registration,
            'base_amount_ron' => $payment,
            'amount' => $payment,
            'item_status' => CheckoutItemStatus::Active,
        ]);

        $registration->load(['event.location', 'eventDivision', 'player']);

        return [
            'player' => $player,
            'event' => $event->fresh(['location']),
            'eventDivision' => $eventDivision,
            'registration' => $registration,
            'checkout' => $checkout,
            'item' => $item,
            'club' => $club,
            'location' => $location,
        ];
    }

    protected function makeContext(
        array $fixture,
        array $selectedBenefitIds = [],
        ?int $now = null,
        bool $hasReferrerSelected = false,
    ): PricingContext {
        return new PricingContext(
            player: $fixture['player'],
            event: $fixture['event'],
            eventDivision: $fixture['eventDivision'],
            registration: $fixture['registration'],
            checkout: $fixture['checkout'],
            now: $now ?? time(),
            selectedBenefitIds: $selectedBenefitIds,
            hasReferrerSelected: $hasReferrerSelected,
        );
    }
}
