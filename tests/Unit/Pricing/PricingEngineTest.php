<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Enums\Benefit\BenefitSelectionSource;
use App\Enums\Registration\CheckoutItemStatus;
use App\Enums\Registration\CheckoutStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\BenefitDefinition;
use App\Models\BenefitRedemption;
use App\Models\Club;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\EventRegistrationCheckout;
use App\Models\EventRegistrationCheckoutItem;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\PlayerStudentProof;
use Carbon\Carbon;
use InvalidArgumentException;

final class PricingEngineTest extends PricingTestCase
{
    public function test_fixed_ron_discount_is_capped_by_remaining_base(): void
    {
        $benefit = BenefitDefinition::factory()->fixedRon(30)->create();

        $this->assertSame(30, $this->discovery->computeDiscountAmount($benefit, 100));
        $this->assertSame(20, $this->discovery->computeDiscountAmount($benefit, 20));
        $this->assertSame(0, $this->discovery->computeDiscountAmount($benefit, 0));
    }

    public function test_percent_discount_applies_to_remaining_floors_and_max_cap(): void
    {
        $percent = BenefitDefinition::factory()->percent(15)->create();
        $this->assertSame(15, $this->discovery->computeDiscountAmount($percent, 100));
        $this->assertSame(14, $this->discovery->computeDiscountAmount($percent, 99));
        $this->assertSame(0, $this->discovery->computeDiscountAmount($percent, 1));

        $capped = BenefitDefinition::factory()->percent(50, maxDiscountRon: 10)->create();
        $this->assertSame(10, $this->discovery->computeDiscountAmount($capped, 100));
        $this->assertSame(5, $this->discovery->computeDiscountAmount($capped, 10));

        $zero = BenefitDefinition::factory()->fixedRon(0)->create();
        $this->assertSame(0, $this->discovery->computeDiscountAmount($zero, 50));
    }

    public function test_stacked_percent_applies_on_remaining_after_prior_discount(): void
    {
        $fixture = $this->seedRegistrationFixture(payment: 100);
        $first = BenefitDefinition::factory()->fixedRon(20)->withPriority(10)->create();
        $second = BenefitDefinition::factory()->percent(10)->withPriority(20)->create();

        $context = $this->makeContext($fixture);
        $applied = $this->discovery->applyStacking($this->discovery->discover($context), 100, []);
        $byId = collect($applied)->keyBy(fn ($row) => (int) $row['benefit']->id_benefit);

        $this->assertSame(20, (int) $byId[$first->id_benefit]['amount_ron']);
        $this->assertSame(8, (int) $byId[$second->id_benefit]['amount_ron']);

        $quote = $this->pricing->quote($context);
        $this->assertSame(72, $quote['amount_due']);
    }

    public function test_stackable_benefits_apply_in_priority_order(): void
    {
        $fixture = $this->seedRegistrationFixture(payment: 100);
        $low = BenefitDefinition::factory()->fixedRon(10)->withPriority(20)->create(['slug' => 'stack-b']);
        $high = BenefitDefinition::factory()->fixedRon(15)->withPriority(10)->create(['slug' => 'stack-a']);

        $quote = $this->pricing->quote($this->makeContext($fixture));
        $this->assertSame([(int) $high->id_benefit, (int) $low->id_benefit], array_column($quote['applied'], 'benefit_id'));
        $this->assertSame(75, $quote['amount_due']);
    }

    public function test_exclusive_in_group_keeps_highest_priority_only(): void
    {
        $fixture = $this->seedRegistrationFixture(payment: 100);
        $winner = BenefitDefinition::factory()->fixedRon(25)->exclusiveInGroup('promo')->withPriority(5)->create(['slug' => 'group-winner']);
        BenefitDefinition::factory()->fixedRon(40)->exclusiveInGroup('promo')->withPriority(50)->create(['slug' => 'group-loser']);
        $other = BenefitDefinition::factory()->fixedRon(5)->exclusiveInGroup('other')->withPriority(60)->create(['slug' => 'other-group']);

        $quote = $this->pricing->quote($this->makeContext($fixture));
        $this->assertSame([(int) $winner->id_benefit, (int) $other->id_benefit], array_column($quote['applied'], 'benefit_id'));
        $this->assertSame(70, $quote['amount_due']);
    }

