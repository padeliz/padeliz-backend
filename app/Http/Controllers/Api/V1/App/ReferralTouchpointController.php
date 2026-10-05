<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReferralTouchpointController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:events,id_event',
            'code' => 'required|string|max:32',
            'client_metadata' => 'nullable|array',
        ]);

        try {
            $touchpoint = $this->referralService->recordTouchpoint(
                $request->user(),
                (string) $validated['code'],
                (int) $validated['event_id'],
                $validated['client_metadata'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['code' => [$e->getMessage()]]);
        }

        return $this->successResponse($touchpoint, 'Touchpoint recorded', 201);
    }
}
