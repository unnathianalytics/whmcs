<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Saas\Impersonation;
use Illuminate\Http\RedirectResponse;

/**
 * What: Entry/exit points for SaaS-admin impersonation of a company admin.
 * Why: The auth swap lives in the `Impersonation` service; this controller just authorizes and
 *      routes. `start` is guarded by `saas_admin` (only the platform owner may begin); `stop` is
 *      guarded by plain `auth` because, mid-impersonation, the authenticated user is the tenant —
 *      not a SaaS admin — yet must still be able to return.
 * When: Hit by the "Impersonate" action on the company detail screen and the "Stop impersonating"
 *       button in the persistent banner.
 */
class ImpersonationController extends Controller
{
    public function __construct(protected Impersonation $impersonation) {}

    /**
     * Begin impersonating the given company admin.
     */
    public function start(User $user): RedirectResponse
    {
        $this->impersonation->start($user);

        return redirect()->route('admin.dashboard');
    }

    /**
     * Restore the original SaaS admin.
     */
    public function stop(): RedirectResponse
    {
        $this->impersonation->stop();

        return redirect()->route('saas.companies');
    }
}