    public function test_exclusive_global_blocks_or_is_skipped_based_on_priority(): void
    {
        $fixture = $this->seedRegistrationFixture(payment: 100);
        $global = BenefitDefinition::factory()->fixedRon(30)->exclusiveGlobal()->withPriority(1)->create(['slug' => 'global-first']);
        BenefitDefinition::factory()->fixedRon(20)->withPriority(10)->create(['slug' => 'after-global']);

        $first = $this->pricing->quote($this->makeContext($fixture));
        $this->assertSame([(int) $global->id_benefit], array_column($first['applied'], 'benefit_id'));

        BenefitDefinition::query()->where('slug', 'global-first')->update(['priority' => 50]);
        BenefitDefinition::query()->where('slug', 'after-global')->update(['priority' => 1]);
        BenefitDefinition::query()->where('slug', 'global-first')->update(['amount_value' => 50]);

        $second = $this->pricing->quote($this->makeContext($fixture));
        $this->assertCount(1, $second['applied']);
        $this->assertSame('after-global', $second['applied'][0]['slug']);
        $this->assertSame(80, $second['amount_due']);
    }

    public function test_player_optional_vs_auto_selection(): void
    {
        $fixture = $this->seedRegistrationFixture(payment: 100);
        $auto = BenefitDefinition::factory()->fixedRon(12)->auto()->create(['slug' => 'auto-discount']);
        $optional = BenefitDefinition::factory()->fixedRon(18)->playerOptional()->create(['slug' => 'optional-vip']);

        $unselected = $this->pricing->quote($this->makeContext($fixture, selectedBenefitIds: []));
        $this->assertCount(2, $unselected['eligible']);
        $this->assertSame([(int) $auto->id_benefit], array_column($unselected['applied'], 'benefit_id'));
        $this->assertSame(BenefitSelectionSource::Auto->value, $unselected['applied'][0]['selection_source']);
        $this->assertSame(88, $unselected['amount_due']);

        $selected = $this->pricing->quote($this->makeContext($fixture, selectedBenefitIds: [(int) $optional->id_benefit]));
        $this->assertCount(2, $selected['applied']);
        $this->assertSame(70, $selected['amount_due']);
        $this->assertContains(BenefitSelectionSource::Player->value, array_column($selected['applied'], 'selection_source'));

        $ignored = $this->pricing->quote($this->makeContext($fixture, selectedBenefitIds: [999999]));
        $this->assertSame([(int) $auto->id_benefit], array_column($ignored['applied'], 'benefit_id'));
    }

