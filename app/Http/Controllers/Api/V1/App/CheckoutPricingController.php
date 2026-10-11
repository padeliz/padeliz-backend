<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Enums\Benefit\BenefitSelectionSource;
use App\Enums\Referral\ReferralAttributionStatus;
use App\Http\Resources\EventRegistrationCheckoutResource;
use App\Models\EventReferralAttribution;
use App\Models\EventRegistrationCheckout;
use App\Services\Pricing\PricingService;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CheckoutPricingController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly ReferralService $referralService,
        private readonly \App\Services\RegistrationPayment\CheckoutService $checkoutService,
    ) {
    }

    public function quote(Request $request, int $checkout)
    {
        $checkoutModel = $this->ownedCheckout($request, $checkout);
        $candidates = $this->referralService->candidatesForCheckout(
            $request->user(),
            (int) $checkoutModel->event_id
        );

        $pending = EventReferralAttribution::query()
            ->where('event_id', $checkoutModel->event_id)
            ->where('referred_player_user_id', $checkoutModel->player_user_id)
            ->where('status', ReferralAttributionStatus::Pending->value)
            ->first();

        $lines = [];
        foreach ($checkoutModel->items()->active()->with(['registration.eventDivision', 'redemptions'])->get() as $item) {
            $reg = $item->registration;
            if ($reg === null) {
                continue;
            }
            $selectedBenefitIds = $item->redemptions
                ->where('selection_source', BenefitSelectionSource::Player)
                ->pluck('benefit_definition_id')
                ->map(static fn ($id) => (int) $id)
                ->values()
                ->all();
            $context = $this->pricingService->buildContextForRegistration(
                $reg,
                $selectedBenefitIds,
                $pending?->referrer_player_user_id !== null ? (int) $pending->referrer_player_user_id : null,
                $pending !== null,
            );
            $quote = $this->pricingService->quote($context);
            $lines[] = [
                'checkout_item_id' => $item->id_checkout_item,
                'registration_id' => $reg->id_registration,
                'event_division_id' => $reg->event_division_id,
                'base_amount_ron' => $item->base_amount_ron ?? $reg->payment,
                'amount' => $item->amount,
                'quote' => $quote,
            ];
        }

        return $this->successResponse([
            'checkout_id' => $checkoutModel->id_checkout,
            'total_amount' => $checkoutModel->total_amount,
            'referrer_candidates' => $candidates,
            'lines' => $lines,
        ], 'Checkout quote');
    }

    public function commitSelections(Request $request, int $checkout)
    {
        $checkoutModel = $this->ownedCheckout($request, $checkout);
        $validated = $request->validate([
            'selected_benefit_ids' => 'nullable|array',
            'selected_benefit_ids.*' => 'integer',
            'referrer_player_user_id' => 'nullable|integer|exists:users,id_user',
        ]);

        $selectedBenefitIds = array_map('intval', $validated['selected_benefit_ids'] ?? []);

        try {
            $result = DB::transaction(function () use (
                $checkoutModel,
                $selectedBenefitIds,
                $validated,
                $request
            ) {
                $checkoutModel = $this->checkoutService->lockCheckout((int) $checkoutModel->id_checkout);
                $candidates = $this->referralService->candidatesForCheckout(
                    $request->user(),
                    (int) $checkoutModel->event_id
                );
                $candidateIds = collect($candidates)->pluck('referrer_player_user_id')->all();

                $referrerId = isset($validated['referrer_player_user_id'])
                    ? (int) $validated['referrer_player_user_id']
                    : null;
                $attribution = null;

                if (count($candidateIds) === 0) {
                    $this->referralService->clearPendingAttribution(
                        (int) $checkoutModel->event_id,
                        (int) $checkoutModel->player_user_id
                    );
                    $referrerId = null;
                } elseif (count($candidateIds) === 1) {
                    $referrerId = (int) $candidateIds[0];
                } elseif ($referrerId === null) {
                    throw new InvalidArgumentException('A referrer must be selected.');
                }

                if ($referrerId !== null) {
                    $attribution = $this->referralService->commitAttribution($checkoutModel, $referrerId);
                }

                foreach ($checkoutModel->items()->active()->with('registration')->get() as $item) {
                    $reg = $item->registration;
                    if ($reg === null || $reg->feesAreLocked()) {
                        continue;
                    }
                    $context = $this->pricingService->buildContextForRegistration(
                        $reg,
                        $selectedBenefitIds,
                        $referrerId !== null ? (int) $referrerId : null,
                        $referrerId !== null,
                    );
                    $this->pricingService->commitSelections(
                        $item,
                        $context,
                        $selectedBenefitIds,
                        $attribution?->id_attribution
                    );
                }

                return $this->checkoutService->recomputeTotals($checkoutModel->fresh(['items.registration']));
            });
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['referrer_player_user_id' => [$e->getMessage()]]);
        }

        return $this->resourceResponse(
            new EventRegistrationCheckoutResource(
                $result->load(['items.redemptions', 'items.registration.eventDivision', 'items.registration.checkoutItems.redemptions'])
            ),
            'Benefit selections committed'
        );
    }

    private function ownedCheckout(Request $request, int $checkoutId): EventRegistrationCheckout
    {
        $checkout = EventRegistrationCheckout::query()->findOrFail($checkoutId);
        $this->checkoutService->assertOwnedBy($checkout, (int) $request->user()->id_user);

        return $checkout->load(['items.registration']);
    }
}
