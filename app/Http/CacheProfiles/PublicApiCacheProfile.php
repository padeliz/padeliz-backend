<?php

declare(strict_types=1);

namespace App\Http\CacheProfiles;

use Illuminate\Http\Request;
use Spatie\ResponseCache\CacheProfiles\BaseCacheProfile;
use Symfony\Component\HttpFoundation\Response;

class PublicApiCacheProfile extends BaseCacheProfile
{
    public function shouldCacheRequest(Request $request): bool
    {
        if ($request->method() !== 'GET') {
            return false;
        }

        if ($request->headers->has('Authorization')) {
            return false;
        }

        if (filter_var($request->query('debug'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return true;
    }

    public function shouldCacheResponse(Response $response): bool
    {
        return $response->isSuccessful();
    }

    public function useCacheNameSuffix(Request $request): string
    {
        return $request->fullUrl();
    }
}
