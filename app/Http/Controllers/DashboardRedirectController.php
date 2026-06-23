<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * What: Routes a freshly-authenticated user to the correct home area.
 * Why: Fortify redirects everyone to `/dashboard` after login, but the two tiers have different
 *      homes — SaaS admins belong in `/saas`, company admins in `/admin`. This single hop keeps
 *      Fortify's config untouched while still landing each user in the right place.
 * When: Hit on every visit to the `dashboard` named route (e.g. immediately after login).
 */
class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->isSaasAdmin()) {
            return redirect()->route('saas.dashboard');
        }

        return redirect()->route('admin.dashboard');
    }
}
