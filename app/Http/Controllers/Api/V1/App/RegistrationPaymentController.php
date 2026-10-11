<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Enums\Registration\RegistrationCancelReason;
use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\EventRegistrationCheckoutResource;
use App\Http\Resources\EventRegistrationResource;
use App\Models\EventRegistration;
use App\Models\EventRegistrationCheckout;
use App\Models\EventRegistrationPaymentProof;
use App\Models\PadelEvent;
use App\Services\RegistrationPayment\BankProofService;
use App\Services\RegistrationPayment\CheckoutService;
use App\Services\RegistrationPayment\NetopiaPaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class RegistrationPaymentController extends V1BaseController
{
    public function __construct(
        private readonly NetopiaPaymentService $netopiaPaymentService,
        private readonly BankProofService $bankProofService,
        private readonly CheckoutService $checkoutService,
    ) {
    }

    public function indexForEvent(Request $request, PadelEvent $event)
    {
        $playerId = (int) $request->user()->id_user;
        $eventId = (int) $event->id_event;

        $checkouts = EventRegistrationCheckout::query()
            ->where('event_id', $eventId)
            ->where('player_user_id', $playerId)
            ->with([
                'items.registration.eventDivision.division',
                'items.redemptions',
                'paymentAttempts',
                'paymentProofs',
            ])
            ->orderByDesc('id_checkout')
            ->get();

        return $this->successResponse([
            'checkouts' => EventRegistrationCheckoutResource::collection($checkouts)->resolve(),
        ], 'PadelEvent registration checkouts');
    }

    public function show(Request $request, int $checkout)
    {
        $checkoutModel = $this->ownedCheckout($request, $checkout);
        $checkoutModel->load([
            'items.registration.eventDivision.division',
            'items.redemptions',
            'paymentAttempts',
            'paymentProofs',
        ]);

        return $this->resourceResponse(
            new EventRegistrationCheckoutResource($checkoutModel),
            'Registration checkout'
        );
    }

    public function startNetopia(Request $request, int $eventRegistration)
    {
        $registration = $this->ownedRegistration($request, $eventRegistration);
        $checkout = $this->checkoutForRegistration($registration);

        try {
            $attempt = $this->netopiaPaymentService->startForCheckout($checkout);
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['payment' => [$e->getMessage()]]);
        }

        $registration->refresh()->load(['event', 'eventDivision', 'checkoutItems']);

        return $this->successResponse([
            'attempt' => [
                'id_payment_attempt' => $attempt->id_payment_attempt,
                'checkout_id' => $attempt->checkout_id,
                'order_id' => $attempt->order_id,
                'redirect_url' => $attempt->redirect_url,
                'status' => $attempt->status?->value ?? $attempt->status,
            ],
            'registration' => (new EventRegistrationResource($registration))->resolve(),
            'checkout' => (new EventRegistrationCheckoutResource(
                $checkout->fresh(['items.registration.eventDivision', 'paymentAttempts', 'paymentProofs'])
            ))->resolve(),
        ], 'Netopia payment started');
    }

    public function startNetopiaForCheckout(Request $request, int $checkout)
    {
        $checkoutModel = $this->ownedCheckout($request, $checkout);

        try {
            $attempt = $this->netopiaPaymentService->startForCheckout($checkoutModel);
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['payment' => [$e->getMessage()]]);
        }

        return $this->successResponse([
            'attempt' => [
                'id_payment_attempt' => $attempt->id_payment_attempt,
                'checkout_id' => $attempt->checkout_id,
                'order_id' => $attempt->order_id,
                'redirect_url' => $attempt->redirect_url,
                'status' => $attempt->status?->value ?? $attempt->status,
            ],
            'checkout' => (new EventRegistrationCheckoutResource(
                $checkoutModel->fresh(['items.registration.eventDivision', 'paymentAttempts', 'paymentProofs'])
            ))->resolve(),
        ], 'Netopia payment started');
    }

    public function uploadBankProof(Request $request, int $eventRegistration)
    {
        $registration = $this->ownedRegistration($request, $eventRegistration);
        $checkout = $this->checkoutForRegistration($registration);

        $request->validate([
            'proof' => 'required|file|max:' . EventRegistrationPaymentProof::MAX_FILE_KILOBYTES,
        ]);

        try {
            $proof = $this->bankProofService->uploadForCheckout($checkout, $request->file('proof'));
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['proof' => [$e->getMessage()]]);
        }

        $registration->refresh()->load(['event', 'eventDivision', 'checkoutItems']);

        return $this->successResponse([
            'proof' => [
                'id_payment_proof' => $proof->id_payment_proof,
                'checkout_id' => $proof->checkout_id,
                'url' => $proof->url,
                'review_status' => $proof->review_status?->value ?? $proof->review_status,
            ],
            'registration' => (new EventRegistrationResource($registration))->resolve(),
            'checkout' => (new EventRegistrationCheckoutResource(
                $checkout->fresh(['items.registration.eventDivision', 'paymentAttempts', 'paymentProofs'])
            ))->resolve(),
        ], 'Bank proof uploaded', 201);
    }

    public function uploadBankProofForCheckout(Request $request, int $checkout)
    {
        $checkoutModel = $this->ownedCheckout($request, $checkout);

        $request->validate([
            'proof' => 'required|file|max:' . EventRegistrationPaymentProof::MAX_FILE_KILOBYTES,
        ]);

        try {
            $proof = $this->bankProofService->uploadForCheckout($checkoutModel, $request->file('proof'));
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['proof' => [$e->getMessage()]]);
        }

        return $this->successResponse([
            'proof' => [
                'id_payment_proof' => $proof->id_payment_proof,
                'checkout_id' => $proof->checkout_id,
                'url' => $proof->url,
                'review_status' => $proof->review_status?->value ?? $proof->review_status,
            ],
            'checkout' => (new EventRegistrationCheckoutResource(
                $checkoutModel->fresh(['items.registration.eventDivision', 'paymentAttempts', 'paymentProofs'])
            ))->resolve(),
        ], 'Bank proof uploaded', 201);
    }

    public function resetCheckout(Request $request, int $checkout)
    {
        $checkoutModel = $this->ownedCheckout($request, $checkout);

        try {
            $checkoutModel = $this->checkoutService->resetPaymentPath($checkoutModel);
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['checkout' => [$e->getMessage()]]);
        }

        return $this->successResponse([
            'checkout' => (new EventRegistrationCheckoutResource(
                $checkoutModel->load(['items.registration.eventDivision', 'paymentAttempts', 'paymentProofs'])
            ))->resolve(),
        ], 'Checkout payment path reset');
    }

    public function excludeFromCheckout(Request $request, int $checkout)
    {
        $checkoutModel = $this->ownedCheckout($request, $checkout);
        $validated = $request->validate([
            'registration_ids' => 'required|array|min:1',
            'registration_ids.*' => 'integer|exists:event_registrations,id_registration',
            'cancel_reason' => ['required', 'string', Rule::in(RegistrationCancelReason::values())],
        ]);

        try {
            $checkoutModel = $this->checkoutService->excludeRegistrations(
                $checkoutModel,
                $validated['registration_ids'],
                app(\App\Services\RegistrationPayment\RegistrationLifecycleService::class),
                true,
                RegistrationCancelReason::from($validated['cancel_reason']),
            );
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['checkout' => [$e->getMessage()]]);
        }

        return $this->successResponse([
            'checkout' => (new EventRegistrationCheckoutResource(
                $checkoutModel->load(['items.registration.eventDivision', 'paymentAttempts', 'paymentProofs'])
            ))->resolve(),
        ], 'Registrations excluded from checkout');
    }

    private function ownedRegistration(Request $request, int $eventRegistration): EventRegistration
    {
        return EventRegistration::query()
            ->where('id_registration', $eventRegistration)
            ->where('player_user_id', $request->user()->id_user)
            ->firstOrFail();
    }

    private function ownedCheckout(Request $request, int $checkout): EventRegistrationCheckout
    {
        return EventRegistrationCheckout::query()
            ->where('id_checkout', $checkout)
            ->where('player_user_id', $request->user()->id_user)
            ->firstOrFail();
    }

    private function checkoutForRegistration(EventRegistration $registration): EventRegistrationCheckout
    {
        $checkout = $this->checkoutService->openCheckoutForRegistration($registration);
        if ($checkout !== null) {
            return $checkout;
        }

        return $this->checkoutService->openOrRefreshForUnpaid(
            (int) $registration->event_id,
            (int) $registration->player_user_id
        );
    }
}
