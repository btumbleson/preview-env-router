<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRouterToken
{
    /**
     * Previews register with the shared ROUTER_TOKEN in X-Router-Token. The
     * Authorization header is left for Cloud's HTTP basic auth.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('router.token');

        abort_unless(filled($token) && filled(config('router.cloud.token')), 503, 'Router is not configured.');

        abort_unless(hash_equals($token, (string) $request->header('X-Router-Token')), 401, 'Invalid router token.');

        return $next($request);
    }
}