    public function test_scopes_lifetime_date_range_edition_and_club(): void
    {
        $now = Carbon::parse('2026-06-15 12:00:00', config('app.timezone'))->timestamp;
        $clubA = Club::factory()->create();
        $clubB = Club::factory()->create();
        $fixture = $this->seedRegistrationFixture(payment: 100, club: $clubA);
        $otherEdition = PadelEvent::factory()->create();

        $lifetime = BenefitDefinition::factory()->lifetime()->fixedRon(7)->withPriority(10)->create(['slug' => 'life']);
        $inRange = BenefitDefinition::factory()->dateRange(validFrom: $now - 100, validUntil: $now + 100)->fixedRon(5)->withPriority(20)->create(['slug' => 'in-range']);
        BenefitDefinition::factory()->dateRange(validFrom: $now + 10, validUntil: $now + 1000)->fixedRon(5)->withPriority(21)->create(['slug' => 'not-yet']);
        BenefitDefinition::factory()->dateRange(validFrom: $now - 1000, validUntil: $now - 10)->fixedRon(5)->withPriority(22)->create(['slug' => 'expired']);
        $editionMatch = BenefitDefinition::factory()->forEvents($fixture['event'])->fixedRon(8)->withPriority(30)->create(['slug' => 'this-edition']);
        BenefitDefinition::factory()->forEvents($otherEdition)->fixedRon(8)->withPriority(31)->create(['slug' => 'other-edition']);
        $clubMatch = BenefitDefinition::factory()->forClub((int) $clubA->id_club)->fixedRon(9)->withPriority(40)->create(['slug' => 'club-a']);
        BenefitDefinition::factory()->forClub((int) $clubB->id_club)->fixedRon(9)->withPriority(41)->create(['slug' => 'club-b']);
        BenefitDefinition::factory()->inactive()->fixedRon(50)->withPriority(1)->create(['slug' => 'inactive']);

        $quote = $this->pricing->quote($this->makeContext($fixture, now: $now));
        $this->assertSame(
            [
                (int) $lifetime->id_benefit,
                (int) $inRange->id_benefit,
                (int) $editionMatch->id_benefit,
                (int) $clubMatch->id_benefit,
            ],
            array_column($quote['applied'], 'benefit_id')
        );
        $this->assertSame(71, $quote['amount_due']);
    }

    public function test_usage_caps_per_player_global_and_per_edition(): void
    {
        $fixture = $this->seedRegistrationFixture(payment: 100);

        $perPlayer = BenefitDefinition::factory()->fixedRon(10)->withMaxRedemptions(perPlayer: 1)->create(['slug' => 'once-per-player']);
        $otherDivision = EventDivision::factory()->create(['event_id' => $fixture['event']->id_event]);
        $this->createPriorRedemption($fixture['player'], $perPlayer, (int) $fixture['event']->id_event, (int) $otherDivision->id_event_division);
        $this->assertSame([], $this->pricing->quote($this->makeContext($fixture))['eligible']);

        $perPlayer->delete();
        BenefitRedemption::query()->delete();

        $global = BenefitDefinition::factory()->fixedRon(10)->withMaxRedemptions(global: 1)->create(['slug' => 'once-global']);
        $this->createPriorRedemption(User::factory()->create(), $global, (int) $fixture['event']->id_event);
        $this->assertSame([], $this->pricing->quote($this->makeContext($fixture))['eligible']);

        $global->delete();
        BenefitRedemption::query()->delete();

        $perEdition = BenefitDefinition::factory()->fixedRon(10)->withMaxRedemptions(perEdition: 1)->create(['slug' => 'once-per-edition']);
        $this->createPriorRedemption(User::factory()->create(), $perEdition, (int) $fixture['event']->id_event, (int) $fixture['eventDivision']->id_event_division);
        $this->assertSame([], $this->pricing->quote($this->makeContext($fixture))['eligible']);

        $otherFixture = $this->seedRegistrationFixture(payment: 100);
        $allowed = $this->pricing->quote($this->makeContext($otherFixture));
        $this->assertSame([(int) $perEdition->id_benefit], array_column($allowed['applied'], 'benefit_id'));
    }

