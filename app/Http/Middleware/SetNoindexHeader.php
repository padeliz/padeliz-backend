<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When NOINDEX=true, emit X-Robots-Tag on every response.
 */
class SetNoindexHeader
{
    public const X_ROBOTS_TAG = 'noindex, nofollow, noarchive, nosnippet, noimageindex';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (filter_var(config('app.noindex'), FILTER_VALIDATE_BOOLEAN)) {
            $response->headers->set('X-Robots-Tag', self::X_ROBOTS_TAG);
        }

        return $response;
    }
}
