<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Enums\Registration\RegistrationCancelReason;
use App\Http\Resources\EventRegistrationResource;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\PadelEvent;
use App\Services\PlayerLevel\PlayerLevelService;
use App\Services\RegistrationPayment\RegistrationLifecycleService;
use App\Services\RegistrationPayment\RegistrationService;
use App\Services\RegistrationPayment\RegistrationValidationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * @OA\Tag(
 *     name="🐥 App: PadelEvent Registrations",
 *     description="(v1) Player edition registration"
 * )
 */
class EventRegistrationController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly RegistrationLifecycleService $lifecycleService,
        private readonly \App\Services\RegistrationPayment\CheckoutService $checkoutService,
        private readonly PlayerLevelService $playerLevels,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/event-registrations",
     *     summary="List own edition registrations",
     *     tags={"🐥 App: PadelEvent Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="event_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'nullable|integer|exists:events,id_event',
        ]);

        $query = EventRegistration::query()
            ->with(['event.location', 'event.divisions.division', 'eventDivision', 'checkoutItems'])
            ->where('player_user_id', $request->user()->id_user)
            ->orderByDesc('id_registration');

        if (isset($validated['event_id'])) {
            $query->where('event_id', (int) $validated['event_id']);
        }

        return $this->resourceResponse(
            EventRegistrationResource::collection($query->get()),
            'Registrations retrieved'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/event-registrations",
     *     summary="Register for an edition",
     *     tags={"🐥 App: PadelEvent Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event_id"},
     *             @OA\Property(property="event_id", type="integer"),
     *             @OA\Property(property="event_division_id", type="integer"),
     *             @OA\Property(property="event_division_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Registration created"),
     *     @OA\Response(response=403, description="Player has no level or level is outside the division bounds (Assess my level)"),
     *     @OA\Response(response=422, description="Validation error or registration closed")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id_event',
        ]);

        $event = PadelEvent::query()->findOrFail((int) $request->input('event_id'));

        if (time() >= (int) $event->registration_allowed_until) {
            return $this->validationErrorResponse(['event' => ['Registration is closed for this edition']]);
        }

        $currentLevel = $this->playerLevels->currentLevel((int) $request->user()->id_user);

        if ($currentLevel === null) {
            return $this->forbiddenResponse('You do not have a player level yet. Please use Assess my level first.');
        }

        $validated = $request->validate([
            'event_division_id' => [
                'required_without:event_division_ids',
                'integer',
                Rule::exists('event_divisions', 'id_event_division')
                    ->where('event_id', $event->id_event),
            ],
            'event_division_ids' => ['required_without:event_division_id', 'array', 'min:1'],
            'event_division_ids.*' => [
                'required',
                'integer',
                Rule::exists('event_divisions', 'id_event_division')
                    ->where('event_id', $event->id_event),
            ],
        ]);

        $playerId = (int) $request->user()->id_user;

        $requestedDivisionIds = array_key_exists('event_division_ids', $validated)
            ? array_map(static fn ($id) => (int) $id, $validated['event_division_ids'])
            : [(int) $validated['event_division_id']];

        $outOfBand = $this->divisionsOutsideLevel($requestedDivisionIds, (string) $currentLevel->level);
        if ($outOfBand !== []) {
            return $this->forbiddenResponse(
                'Your level (' . $currentLevel->level . ') is outside the allowed level range for: '
                . implode(', ', $outOfBand) . '. Please use Assess my level.'
            );
        }

        try {
            if (array_key_exists('event_division_ids', $validated)) {
                $divisionIds = array_map(
                    static fn ($id) => (int) $id,
                    $validated['event_division_ids']
                );
                $result = $this->checkoutService->createManyForApp(
                    (int) $event->id_event,
                    $playerId,
                    $divisionIds
                );

                $registrations = collect($result['registrations'])->each(
                    fn ($r) => $r->load(['event.location', 'event.divisions.division', 'eventDivision', 'checkoutItems.redemptions'])
                );

                return $this->successResponse([
                    'registrations' => EventRegistrationResource::collection($registrations)->resolve(),
                    'checkout' => (new \App\Http\Resources\EventRegistrationCheckoutResource(
                        $result['checkout']->load(['items.redemptions', 'items.registration.eventDivision'])
                    ))->resolve(),
                ], 'Registrations created', 201);
            }

            $registration = $this->registrationService->createForApp([
                'event_id' => (int) $event->id_event,
                'player_user_id' => $playerId,
                'event_division_id' => (int) $validated['event_division_id'],
            ]);
        } catch (RegistrationValidationException $e) {
            return $this->validationErrorResponse($e->errors);
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['event_id' => [$e->getMessage()]]);
        }

        $registration->load(['event.location', 'event.divisions.division', 'eventDivision', 'checkoutItems.redemptions']);

        return $this->resourceResponse(
            new EventRegistrationResource($registration),
            'Registration created',
            201
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/app/event-registrations/{eventRegistration}",
     *     summary="Cancel own registration",
     *     tags={"🐥 App: PadelEvent Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="eventRegistration",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cancel_reason"},
     *             @OA\Property(property="cancel_reason", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Registration cancelled"),
     *     @OA\Response(response=404, description="Registration not found"),
     *     @OA\Response(response=422, description="Cancel not allowed")
     * )
     */
    public function destroy(Request $request, int $eventRegistration)
    {
        $registration = EventRegistration::query()
            ->where('id_registration', $eventRegistration)
            ->where('player_user_id', $request->user()->id_user)
            ->firstOrFail();

        $validated = $request->validate([
            'cancel_reason' => ['required', Rule::enum(RegistrationCancelReason::class)],
        ]);

        try {
            $this->lifecycleService->cancelByPlayer(
                $registration,
                RegistrationCancelReason::from($validated['cancel_reason'])
            );
        } catch (InvalidArgumentException $e) {
            return $this->validationErrorResponse(['cancel_reason' => [$e->getMessage()]]);
        }

        $registration->refresh()->load(['event.location', 'event.divisions.division', 'eventDivision', 'checkoutItems']);

        return $this->resourceResponse(
            new EventRegistrationResource($registration),
            'Registration cancelled'
        );
    }

    /**
     * Bounds run weakest (level_min) to strongest (level_max); only enforced when both are set.
     *
     * @param  list<int>  $eventDivisionIds
     * @return list<string> division titles the level does not fit
     */
    private function divisionsOutsideLevel(array $eventDivisionIds, string $level): array
    {
        $value = (float) $level;
        $titles = [];

        foreach (EventDivision::query()->whereIn('id_event_division', $eventDivisionIds)->get() as $division) {
            if (! PlayerLevelService::levelFitsBounds($value, $division->level_min, $division->level_max)) {
                $titles[] = (string) ($division->division_title ?? $division->id_event_division);
            }
        }

        return $titles;
    }
}