    public function test_commit_snapshot_hash_is_stable_when_benefit_edited(): void
    {
        $fixture = $this->seedRegistrationFixture(payment: 100);
        $benefit = BenefitDefinition::factory()->fixedRon(20)->create(['slug' => 'snapshot-promo', 'name' => 'Promo 20']);

        $payable = $this->pricing->commitSelections($fixture['item'], $this->makeContext($fixture), []);
        $this->assertSame(80, $payable);

        $redemption = BenefitRedemption::query()
            ->where('checkout_item_id', $fixture['item']->id_checkout_item)
            ->where('benefit_definition_id', $benefit->id_benefit)
            ->firstOrFail();

        $storedHash = $redemption->benefit_snapshot_hash;
        $this->assertSame(
            sha1(json_encode([
                'slug' => 'snapshot-promo',
                'amount_type' => $benefit->amount_type,
                'amount_value' => 20,
                'max_discount_ron' => null,
            ])),
            $storedHash
        );

        $benefit->amount_value = 50;
        $benefit->name = 'Promo 50';
        $benefit->save();

        $redemption->refresh();
        $this->assertSame(20, (int) $redemption->amount_ron);
        $this->assertSame('Promo 20', $redemption->benefit_name);
        $this->assertSame($storedHash, $redemption->benefit_snapshot_hash);
        $this->assertSame(80, (int) $fixture['item']->fresh()->amount);
        $this->assertSame(50, $this->pricing->quote($this->makeContext($fixture))['applied'][0]['amount_ron']);
    }

    public function test_commit_keeps_manual_adjustment_redemption(): void
    {
        $fixture = $this->seedRegistrationFixture(payment: 100);
        $auto = BenefitDefinition::factory()->fixedRon(10)->create(['slug' => 'auto-a']);
        $manual = BenefitDefinition::query()->where('slug', 'manual_adjustment')->firstOrFail();

        BenefitRedemption::factory()->create([
            'checkout_item_id' => $fixture['item']->id_checkout_item,
            'benefit_definition_id' => $manual->id_benefit,
            'amount_ron' => 5,
            'benefit_name' => $manual->name,
            'selection_source' => BenefitSelectionSource::Manager,
            'reason' => 'courtesy',
        ]);

        $this->pricing->commitSelections($fixture['item'], $this->makeContext($fixture), []);
        $rows = BenefitRedemption::query()->where('checkout_item_id', $fixture['item']->id_checkout_item)->get();

        $this->assertCount(2, $rows);
        $this->assertTrue($rows->contains(fn ($r) => (int) $r->benefit_definition_id === (int) $auto->id_benefit && (int) $r->amount_ron === 10));
        $this->assertTrue($rows->contains(fn ($r) => (int) $r->benefit_definition_id === (int) $manual->id_benefit && (int) $r->amount_ron === 5));
        $this->assertSame(85, (int) $fixture['item']->fresh()->amount);
    }

    public function test_manual_adjustment_unpaid_only(): void
    {
        $fixture = $this->seedRegistrationFixture(payment: 100);
        $manual = BenefitDefinition::query()->where('slug', 'manual_adjustment')->firstOrFail();

        $redemption = $this->pricing->applyManualAdjustment($fixture['item'], 15, 'Goodwill credit');
        $this->assertSame((int) $manual->id_benefit, (int) $redemption->benefit_definition_id);
        $this->assertSame(BenefitSelectionSource::Manager, $redemption->selection_source);
        $this->assertSame(85, (int) $fixture['item']->fresh()->amount);

        $this->pricing->applyManualAdjustment($fixture['item'], 25, 'updated');
        $this->assertSame(1, BenefitRedemption::query()->where('checkout_item_id', $fixture['item']->id_checkout_item)->count());
        $this->assertSame(75, (int) $fixture['item']->fresh()->amount);

        try {
            $this->pricing->applyManualAdjustment($fixture['item'], -1, 'bad');
            $this->fail('Expected negative amount to throw');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('non-negative', $e->getMessage());
        }

        try {
            $this->pricing->applyManualAdjustment($fixture['item'], 5, '');
            $this->fail('Expected empty reason to throw');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('reason', $e->getMessage());
        }

        $paid = $this->seedRegistrationFixture(payment: 100, paymentStatus: RegistrationPaymentStatus::Paid);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fees are locked for this registration.');
        $this->pricing->applyManualAdjustment($paid['item'], 10, 'Too late');
    }

