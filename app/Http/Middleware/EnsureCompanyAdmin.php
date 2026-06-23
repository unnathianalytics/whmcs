<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * What: Guards the `/admin/*` area and binds the request to the user's tenant.
 * Why: Company admins may only enter when (a) they belong to a company, (b) that company is not
 *      suspended, and (c) its subscription is active/trialing. It also sets the spatie permission
 *      team id to the company so role checks resolve against tenant-scoped role assignments.
 * When: Applied as the `company_admin` route-middleware alias on the `/admin` route group; runs
 *       after `auth` so `$request->user()` is guaranteed present.
 */
class EnsureCompanyAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null || $user->isSaasAdmin(), 403);
        abort_if($user->company_id === null, 403);

        $company = $user->company;

        abort_if($company === null || $company->isSuspended(), 403, 'Your company account is suspended.');

        $subscription = $company->subscription;

        abort_unless(
            $company->onTrial() || ($subscription !== null && $subscription->isActive()),
            403,
            'Your company subscription is inactive.',
        );

        // Scope all spatie role/permission checks for this request to the user's company.
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->company_id);

        return $next($request);
    }
}
