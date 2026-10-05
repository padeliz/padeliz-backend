<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Models\PadelEvent;
use App\Models\EventReferralCode;
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
            ->where('referrer_player_id', $request->user()->id_player)
            ->orderByDesc('id_referral_code')
            ->get();

        return $this->successResponse([
            'remaining_slots' => $this->referralService->remainingSlots(
                (int) $request->user()->id_player,
                $eventId
            ),
            'codes' => $codes,
        ], 'Referral codes retrieved');
    }

    public function store(Request $request, PadelEvent $event)
    {
        $eventId = (int) $event->id_event;
        $validated = $request->validate([
            'invitees' => 'required|array|min:1',
            'invitees.*.email' => 'nullable|email',
            'invitees.*.phone' => 'nullable|string|max:32',
            'invitees.*.player_id' => 'nullable|integer|exists:players,id_player',
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
                    $this->referralService->remainingSlots((int) $request->user()->id_player, $eventId),
                ],
            ]);
        }

        return $this->successResponse([
            'remaining_slots' => $this->referralService->remainingSlots(
                (int) $request->user()->id_player,
                $eventId
            ),
            'code' => $code,
        ], 'Referral code created', 201);
    }

    public function revoke(Request $request, int $referralCode)
    {
        $code = EventReferralCode::query()
            ->where('id_referral_code', $referralCode)
            ->where('referrer_player_id', $request->user()->id_player)
            ->firstOrFail();

        if ($code->revoked_at === null) {
            $code->forceFill(['revoked_at' => time()])->save();
        }

        $eventId = (int) $code->event_id;

        return $this->successResponse([
            'remaining_slots' => $this->referralService->remainingSlots(
                (int) $request->user()->id_player,
                $eventId
            ),
            'code' => $code->fresh('invitees'),
        ], 'Referral code revoked');
    }
}
