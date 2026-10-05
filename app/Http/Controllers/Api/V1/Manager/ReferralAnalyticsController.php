<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Enums\Referral\ReferralAttributionStatus;
use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\EventReferralAttributionResource;
use App\Http\Resources\ReferralTouchpointResource;
use App\Models\EventReferralAttribution;
use App\Models\ReferralTouchpoint;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Referral Analytics",
 *     description="(v1) Referral touchpoints and attributions"
 * )
 */
class ReferralAnalyticsController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/referral-analytics/touchpoints",
     *     summary="List referral touchpoints",
     *     tags={"🐧 Manager: Referral Analytics"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function touchpoints(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'sometimes|integer|exists:events,id_event',
            'referrer' => 'sometimes|integer|exists:players,id_player',
            'referred' => 'sometimes|integer|exists:players,id_player',
            'discount_eligible' => 'sometimes|boolean',
        ]);

        $query = ReferralTouchpoint::with(['referredPlayer', 'referrerPlayer']);

        if (isset($validated['event_id'])) {
            $query->where('event_id', (int) $validated['event_id']);
        }
        if (isset($validated['referrer'])) {
            $query->where('referrer_player_id', (int) $validated['referrer']);
        }
        if (isset($validated['referred'])) {
            $query->where('referred_player_id', (int) $validated['referred']);
        }
        if (array_key_exists('discount_eligible', $validated)) {
            $query->where('discount_eligible', (bool) $validated['discount_eligible']);
        }

        $paginationParams = $this->getPaginationParams($request);
        $rows = $query->orderByDesc('id_touchpoint')
            ->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return ReferralTouchpointResource::collection($rows);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/referral-analytics/attributions",
     *     summary="List referral attributions",
     *     tags={"🐧 Manager: Referral Analytics"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function attributions(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'sometimes|integer|exists:events,id_event',
            'referrer' => 'sometimes|integer|exists:players,id_player',
            'referred' => 'sometimes|integer|exists:players,id_player',
            'status' => ['sometimes', Rule::enum(ReferralAttributionStatus::class)],
        ]);

        $query = EventReferralAttribution::with(['referredPlayer', 'referrerPlayer']);

        if (isset($validated['event_id'])) {
            $query->where('event_id', (int) $validated['event_id']);
        }
        if (isset($validated['referrer'])) {
            $query->where('referrer_player_id', (int) $validated['referrer']);
        }
        if (isset($validated['referred'])) {
            $query->where('referred_player_id', (int) $validated['referred']);
        }
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $paginationParams = $this->getPaginationParams($request);
        $rows = $query->orderByDesc('id_attribution')
            ->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return EventReferralAttributionResource::collection($rows);
    }
}
