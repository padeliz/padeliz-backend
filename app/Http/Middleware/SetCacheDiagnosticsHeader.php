<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Cache\PadelizCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCacheDiagnosticsHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! str_starts_with($request->path(), 'api/')) {
            return $response;
        }

        $status = $request->attributes->get(PadelizCache::STATUS_REQUEST_KEY);

        if ($status !== null) {
            $response->headers->set('X-Cache', (string) $status);
        }

        return $response;
    }
}
