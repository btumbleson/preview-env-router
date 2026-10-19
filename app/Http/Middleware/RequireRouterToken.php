<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRouterToken
{
    /**
     * Apps register previews with the shared ROUTER_TOKEN as a bearer token.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('router.token');

        abort_unless(filled($token) && filled(config('router.cloud.token')), 503, 'Router is not configured.');

        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401, 'Invalid router token.');

        return $next($request);
    }
}
