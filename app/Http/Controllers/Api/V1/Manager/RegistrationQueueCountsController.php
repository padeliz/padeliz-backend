<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Services\Cache\PadelizCache;
use App\Services\RegistrationPayment\CheckoutQueueService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Registration Queues",
 *     description="(v1) Registration payment ops queue counts"
 * )
 */
class RegistrationQueueCountsController extends V1BaseController
{
    public function __construct(
        private readonly CheckoutQueueService $queueService,
        private readonly PadelizCache $cache,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/registration-queue-counts",
     *     summary="Get registration payment ops queue counts",
     *     tags={"🐧 Manager: Registration Queues"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function __invoke(): JsonResponse
    {
        $policy = config('cache_policies.registration_queue_counts');
        $tags = array_merge($policy['tags'] ?? [], ['mgr-list:event-registrations']);

        $counts = $this->cache->remember(
            'mgr:registration-queue-counts',
            (int) $policy['ttl'],
            $tags,
            fn (): array => $this->queueService->counts()
        );

        return response()->json(['data' => $counts]);
    }
}
