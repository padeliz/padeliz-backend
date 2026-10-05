<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\Benefit\BenefitSelectionSource;
use App\Enums\Registration\CheckoutItemStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\BenefitDefinition;
use App\Models\BenefitEntitlement;
use App\Models\BenefitRedemption;
use App\Models\EventReferralAttribution;
use App\Models\EventRegistration;
use App\Models\EventRegistrationCheckout;
use App\Models\EventRegistrationCheckoutItem;
use App\Models\Eligibility;
use App\Models\Player;
use App\Enums\Referral\ReferralAttributionStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PricingService
{
    public function __construct(
        private readonly BenefitDiscoveryService $discovery,
    ) {
    }

    /**
     * @param  list<int>  $selectedBenefitIds
     * @return array{
     *   base_fee: int,
     *   amount_due: int,
     *   eligible: list<array{benefit_id: int, slug: string, name: string, auto: bool, amount_ron: int}>,
     *   applied: list<array{benefit_id: int, slug: string, name: string, amount_ron: int, selection_source: string}>
     * }
     */
    public function quote(PricingContext $context): array
    {
        $candidates = $this->discovery->discover($context);
        $base = (int) $context->registration->payment;
        $applied = $this->discovery->applyStacking($candidates, $base, $context->selectedBenefitIds);

        $eligible = [];
        foreach ($candidates as $c) {
            $previewAmount = $this->discovery->computeDiscountAmount($c['benefit'], $base);
            $eligible[] = [
                'benefit_id' => (int) $c['benefit']->id_benefit,
                'slug' => (string) $c['benefit']->slug,
                'name' => (string) $c['benefit']->name,
                'auto' => (bool) $c['auto'],
                'player_optional' => (bool) $c['benefit']->player_optional,
                'amount_ron' => $previewAmount,
            ];
        }

        $appliedOut = [];
        $due = $base;
        foreach ($applied as $row) {
            $appliedOut[] = [
                'benefit_id' => (int) $row['benefit']->id_benefit,
                'slug' => (string) $row['benefit']->slug,
                'name' => (string) $row['benefit']->name,
                'amount_ron' => (int) $row['amount_ron'],
                'selection_source' => $row['benefit']->player_optional
                    ? BenefitSelectionSource::Player->value
                    : BenefitSelectionSource::Auto->value,
            ];
            $due = max(0, $due - (int) $row['amount_ron']);
        }

        return [
            'base_fee' => $base,
            'amount_due' => $due,
            'eligible' => $eligible,
            'applied' => $appliedOut,
        ];
    }

    /**
     * Replace unpaid redemptions for a checkout item from selected benefits (locks non-student thereafter by persistence).
     *
     * @param  list<int>  $selectedBenefitIds
     */
    public function commitSelections(
        EventRegistrationCheckoutItem $item,
        PricingContext $context,
        array $selectedBenefitIds,
        ?int $referralAttributionId = null,
    ): int {
        if ($item->registration->feesAreLocked()) {
            throw new InvalidArgumentException('Fees are locked for this registration.');
        }

        $context = new PricingContext(
            player: $context->player,
            event: $context->event,
            eventDivision: $context->eventDivision,
            registration: $context->registration,
            checkout: $context->checkout,
            eligibility: $context->eligibility,
            now: $context->now,
            selectedBenefitIds: $selectedBenefitIds,
            selectedReferrerPlayerId: $context->selectedReferrerPlayerId,
            touchpoints: $context->touchpoints,
            hasReferrerSelected: $context->hasReferrerSelected,
            lockNonStudentBenefits: true,
        );

        $candidates = $this->discovery->discover($context);
        $applied = $this->discovery->applyStacking(
            $candidates,
            (int) $context->registration->payment,
            $selectedBenefitIds
        );

        return DB::transaction(function () use ($item, $applied, $referralAttributionId, $context) {
            // Keep manager manual adjustments
            $manual = BenefitDefinition::query()->where('slug', 'manual_adjustment')->first();
            BenefitRedemption::query()
                ->where('checkout_item_id', $item->id_checkout_item)
                ->when($manual, fn ($q) => $q->where('benefit_definition_id', '!=', $manual->id_benefit))
                ->delete();

            $totalDiscount = 0;
            foreach ($applied as $row) {
                $amount = (int) $row['amount_ron'];
                $totalDiscount += $amount;
                BenefitRedemption::query()->create([
                    'checkout_item_id' => $item->id_checkout_item,
                    'benefit_definition_id' => $row['benefit']->id_benefit,
                    'entitlement_id' => $row['entitlement']?->id_entitlement,
                    'referral_attribution_id' => $row['benefit']->slug === 'referred' ? $referralAttributionId : null,
                    'amount_ron' => $amount,
                    'benefit_name' => $row['benefit']->name,
                    'benefit_snapshot_hash' => sha1(json_encode([
                        'slug' => $row['benefit']->slug,
                        'amount_type' => $row['benefit']->amount_type,
                        'amount_value' => $row['benefit']->amount_value,
                        'max_discount_ron' => $row['benefit']->max_discount_ron,
                    ])),
                    'selection_source' => $row['benefit']->player_optional
                        ? BenefitSelectionSource::Player->value
                        : BenefitSelectionSource::Auto->value,
                    'reason' => null,
                ]);
            }

            $manualTotal = (int) BenefitRedemption::query()
                ->where('checkout_item_id', $item->id_checkout_item)
                ->when($manual, fn ($q) => $q->where('benefit_definition_id', $manual->id_benefit))
                ->sum('amount_ron');

            $payable = max(0, (int) $context->registration->payment - $totalDiscount - $manualTotal);
            $item->base_amount_ron = (int) $context->registration->payment;
            $item->amount = $payable;
            $item->save();

            return $payable;
        });
    }

    public function resyncItemAmount(EventRegistrationCheckoutItem $item): void
    {
        $registration = $item->registration;
        $sum = (int) BenefitRedemption::query()
            ->where('checkout_item_id', $item->id_checkout_item)
            ->sum('amount_ron');
        $item->base_amount_ron = (int) $registration->payment;
        $item->amount = max(0, (int) $registration->payment - $sum);
        $item->save();
    }

    public function revalidateStudentOnPay(EventRegistrationCheckoutItem $item, ?Eligibility $eligibility): void
    {
        $registration = $item->registration()->firstOrFail();
        if ($registration->feesAreLocked()) {
            return;
        }

        $studentBenefit = BenefitDefinition::query()->where('slug', 'student')->where('is_active', true)->first();
        if (! $studentBenefit) {
            $this->resyncItemAmount($item);

            return;
        }

        $isStudent = (bool) ($eligibility?->being_student ?? false);
        $existing = BenefitRedemption::query()
            ->where('checkout_item_id', $item->id_checkout_item)
            ->where('benefit_definition_id', $studentBenefit->id_benefit)
            ->first();

        if ($isStudent && ! $existing) {
            $remaining = (int) $registration->payment - (int) BenefitRedemption::query()
                ->where('checkout_item_id', $item->id_checkout_item)
                ->sum('amount_ron');
            $amount = $this->discovery->computeDiscountAmount($studentBenefit, max(0, $remaining));
            BenefitRedemption::query()->create([
                'checkout_item_id' => $item->id_checkout_item,
                'benefit_definition_id' => $studentBenefit->id_benefit,
                'entitlement_id' => null,
                'referral_attribution_id' => null,
                'amount_ron' => $amount,
                'benefit_name' => $studentBenefit->name,
                'benefit_snapshot_hash' => null,
                'selection_source' => BenefitSelectionSource::Auto->value,
                'reason' => null,
            ]);
        } elseif (! $isStudent && $existing) {
            $existing->delete();
        }

        $this->resyncItemAmount($item);
    }

    public function finalizeOnPaid(EventRegistration $registration): void
    {
        $item = $registration->latestCheckoutItem;
        if ($item) {
            $redemptions = BenefitRedemption::query()
                ->where('checkout_item_id', $item->id_checkout_item)
                ->get();
            foreach ($redemptions as $redemption) {
                if ($redemption->entitlement_id) {
                    $entitlement = BenefitEntitlement::query()->find($redemption->entitlement_id);
                    if ($entitlement && $entitlement->remaining_uses !== null) {
                        $entitlement->remaining_uses = max(0, (int) $entitlement->remaining_uses - 1);
                        if ((int) $entitlement->remaining_uses === 0) {
                            $entitlement->status = 'consumed';
                        }
                        $entitlement->save();
                    }
                }
            }
        }

        $attribution = EventReferralAttribution::query()
            ->where('event_id', $registration->event_id)
            ->where('referred_player_id', $registration->player_id)
            ->where('status', ReferralAttributionStatus::Pending->value)
            ->first();

        if (! $attribution) {
            return;
        }

        $alreadyConfirmedOrReleased = EventReferralAttribution::query()
            ->where('event_id', $registration->event_id)
            ->where('referred_player_id', $registration->player_id)
            ->whereIn('status', [
                ReferralAttributionStatus::Confirmed->value,
                ReferralAttributionStatus::Released->value,
            ])
            ->exists();

        // First paid in edition for this player?
        $priorPaid = EventRegistration::query()
            ->where('event_id', $registration->event_id)
            ->where('player_id', $registration->player_id)
            ->where('id_registration', '!=', $registration->id_registration)
            ->whereIn('payment_status', [
                RegistrationPaymentStatus::Paid->value,
                RegistrationPaymentStatus::Refunded->value,
                RegistrationPaymentStatus::RefundPending->value,
            ])
            ->exists();

        if ($priorPaid || $alreadyConfirmedOrReleased) {
            return;
        }

        $attribution->status = ReferralAttributionStatus::Confirmed->value;
        $attribution->save();

        $referrerBenefit = BenefitDefinition::query()->where('slug', 'referrer')->where('is_active', true)->first();
        if (! $referrerBenefit) {
            return;
        }

        BenefitEntitlement::query()->firstOrCreate(
            [
                'benefit_definition_id' => $referrerBenefit->id_benefit,
                'player_id' => $attribution->referrer_player_id,
                'source_type' => 'referral_reward',
                'source_id' => $attribution->id_attribution,
            ],
            [
                'remaining_uses' => 1,
                'valid_from' => time(),
                'valid_until' => null,
                'status' => 'active',
            ]
        );
    }

    public function applyManualAdjustment(
        EventRegistrationCheckoutItem $item,
        int $amountRon,
        string $reason,
    ): BenefitRedemption {
        if ($amountRon < 0) {
            throw new InvalidArgumentException('Manual adjustment must be a non-negative RON reduction.');
        }
        if ($reason === '') {
            throw new InvalidArgumentException('Manual adjustment requires a reason.');
        }
        if ($item->registration->feesAreLocked()) {
            throw new InvalidArgumentException('Fees are locked for this registration.');
        }

        $manual = BenefitDefinition::query()->where('slug', 'manual_adjustment')->firstOrFail();

        $redemption = BenefitRedemption::query()->updateOrCreate(
            [
                'checkout_item_id' => $item->id_checkout_item,
                'benefit_definition_id' => $manual->id_benefit,
            ],
            [
                'entitlement_id' => null,
                'referral_attribution_id' => null,
                'amount_ron' => $amountRon,
                'benefit_name' => $manual->name,
                'benefit_snapshot_hash' => null,
                'selection_source' => BenefitSelectionSource::Manager->value,
                'reason' => $reason,
            ]
        );

        $this->resyncItemAmount($item);

        return $redemption;
    }

    public function buildContextForRegistration(
        EventRegistration $registration,
        ?Eligibility $eligibility = null,
        array $selectedBenefitIds = [],
        ?int $selectedReferrerPlayerId = null,
        bool $hasReferrerSelected = false,
    ): PricingContext {
        $registration->loadMissing(['event.location', 'eventDivision', 'player']);

        return new PricingContext(
            player: $registration->player,
            event: $registration->event,
            eventDivision: $registration->eventDivision,
            registration: $registration,
            checkout: $registration->latestCheckoutItem?->checkout,
            eligibility: $eligibility,
            now: time(),
            selectedBenefitIds: $selectedBenefitIds,
            selectedReferrerPlayerId: $selectedReferrerPlayerId,
            touchpoints: [],
            hasReferrerSelected: $hasReferrerSelected,
        );
    }
}
