<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireDashboardPassword
{
    /**
     * The status page lists every preview and its hostnames, so it requires
     * ROUTER_PASSWORD via HTTP basic auth (any username).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $password = config('router.password');

        abort_unless(filled($password) && filled(config('router.cloud.token')), 503, 'Router is not configured.');

        if (! hash_equals($password, (string) $request->getPassword())) {
            return response('Authentication required.', 401, [
                'WWW-Authenticate' => 'Basic realm="Preview router"',
            ]);
        }

        return $next($request);
    }
}
