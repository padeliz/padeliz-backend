<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\EventRegistrationCheckoutResource;
use App\Models\EventRegistrationCheckout;
use App\Models\EventRegistrationPaymentProof;
use App\Services\RegistrationPayment\BankProofService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RegistrationPaymentProofController extends V1BaseController
{
    public function __construct(
        private readonly BankProofService $bankProofService,
    ) {
    }

    public function approve(Request $request, int $paymentProof)
    {
        $proof = EventRegistrationPaymentProof::query()->findOrFail($paymentProof);
        $validated = $request->validate([
            'review_message' => 'nullable|string|max:1000',
        ]);

        try {
            $proof = $this->bankProofService->approve(
                $proof,
                (int) $request->user()->id_player,
                $validated['review_message'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['proof' => [$e->getMessage()]]);
        }

        $checkout = EventRegistrationCheckout::query()
            ->with(['player', 'event', 'items.registration.eventDivision', 'paymentAttempts', 'paymentProofs'])
            ->findOrFail($proof->checkout_id);

        return $this->resourceResponse(
            new EventRegistrationCheckoutResource($checkout),
            'Payment proof approved'
        );
    }

    public function reject(Request $request, int $paymentProof)
    {
        $proof = EventRegistrationPaymentProof::query()->findOrFail($paymentProof);
        $validated = $request->validate([
            'review_message' => 'nullable|string|max:1000',
        ]);

        try {
            $proof = $this->bankProofService->reject(
                $proof,
                (int) $request->user()->id_player,
                $validated['review_message'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['proof' => [$e->getMessage()]]);
        }

        $checkout = EventRegistrationCheckout::query()
            ->with(['player', 'event', 'items.registration.eventDivision', 'paymentAttempts', 'paymentProofs'])
            ->findOrFail($proof->checkout_id);

        return $this->resourceResponse(
            new EventRegistrationCheckoutResource($checkout),
            'Payment proof rejected'
        );
    }
}
