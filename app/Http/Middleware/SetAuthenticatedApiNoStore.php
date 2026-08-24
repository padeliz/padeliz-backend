<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAuthenticatedApiNoStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() !== null && str_starts_with($request->path(), 'api/')) {
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
