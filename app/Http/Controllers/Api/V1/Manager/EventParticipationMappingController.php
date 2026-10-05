<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Models\PadelEvent;
use App\Services\EventParticipationMapping\EventParticipationMappingException;
use App\Services\EventParticipationMapping\EventParticipationMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Participation Mapping",
 *     description="(v1) Map edition registrations to participations"
 * )
 */
class EventParticipationMappingController extends V1BaseController
{
    public function __construct(
        private readonly EventParticipationMappingService $mappingService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/events/{event}/participations/map/preview",
     *     summary="Preview eligibility to map registrations to participations",
     *     tags={"🐧 Manager: PadelEvent Participation Mapping"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Preview"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function preview(PadelEvent $event): JsonResponse
    {
        $event->loadMissing('divisions');

        return $this->successResponse(
            $this->mappingService->preview($event),
            'Participation mapping preview'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/events/{event}/participations/map",
     *     summary="Map edition registrations to participations",
     *     tags={"🐧 Manager: PadelEvent Participation Mapping"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="confirm_overwrite", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Mapped"),
     *     @OA\Response(response=409, description="Blocked or confirmation required", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function map(Request $request, PadelEvent $event): JsonResponse
    {
        $validated = $request->validate([
            'confirm_overwrite' => 'sometimes|boolean',
        ]);

        try {
            $result = $this->mappingService->map(
                $event,
                (bool) ($validated['confirm_overwrite'] ?? false)
            );
        } catch (EventParticipationMappingException $e) {
            return $this->errorResponse($e->getMessage(), $e->status, $e->errors);
        }

        return $this->successResponse($result, 'Participations mapped successfully');
    }
}
