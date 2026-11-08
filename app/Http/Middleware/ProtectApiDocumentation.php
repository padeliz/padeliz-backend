<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectApiDocumentation
{
    /**
     * Gate HTTP API documentation (Swagger UI, assets, and parsed docs).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('l5-swagger.ui_enabled')) {
            abort(404);
        }

        if (! config('l5-swagger.basic_auth.enabled')) {
            return $next($request);
        }

        $username = (string) config('l5-swagger.basic_auth.username');
        $password = (string) config('l5-swagger.basic_auth.password');

        if ($username === '' || $password === '') {
            abort(404);
        }

        $providedUser = (string) $request->getUser();
        $providedPassword = (string) $request->getPassword();

        if (
            ! hash_equals($username, $providedUser)
            || ! hash_equals($password, $providedPassword)
        ) {
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="API Documentation"',
            ]);
        }

        return $next($request);
    }
}
