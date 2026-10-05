<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\EventRegistrationCheckoutResource;
use App\Models\EventRegistrationCheckout;
use App\Services\RegistrationPayment\CheckoutQueueService;
use Illuminate\Http\Request;

class RegistrationCheckoutController extends V1BaseController
{
    public function __construct(
        private readonly CheckoutQueueService $queueService,
    ) {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'queue' => 'required|string|in:' . implode(',', CheckoutQueueService::allowedQueues()),
        ]);

        $query = EventRegistrationCheckout::query()
            ->with([
                'player',
                'event',
                'items.registration.eventDivision',
                'paymentAttempts',
                'paymentProofs',
            ])
            ->orderByDesc('id_checkout');

        $this->queueService->applyQueue($query, $validated['queue']);

        return $this->resourceResponse(
            EventRegistrationCheckoutResource::collection($query->get()),
            'Registration checkouts retrieved'
        );
    }
}
