<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Enums\Registration\CheckoutItemStatus;
use App\Enums\Registration\RegistrationCancelReason;
use App\Enums\Registration\RegistrationDeclineReason;
use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\BenefitRedemptionResource;
use App\Http\Resources\EventRegistrationResource;
use App\Models\EventRegistration;
use App\Models\PadelEvent;
use App\Services\Pricing\PricingService;
use App\Services\RegistrationPayment\CheckoutService;
use App\Services\RegistrationPayment\RegistrationLifecycleService;
use App\Services\RegistrationPayment\RegistrationQueueService;
use App\Services\RegistrationPayment\RegistrationService;
use App\Services\RegistrationPayment\RegistrationValidationException;
use App\Traits\CachesManagerResourceReads;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Registrations",
 *     description="(v1) PadelEvent registration management"
 * )
 */
class EventRegistrationController extends V1BaseController
{
    use CachesManagerResourceReads;

    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly RegistrationLifecycleService $lifecycleService,
        private readonly RegistrationQueueService $queueService,
        private readonly PricingService $pricingService,
        private readonly CheckoutService $checkoutService,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-registrations",
     *     summary="Get all edition registrations",
     *     tags={"🐧 Manager: PadelEvent Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="queue",
     *         in="query",
     *         required=false,
     *         description="Manager payment ops queue (awaiting_proof|refund_pending|abandoned_netopia)",
     *         @OA\Schema(type="string", enum={"awaiting_proof", "refund_pending", "abandoned_netopia"})
     *     ),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         required=false,
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(
     *             type="object",
     *             additionalProperties=false,
     *             @OA\Property(property="event_id", type="string"),
     *             @OA\Property(property="player_id", type="string"),
     *             @OA\Property(property="event_division_id", type="string"),
     *             @OA\Property(property="is_minor", type="string"),
     *             @OA\Property(property="is_student", type="string"),
     *             @OA\Property(property="payment", type="string")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         required=false,
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(
     *             type="object",
     *             additionalProperties=false,
     *             @OA\Property(property="id_registration", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="event_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="player_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="event_division_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="is_minor", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="is_student", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="payment", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'queue' => ['sometimes', 'nullable', 'string', Rule::in(RegistrationQueueService::allowedQueues())],
        ]);

        $queue = isset($validated['queue']) && is_string($validated['queue']) && $validated['queue'] !== ''
            ? $validated['queue']
            : null;

        $payload = $this->cachedManagerIndex('event-registrations', $request, ['event_id'], function () use ($request, $queue) {
            $query = EventRegistration::with(['player', 'eventDivision', 'event']);

            if ($queue !== null) {
                $this->queueService->applyQueue($query, $queue);
            }

            $this->applyAdvancedPagination(
                $query,
                $request,
                EventRegistration::$searchableFields,
                EventRegistration::$filterableFields,
                EventRegistration::$sortableFields
            );

            $paginationParams = $this->getPaginationParams($request);

            if (! $request->has('sort_by')) {
                $query->orderBy('id_registration', 'desc');
            }

            $registrations = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

            return EventRegistrationResource::collection($registrations)->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-registrations/{eventRegistration}",
     *     summary="Get edition registration details",
     *     tags={"🐧 Manager: PadelEvent Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventRegistration", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Registration not found")
     * )
     */
    public function show(EventRegistration $eventRegistration)
    {
        $eventRegistration->load(['player', 'eventDivision', 'event', 'checkoutItems']);

        return $this->resourceResponse(
            new EventRegistrationResource($eventRegistration),
            'Registration details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-registrations",
     *     summary="Create edition registration",
     *     tags={"🐧 Manager: PadelEvent Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event_id", "player_id"},
     *             @OA\Property(property="event_id", type="integer"),
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="event_division_id", type="integer", nullable=true),
     *             @OA\Property(property="is_minor", type="boolean"),
     *             @OA\Property(property="is_student", type="boolean"),
     *             @OA\Property(property="payment", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Registration created"),
     *     @OA\Response(response=409, description="PadelEvent is finalized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->storeRules($request->input('event_id')));

        $event = PadelEvent::query()->find($validated['event_id']);
        if ($event?->finalized_at !== null) {
            return $this->errorResponse('Registrations cannot be created after finalize.', 409);
        }

        $eventDivisionId = $this->nullableInt($validated, 'event_division_id');

        try {
            $payload = [
                'event_id' => (int) $validated['event_id'],
                'player_id' => (int) $validated['player_id'],
                'event_division_id' => $eventDivisionId,
                'is_minor' => $validated['is_minor'] ?? false,
                'is_student' => $validated['is_student'] ?? false,
            ];
            if (array_key_exists('payment', $validated) && $validated['payment'] !== null) {
                $payload['payment'] = (int) $validated['payment'];
            }
            $registration = $this->registrationService->createForManager($payload);
        } catch (RegistrationValidationException $e) {
            return $this->validationErrorResponse($e->errors);
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['player_id' => [$e->getMessage()]]);
        }

        $registration->load(['player', 'eventDivision', 'event']);

        return $this->resourceResponse(
            new EventRegistrationResource($registration),
            'Registration created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/event-registrations/{eventRegistration}",
     *     summary="Update edition registration (complete replacement)",
     *     tags={"🐧 Manager: PadelEvent Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventRegistration", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event_id", "player_id"},
     *             @OA\Property(property="event_id", type="integer"),
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="event_division_id", type="integer", nullable=true),
     *             @OA\Property(property="is_minor", type="boolean"),
     *             @OA\Property(property="is_student", type="boolean"),
     *             @OA\Property(property="payment", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Registration updated"),
     *     @OA\Response(response=409, description="PadelEvent is finalized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, EventRegistration $eventRegistration)
    {
        $eventRegistration->loadMissing('event');
        if ($eventRegistration->event?->finalized_at !== null) {
            return $this->errorResponse('Registrations cannot be updated after finalize.', 409);
        }

        $validated = $request->validate($this->updateRules($request->input('event_id')));

        $eventDivisionId = array_key_exists('event_division_id', $validated)
            ? $this->nullableInt($validated, 'event_division_id')
            : null;
        $xor = EventRegistration::xorConflict(
            (int) $validated['event_id'],
            (int) $validated['player_id'],
            $eventDivisionId,
            (int) $eventRegistration->id_registration
        );
        if ($xor !== null) {
            return $this->validationErrorResponse($xor);
        }

        $validated['event_division_id'] = $eventDivisionId;

        try {
            if (array_key_exists('payment', $validated)) {
                $this->registrationService->applyFeeChanges($eventRegistration, $validated);
            }
            $eventRegistration->fill($validated);
            $eventRegistration->save();
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['payment' => [$e->getMessage()]]);
        }

        $eventRegistration->load(['player', 'eventDivision', 'event']);

        return $this->resourceResponse(
            new EventRegistrationResource($eventRegistration),
            'Registration updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/event-registrations/{eventRegistration}",
     *     summary="Partially update edition registration",
     *     tags={"🐧 Manager: PadelEvent Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventRegistration", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="event_id", type="integer"),
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="event_division_id", type="integer", nullable=true),
     *             @OA\Property(property="is_minor", type="boolean"),
     *             @OA\Property(property="is_student", type="boolean"),
     *             @OA\Property(property="payment", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Registration partially updated"),
     *     @OA\Response(response=409, description="PadelEvent is finalized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function patch(Request $request, EventRegistration $eventRegistration)
    {
        $eventRegistration->loadMissing('event');
        if ($eventRegistration->event?->finalized_at !== null) {
            return $this->errorResponse('Registrations cannot be updated after finalize.', 409);
        }

        $targetEditionId = $request->input('event_id', $eventRegistration->event_id);
        $validated = $request->validate($this->patchRules($targetEditionId));

        $editionId = (int) ($validated['event_id'] ?? $eventRegistration->event_id);
        $playerId = (int) ($validated['player_id'] ?? $eventRegistration->player_id);
        $eventDivisionId = array_key_exists('event_division_id', $validated)
            ? $this->nullableInt($validated, 'event_division_id')
            : ($eventRegistration->event_division_id !== null ? (int) $eventRegistration->event_division_id : null);

        $xor = EventRegistration::xorConflict(
            $editionId,
            $playerId,
            $eventDivisionId,
            (int) $eventRegistration->id_registration
        );
        if ($xor !== null) {
            return $this->validationErrorResponse($xor);
        }

        if (array_key_exists('event_division_id', $validated)) {
            $validated['event_division_id'] = $eventDivisionId;
        }

        try {
            if (array_key_exists('payment', $validated)) {
                $this->registrationService->applyFeeChanges($eventRegistration, $validated);
            }
            $eventRegistration->fill($validated);
            $eventRegistration->save();
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['payment' => [$e->getMessage()]]);
        }

        $eventRegistration->load(['player', 'eventDivision', 'event']);

        return $this->resourceResponse(
            new EventRegistrationResource($eventRegistration),
            'Registration partially updated successfully'
        );
    }

    public function destroy()
    {
        return $this->errorResponse('Hard delete is not allowed. Use cancel or decline instead.', 405);
    }

    public function bulkDestroy()
    {
        return $this->errorResponse('Hard delete is not allowed. Use cancel or decline instead.', 405);
    }

    public function cancel(Request $request, EventRegistration $eventRegistration)
    {
        $validated = $request->validate([
            'cancel_reason' => ['required', Rule::enum(RegistrationCancelReason::class)],
        ]);

        try {
            $registration = $this->lifecycleService->cancelByManager(
                $eventRegistration,
                RegistrationCancelReason::from($validated['cancel_reason'])
            );
        } catch (InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'finalized')) {
                return $this->errorResponse($e->getMessage(), 409);
            }

            return $this->validationErrorResponse(['cancel_reason' => [$e->getMessage()]]);
        }

        $registration->load(['player', 'eventDivision', 'event']);

        return $this->resourceResponse(
            new EventRegistrationResource($registration),
            'Registration cancelled'
        );
    }

    public function decline(Request $request, EventRegistration $eventRegistration)
    {
        $validated = $request->validate([
            'decline_reason' => ['required', Rule::enum(RegistrationDeclineReason::class)],
        ]);

        try {
            $registration = $this->lifecycleService->declineByManager(
                $eventRegistration,
                RegistrationDeclineReason::from($validated['decline_reason'])
            );
        } catch (InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'finalized')) {
                return $this->errorResponse($e->getMessage(), 409);
            }

            return $this->validationErrorResponse(['decline_reason' => [$e->getMessage()]]);
        }

        $registration->load(['player', 'eventDivision', 'event']);

        return $this->resourceResponse(
            new EventRegistrationResource($registration),
            'Registration declined'
        );
    }

    public function markRefunded(EventRegistration $eventRegistration)
    {
        try {
            $registration = $this->lifecycleService->markRefunded($eventRegistration);
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['payment_status' => [$e->getMessage()]]);
        }

        $registration->load(['player', 'eventDivision', 'event']);

        return $this->resourceResponse(
            new EventRegistrationResource($registration),
            'Registration marked as refunded'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-registrations/{eventRegistration}/manual-adjustment",
     *     summary="Apply manual fee adjustment on unpaid registration",
     *     tags={"🐧 Manager: PadelEvent Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eventRegistration", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Adjustment applied"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function manualAdjustment(Request $request, EventRegistration $eventRegistration)
    {
        $validated = $request->validate([
            'amount_ron' => 'required|integer|min:0',
            'reason' => 'required|string|min:1|max:2000',
        ]);

        if ($eventRegistration->feesAreLocked()) {
            return $this->validationErrorResponse([
                'payment_status' => ['Manual adjustment is only allowed on unpaid registrations.'],
            ]);
        }

        $item = $eventRegistration->latestCheckoutItem;
        $itemStatus = $item?->item_status instanceof CheckoutItemStatus
            ? $item->item_status
            : ($item !== null ? CheckoutItemStatus::tryFrom((string) $item->item_status) : null);

        if ($item === null || $itemStatus !== CheckoutItemStatus::Active) {
            $checkout = $this->checkoutService->openOrRefreshForUnpaid(
                (int) $eventRegistration->event_id,
                (int) $eventRegistration->player_id
            );
            $item = $checkout->items()
                ->where('registration_id', $eventRegistration->id_registration)
                ->where('item_status', CheckoutItemStatus::Active->value)
                ->orderByDesc('id_checkout_item')
                ->first();
        }

        if ($item === null) {
            return $this->validationErrorResponse([
                'amount_ron' => ['No active unpaid checkout item found for this registration.'],
            ]);
        }

        try {
            $redemption = $this->pricingService->applyManualAdjustment(
                $item,
                (int) $validated['amount_ron'],
                (string) $validated['reason']
            );
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['amount_ron' => [$e->getMessage()]]);
        }

        $eventRegistration->refresh()->load([
            'player',
            'eventDivision',
            'event',
            'latestCheckoutItem.redemptions',
        ]);

        return $this->successResponse([
            'registration' => new EventRegistrationResource($eventRegistration),
            'redemption' => new BenefitRedemptionResource($redemption),
        ], 'Manual adjustment applied');
    }

    /**
     * @return array<string, mixed>
     */
    private function storeRules(mixed $editionId): array
    {
        return [
            'event_id' => 'required|exists:events,id_event',
            'player_id' => 'required|exists:players,id_player',
            'event_division_id' => $this->eventDivisionIdRules($editionId),
            'is_minor' => 'boolean',
            'is_student' => 'boolean',
            'payment' => 'nullable|integer|min:0',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(mixed $editionId): array
    {
        return [
            'event_id' => 'required|exists:events,id_event',
            'player_id' => 'required|exists:players,id_player',
            'event_division_id' => $this->eventDivisionIdRules($editionId),
            'is_minor' => 'required|boolean',
            'is_student' => 'required|boolean',
            'payment' => 'required|integer|min:0',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function patchRules(mixed $editionId): array
    {
        return [
            'event_id' => 'sometimes|exists:events,id_event',
            'player_id' => 'sometimes|exists:players,id_player',
            'event_division_id' => $this->eventDivisionIdRules($editionId, sometimes: true),
            'is_minor' => 'boolean',
            'is_student' => 'boolean',
            'payment' => 'nullable|integer|min:0',
        ];
    }

    /**
     * @return list<mixed>
     */
    private function eventDivisionIdRules(mixed $editionId, bool $sometimes = false): array
    {
        $rules = [];
        if ($sometimes) {
            $rules[] = 'sometimes';
        }
        $rules[] = 'required';
        $rules[] = Rule::exists('event_divisions', 'id_event_division')
            ->where('event_id', $editionId);

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function nullableInt(array $validated, string $key): ?int
    {
        if (! array_key_exists($key, $validated) || $validated[$key] === null) {
            return null;
        }

        return (int) $validated[$key];
    }
}
