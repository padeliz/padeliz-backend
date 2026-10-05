<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EventResource;
use App\Models\PadelEvent;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Events",
 *     description="(v1) PadelEvent management and operations"
 * )
 */
class EventFinalizeController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/manager/events/{event}/finalize",
     *     summary="Finalize an edition (locks schedule/scores/standings/replacements/participations/lottery live)",
     *     tags={"🐧 Manager: Events"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Finalized"),
     *     @OA\Response(response=409, description="Already finalized")
     * )
     */
    public function store(PadelEvent $event)
    {
        if ($event->finalized_at !== null) {
            return $this->errorResponse('PadelEvent is already finalized.', 409);
        }

        $event->update([
            'finalized_at' => time(),
        ]);

        $event->load(['location', 'divisions', 'participations', 'lotteries']);

        return $this->resourceResponse(
            new EventResource($event),
            'PadelEvent finalized successfully'
        );
    }
}
