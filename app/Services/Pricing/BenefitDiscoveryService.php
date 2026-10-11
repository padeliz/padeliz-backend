<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\Benefit\BenefitAmountType;
use App\Enums\Benefit\BenefitConditionType;
use App\Enums\Benefit\BenefitExclusivity;
use App\Enums\Benefit\BenefitScopeType;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\BenefitDefinition;
use App\Models\BenefitEntitlement;
use App\Models\BenefitRedemption;
use App\Models\EventRegistration;
use App\Models\PlayerStudentProof;
use Illuminate\Support\Collection;

final class BenefitDiscoveryService
{
    /**
     * @return Collection<int, array{benefit: BenefitDefinition, entitlement: ?BenefitEntitlement, auto: bool}>
     */
    public function discover(PricingContext $context): Collection
    {
        $benefits = BenefitDefinition::query()
            ->with(['conditions', 'events'])
            ->where('is_active', true)
            ->where('slug', '!=', 'manual_adjustment')
            ->orderBy('priority')
            ->get();

        $entitlements = BenefitEntitlement::query()
            ->with('benefitDefinition.conditions')
            ->where('player_user_id', $context->player->id_user)
            ->where('status', 'active')
            ->where(function ($q) use ($context) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $context->now);
            })
            ->where(function ($q) use ($context) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $context->now);
            })
            ->get()
            ->keyBy('benefit_definition_id');

        $candidates = collect();

        foreach ($benefits as $benefit) {
            if (! $this->inScope($benefit, $context)) {
                continue;
            }

            $entitlement = $entitlements->get($benefit->id_benefit);
            $requiresEntitlement = $benefit->slug === 'referrer'
                || $benefit->conditions->isEmpty() && $entitlement !== null;

            // Entitlement-only benefits (e.g. referrer, VIP grants): include when entitlement exists
            if ($benefit->slug === 'referrer' || ($benefit->conditions->isEmpty() && $entitlement)) {
                if (! $entitlement) {
                    continue;
                }
                if (! $this->entitlementHasUses($entitlement)) {
                    continue;
                }
                $candidates->push([
                    'benefit' => $benefit,
                    'entitlement' => $entitlement,
                    'auto' => ! $benefit->player_optional,
                ]);

                continue;
            }

            if (! $this->passesConditions($benefit, $context)) {
                continue;
            }

            if (! $this->withinUsageCaps($benefit, $context)) {
                continue;
            }

            $candidates->push([
                'benefit' => $benefit,
                'entitlement' => $entitlement,
                'auto' => ! $benefit->player_optional,
            ]);
        }

        // Entitlements for benefits that may be inactive as catalog auto but granted
        foreach ($entitlements as $entitlement) {
            $benefit = $entitlement->benefitDefinition;
            if (! $benefit || ! $benefit->is_active) {
                continue;
            }
            if ($candidates->contains(fn ($c) => $c['benefit']->id_benefit === $benefit->id_benefit)) {
                continue;
            }
            if (! $this->inScope($benefit, $context) || ! $this->entitlementHasUses($entitlement)) {
                continue;
            }
            if ($benefit->conditions->isNotEmpty() && ! $this->passesConditions($benefit, $context)) {
                continue;
            }
            $candidates->push([
                'benefit' => $benefit,
                'entitlement' => $entitlement,
                'auto' => ! $benefit->player_optional,
            ]);
        }

        return $candidates->sortBy(fn ($c) => (int) $c['benefit']->priority)->values();
    }

    private function inScope(BenefitDefinition $benefit, PricingContext $context): bool
    {
        if ($benefit->valid_from !== null && $context->now < (int) $benefit->valid_from) {
            return false;
        }
        if ($benefit->valid_until !== null && $context->now > (int) $benefit->valid_until) {
            return false;
        }

        return match ($benefit->scope_type instanceof BenefitScopeType ? $benefit->scope_type : BenefitScopeType::from((string) $benefit->scope_type)) {
            BenefitScopeType::Lifetime => true,
            BenefitScopeType::DateRange => true,
            BenefitScopeType::Club => (int) $benefit->club_id === (int) ($context->event->location?->club_id
                ?? $context->event->location()->value('club_id')),
            BenefitScopeType::PadelEvent => $benefit->relationLoaded('events')
                ? $benefit->events->contains('id_event', $context->event->id_event)
                : $benefit->events()->where('events.id_event', $context->event->id_event)->exists(),
        };
    }

    private function passesConditions(BenefitDefinition $benefit, PricingContext $context): bool
    {
        foreach ($benefit->conditions as $condition) {
            $type = $condition->condition_type instanceof BenefitConditionType
                ? $condition->condition_type
                : BenefitConditionType::tryFrom((string) $condition->condition_type);
            $params = is_array($condition->parameters) ? $condition->parameters : [];

            $ok = match ($type) {
                BenefitConditionType::StudentProofActive => PlayerStudentProof::query()
                    ->where('player_user_id', $context->player->id_user)
                    ->active($context->now)
                    ->exists(),
                BenefitConditionType::DaysBeforeEditionDate => $context->daysBeforeEditionDate() >= (int) ($params['min_days'] ?? 0),
                BenefitConditionType::ReferrerSelected => $context->hasReferrerSelected,
                BenefitConditionType::NoPriorPaidRegistrationInEdition => ! $this->hasPriorPaidRegistration(
                    (int) $context->event->id_event,
                    (int) $context->player->id_user,
                    (int) $context->registration->id_registration
                ),
                default => false,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    private function hasPriorPaidRegistration(int $editionId, int $playerId, int $excludeRegistrationId): bool
    {
        return EventRegistration::query()
            ->where('event_id', $editionId)
            ->where('player_user_id', $playerId)
            ->where('id_registration', '!=', $excludeRegistrationId)
            ->where('payment_status', RegistrationPaymentStatus::Paid->value)
            ->exists()
            || EventRegistration::query()
                ->where('event_id', $editionId)
                ->where('player_user_id', $playerId)
                ->where('id_registration', '!=', $excludeRegistrationId)
                ->whereIn('payment_status', [
                    RegistrationPaymentStatus::Refunded->value,
                    RegistrationPaymentStatus::RefundPending->value,
                ])
                ->exists();
    }

    private function withinUsageCaps(BenefitDefinition $benefit, PricingContext $context): bool
    {
        if ($benefit->max_redemptions_per_player !== null) {
            $count = BenefitRedemption::query()
                ->where('benefit_definition_id', $benefit->id_benefit)
                ->whereHas('checkoutItem.registration', fn ($q) => $q->where('player_user_id', $context->player->id_user))
                ->count();
            if ($count >= (int) $benefit->max_redemptions_per_player) {
                return false;
            }
        }

        if ($benefit->max_redemptions_global !== null) {
            $count = BenefitRedemption::query()
                ->where('benefit_definition_id', $benefit->id_benefit)
                ->count();
            if ($count >= (int) $benefit->max_redemptions_global) {
                return false;
            }
        }

        if ($benefit->max_redemptions_per_event !== null) {
            $count = BenefitRedemption::query()
                ->where('benefit_definition_id', $benefit->id_benefit)
                ->whereHas('checkoutItem.registration', fn ($q) => $q->where('event_id', $context->event->id_event))
                ->count();
            if ($count >= (int) $benefit->max_redemptions_per_event) {
                return false;
            }
        }

        return true;
    }

    private function entitlementHasUses(BenefitEntitlement $entitlement): bool
    {
        return $entitlement->remaining_uses === null || (int) $entitlement->remaining_uses > 0;
    }

    public function computeDiscountAmount(BenefitDefinition $benefit, int $remainingBase): int
    {
        $type = $benefit->amount_type instanceof BenefitAmountType
            ? $benefit->amount_type
            : BenefitAmountType::from((string) $benefit->amount_type);

        $amount = match ($type) {
            BenefitAmountType::FixedRon => (int) $benefit->amount_value,
            BenefitAmountType::Percent => (int) floor($remainingBase * ((int) $benefit->amount_value) / 100),
        };

        if ($benefit->max_discount_ron !== null) {
            $amount = min($amount, (int) $benefit->max_discount_ron);
        }

        return max(0, min($amount, $remainingBase));
    }

    /**
     * @param  Collection<int, array{benefit: BenefitDefinition, entitlement: ?BenefitEntitlement, auto: bool}>  $candidates
     * @param  list<int>  $selectedBenefitIds
     * @return list<array{benefit: BenefitDefinition, entitlement: ?BenefitEntitlement, amount_ron: int}>
     */
    public function applyStacking(Collection $candidates, int $baseFee, array $selectedBenefitIds): array
    {
        $selected = $candidates->filter(function (array $c) use ($selectedBenefitIds) {
            if ($c['auto']) {
                return true;
            }

            return in_array((int) $c['benefit']->id_benefit, $selectedBenefitIds, true);
        })->values();

        $applied = [];
        $usedGroups = [];
        $globalExclusiveTaken = false;
        $remaining = $baseFee;

        foreach ($selected as $c) {
            /** @var BenefitDefinition $benefit */
            $benefit = $c['benefit'];
            $exclusivity = $benefit->exclusivity instanceof BenefitExclusivity
                ? $benefit->exclusivity
                : BenefitExclusivity::from((string) $benefit->exclusivity);

            if ($globalExclusiveTaken) {
                continue;
            }

            if ($exclusivity === BenefitExclusivity::ExclusiveGlobal) {
                if ($applied !== []) {
                    continue;
                }
                $amount = $this->computeDiscountAmount($benefit, $remaining);
                if ($amount <= 0 && (int) $benefit->amount_value > 0) {
                    continue;
                }
                $applied[] = ['benefit' => $benefit, 'entitlement' => $c['entitlement'], 'amount_ron' => $amount];
                $remaining = max(0, $remaining - $amount);
                $globalExclusiveTaken = true;

                continue;
            }

            if ($exclusivity === BenefitExclusivity::ExclusiveInGroup) {
                $group = (string) ($benefit->stack_group ?? '');
                if ($group !== '' && isset($usedGroups[$group])) {
                    continue;
                }
                if ($group !== '') {
                    $usedGroups[$group] = true;
                }
            }

            $amount = $this->computeDiscountAmount($benefit, $remaining);
            $applied[] = ['benefit' => $benefit, 'entitlement' => $c['entitlement'], 'amount_ron' => $amount];
            $remaining = max(0, $remaining - $amount);
        }

        return $applied;
    }
}
