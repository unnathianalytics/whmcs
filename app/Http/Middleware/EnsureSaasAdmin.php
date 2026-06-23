<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What: Guards the `/saas/*` area so only the platform owner may enter.
 * Why: SaaS Admins manage tenants, plans and platform settings — a tenant admin must never reach
 *      these screens. Authorization keys off the single `is_saas_admin` flag.
 * When: Applied as the `saas_admin` route-middleware alias on the `/saas` route group.
 */
class EnsureSaasAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSaasAdmin() === true, 403);

        return $next($request);
    }
}
