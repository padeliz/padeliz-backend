<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\PlayerStudentProof;
use App\Services\Pricing\PricingContext;
use App\Services\Pricing\PricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Pricing Preview",
 *     description="(v1) Preview benefit stacking and payable amount"
 * )
 */
class PricingPreviewController extends V1BaseController
{
    public function __construct(
        private readonly PricingService $pricingService,
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/pricing/preview",
     *     summary="Preview pricing for a player/edition/division",
     *     tags={"🐧 Manager: Pricing Preview"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Pricing quote"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'player_user_id' => 'required|integer|exists:users,id_user',
            'event_id' => 'required|integer|exists:events,id_event',
            'event_division_id' => [
                'required',
                'integer',
                Rule::exists('event_divisions', 'id_event_division')
                    ->where('event_id', $request->input('event_id')),
            ],
            'selected_benefit_ids' => 'sometimes|array',
            'selected_benefit_ids.*' => 'integer|exists:benefit_definitions,id_benefit',
            'referrer_player_user_id' => 'sometimes|nullable|integer|exists:users,id_user',
        ]);

        $player = User::query()->findOrFail($validated['player_user_id']);
        $event = PadelEvent::query()->with('location')->findOrFail($validated['event_id']);
        $division = EventDivision::query()->findOrFail($validated['event_division_id']);

        $registration = EventRegistration::query()
            ->where('event_id', $event->id_event)
            ->where('player_user_id', $player->id_user)
            ->where('event_division_id', $division->id_event_division)
            ->active()
            ->orderByDesc('id_registration')
            ->first();

        if ($registration === null) {
            $registration = new EventRegistration([
                'event_id' => $event->id_event,
                'player_user_id' => $player->id_user,
                'event_division_id' => $division->id_event_division,
                'is_minor' => false,
                'is_student' => PlayerStudentProof::hasActiveFor($player),
                'payment' => (int) $division->entry_fee,
            ]);
        }

        $registration->setRelation('player', $player);
        $registration->setRelation('event', $event);
        $registration->setRelation('eventDivision', $division);

        $selectedBenefitIds = array_map('intval', $validated['selected_benefit_ids'] ?? []);
        $referrerPlayerId = isset($validated['referrer_player_user_id'])
            ? (int) $validated['referrer_player_user_id']
            : null;

        $context = new PricingContext(
            player: $player,
            event: $event,
            eventDivision: $division,
            registration: $registration,
            checkout: null,
            now: time(),
            selectedBenefitIds: $selectedBenefitIds,
            selectedReferrerPlayerId: $referrerPlayerId,
            touchpoints: [],
            hasReferrerSelected: $referrerPlayerId !== null,
        );

        $quote = $this->pricingService->quote($context);

        return $this->successResponse($quote, 'Pricing preview');
    }
}
