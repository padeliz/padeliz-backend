<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Models\EventReferralCode;
use App\Models\PadelEvent;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReferralCodeController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {
    }

    public function index(Request $request, PadelEvent $event)
    {
        $eventId = (int) $event->id_event;
        $codes = EventReferralCode::query()
            ->with('invitees')
            ->where('event_id', $eventId)
            ->where('referrer_player_user_id', $request->user()->id_user)
            ->orderByDesc('id_referral_code')
            ->get();

        return $this->successResponse([
            'remaining_slots' => $this->referralService->remainingSlots(
                (int) $request->user()->id_user,
                $eventId
            ),
            'codes' => $codes,
        ], 'Referral codes retrieved');
    }

    public function store(Request $request, PadelEvent $event)
    {
        $eventId = (int) $event->id_event;

        if (time() >= (int) $event->registration_allowed_until) {
            return $this->validationErrorResponse([
                'event' => ['Referral codes can no longer be created after registration closes.'],
            ]);
        }

        $validated = $request->validate([
            'invitees' => 'required|array|min:1',
            'invitees.*.email' => 'nullable|email',
            'invitees.*.phone' => 'nullable|string|max:32',
            'invitees.*.player_user_id' => 'nullable|integer|exists:users,id_user',
        ]);

        try {
            $code = $this->referralService->createCode(
                $request->user(),
                $eventId,
                $validated['invitees']
            );
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse([
                'invitees' => [$e->getMessage()],
                'remaining_slots' => [
                    $this->referralService->remainingSlots((int) $request->user()->id_user, $eventId),
                ],
            ]);
        }

        return $this->successResponse([
            'remaining_slots' => $this->referralService->remainingSlots(
                (int) $request->user()->id_user,
                $eventId
            ),
            'code' => $code,
        ], 'Referral code created', 201);
    }

    public function revoke(Request $request, int $referralCode)
    {
        $code = EventReferralCode::query()
            ->where('id_referral_code', $referralCode)
            ->where('referrer_player_user_id', $request->user()->id_user)
            ->firstOrFail();

        $event = PadelEvent::query()->findOrFail((int) $code->event_id);
        if (time() >= (int) $event->event_starts_at) {
            return $this->validationErrorResponse([
                'event' => ['Referral codes can no longer be revoked after the event has started.'],
            ]);
        }

        if ($code->revoked_at === null) {
            $code->forceFill(['revoked_at' => time()])->save();
        }

        $eventId = (int) $code->event_id;

        return $this->successResponse([
            'remaining_slots' => $this->referralService->remainingSlots(
                (int) $request->user()->id_user,
                $eventId
            ),
            'code' => $code->fresh('invitees'),
        ], 'Referral code revoked');
    }
}