    public function test_student_condition_needs_an_active_approved_student_proof(): void
    {
        $fixture = $this->seedRegistrationFixture(payment: 100);
        $benefit = BenefitDefinition::factory()->fixedRon(10)->withStudentProofActive()->create(['slug' => 'student-test']);

        $this->assertSame([], $this->pricing->quote($this->makeContext($fixture))['eligible']);

        $pending = PlayerStudentProof::factory()->create([
            'player_user_id' => $fixture['player']->id_user,
        ]);
        $this->assertSame([], $this->pricing->quote($this->makeContext($fixture))['eligible']);

        $pending->delete();
        PlayerStudentProof::factory()->expired()->create([
            'player_user_id' => $fixture['player']->id_user,
        ]);
        $this->assertSame([], $this->pricing->quote($this->makeContext($fixture))['eligible']);

        PlayerStudentProof::factory()->approved()->create([
            'player_user_id' => $fixture['player']->id_user,
        ]);
        $allowed = $this->pricing->quote($this->makeContext($fixture));
        $this->assertSame([(int) $benefit->id_benefit], array_column($allowed['applied'], 'benefit_id'));
        $this->assertSame(90, $allowed['amount_due']);
    }

    public function test_early_bird_days_before_edition_date_boundary(): void
    {
        $tz = (string) config('app.timezone', 'UTC');
        $editionDate = Carbon::parse('2026-07-20', $tz)->startOfDay();
        $fixture = $this->seedRegistrationFixture(payment: 100, editionDate: $editionDate->format('Y-m-d'));
        $benefit = BenefitDefinition::factory()->fixedRon(12)->withDaysBeforeEditionDate(7)->create(['slug' => 'early-bird-test']);

        $onBoundary = Carbon::parse('2026-07-13', $tz)->startOfDay()->timestamp;
        $this->assertSame(7, $this->makeContext($fixture, now: $onBoundary)->daysBeforeEditionDate());
        $this->assertSame([(int) $benefit->id_benefit], array_column(
            $this->pricing->quote($this->makeContext($fixture, now: $onBoundary))['applied'],
            'benefit_id'
        ));

        $tooLate = Carbon::parse('2026-07-14', $tz)->startOfDay()->timestamp;
        $this->assertSame([], $this->pricing->quote($this->makeContext($fixture, now: $tooLate))['eligible']);

        $early = Carbon::parse('2026-07-12', $tz)->startOfDay()->timestamp;
        $this->assertSame([(int) $benefit->id_benefit], array_column(
            $this->pricing->quote($this->makeContext($fixture, now: $early))['applied'],
            'benefit_id'
        ));
    }

    private function createPriorRedemption(
        User $player,
        BenefitDefinition $benefit,
        int $editionId,
        ?int $eventDivisionId = null,
    ): void {
        if ($eventDivisionId === null) {
            $eventDivisionId = (int) EventDivision::factory()->create([
                'event_id' => $editionId,
            ])->id_event_division;
        }

        $registration = EventRegistration::factory()->create([
            'event_id' => $editionId,
            'event_division_id' => $eventDivisionId,
            'player_user_id' => $player->id_user,
            'payment' => 100,
        ]);
        $checkout = EventRegistrationCheckout::query()->create([
            'event_id' => $editionId,
            'player_user_id' => $player->id_user,
            'status' => CheckoutStatus::Completed,
            'total_amount' => 90,
            'paid_at' => time(),
        ]);
        $item = EventRegistrationCheckoutItem::query()->create([
            'checkout_id' => $checkout->id_checkout,
            'registration_id' => $registration->id_registration,
            'base_amount_ron' => 100,
            'amount' => 90,
            'item_status' => CheckoutItemStatus::Active,
        ]);

        BenefitRedemption::factory()->create([
            'checkout_item_id' => $item->id_checkout_item,
            'benefit_definition_id' => $benefit->id_benefit,
            'amount_ron' => 10,
            'benefit_name' => $benefit->name,
            'selection_source' => BenefitSelectionSource::Auto,
        ]);
    }
}
