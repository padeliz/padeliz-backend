<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetReferenceResourceEtag
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->isSuccessful()) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return $response;
        }

        $etag = '"'.hash('sha256', $content).'"';
        $response->headers->set('ETag', $etag);

        $ifNoneMatch = $request->headers->get('If-None-Match');

        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
            $response->setStatusCode(304);
            $response->setContent('');
        }

        return $response;
    }
}
