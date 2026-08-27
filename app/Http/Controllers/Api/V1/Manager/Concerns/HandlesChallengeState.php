<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager\Concerns;

use App\Services\Challenge\ChallengeStateException;
use Closure;
use Illuminate\Http\JsonResponse;

trait HandlesChallengeState
{
    /**
     * @template T
     * @param Closure(): T $callback
     * @return JsonResponse|T
     */
    protected function challengeResponse(Closure $callback): JsonResponse|array
    {
        try {
            return $callback();
        } catch (ChallengeStateException $e) {
            return response()->json(['message' => $e->getMessage()], $e->httpStatus);
        }
    }
}
