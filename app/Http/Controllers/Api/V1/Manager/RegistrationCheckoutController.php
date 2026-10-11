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

    /**
     * Queue triage (`queue=`) is the default and required view. A player-scoped view
     * (`filters[player_user_id]`, the Player > Checkouts tab) lists every checkout of that
     * player instead: no queue is required and queue triage is skipped.
     */
    public function index(Request $request)
    {
        $playerId = $this->playerIdFilter($request);

        if ($playerId === null) {
            $validated = $request->validate([
                'queue' => 'required|string|in:' . implode(',', CheckoutQueueService::allowedQueues()),
            ]);
        }

        $query = EventRegistrationCheckout::query()
            ->with([
                'player',
                'event',
                'items.registration.eventDivision',
                'paymentAttempts',
                'paymentProofs',
            ])
            ->orderByDesc('id_checkout');

        if ($playerId !== null) {
            $query->where('player_user_id', $playerId);
        } else {
            $this->queueService->applyQueue($query, $validated['queue']);
        }

        return $this->resourceResponse(
            EventRegistrationCheckoutResource::collection($query->get()),
            'Registration checkouts retrieved'
        );
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function playerIdFilter(Request $request): ?int
    {
        $filters = $request->input('filters');

        if (! is_array($filters) || ! array_key_exists('player_user_id', $filters) || $filters['player_user_id'] === '' || $filters['player_user_id'] === null) {
            return null;
        }

        $request->validate([
            'filters.player_user_id' => 'integer|min:1',
        ]);

        return (int) $filters['player_user_id'];
    }
}
