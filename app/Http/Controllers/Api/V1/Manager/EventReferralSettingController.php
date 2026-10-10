<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\EventReferralSettingResource;
use App\Models\EventReferralSetting;
use App\Models\PadelEvent;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Referral Settings",
 *     description="(v1) Per-edition referral code TTL settings"
 * )
 */
class EventReferralSettingController extends V1BaseController
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/events/{event}/referral-settings",
     *     summary="Get edition referral settings",
     *     tags={"🐧 Manager: PadelEvent Referral Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show(PadelEvent $event)
    {
        $setting = EventReferralSetting::query()
            ->where('event_id', $event->id_event)
            ->first();

        if ($setting === null) {
            return $this->successResponse([
                'id_event_referral_setting' => null,
                'event_id' => (int) $event->id_event,
                'code_ttl_hours' => $this->referralService->effectiveTtlHours((int) $event->id_event),
                'inserted_at' => null,
                'updated_at' => null,
            ], 'PadelEvent referral settings (defaults)');
        }

        return $this->resourceResponse(
            new EventReferralSettingResource($setting),
            'PadelEvent referral settings retrieved successfully'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/events/{event}/referral-settings",
     *     summary="Upsert edition referral settings",
     *     tags={"🐧 Manager: PadelEvent Referral Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Saved")
     * )
     */
    public function upsert(Request $request, PadelEvent $event)
    {
        $validated = $request->validate([
            'code_ttl_hours' => 'required|integer|min:1|max:8760',
        ]);

        $setting = EventReferralSetting::query()->updateOrCreate(
            ['event_id' => $event->id_event],
            ['code_ttl_hours' => (int) $validated['code_ttl_hours']]
        );

        return $this->resourceResponse(
            new EventReferralSettingResource($setting),
            'PadelEvent referral settings saved'
        );
    }
}
