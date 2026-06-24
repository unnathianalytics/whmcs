<?php

namespace App\Services\Saas;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * What: Encapsulates the SaaS Admin "log in as a company admin" auth swap and its reversal.
 * Why: Impersonation mutates the authenticated user and stashes the original admin id in the
 *      session; isolating that logic keeps the controller thin and the behaviour unit-testable, and
 *      gives a single place that guarantees the original admin id is always restored exactly once.
 * When: `start()` from the SaaS company detail screen; `stop()` from the persistent impersonation
 *       banner rendered while the SaaS admin is browsing the tenant panel.
 */
class Impersonation
{
    /**
     * The session key holding the impersonator's (original SaaS admin) user id.
     */
    public const SESSION_KEY = 'impersonator_id';

    /**
     * What: Begin impersonating a company admin, stashing the current user as the impersonator.
     * Why: Lets the platform owner debug a tenant exactly as one of its admins sees it, while
     *      keeping a breadcrumb back to their real identity. Refuses to target another platform
     *      owner or a user with no company, since neither is a valid tenant context.
     * When: Invoked from `ImpersonationController@start` after authorizing the SaaS admin.
     */
    public function start(User $target): void
    {
        $impersonator = Auth::user();

        abort_if($impersonator === null || ! $impersonator->isSaasAdmin(), 403);
        abort_if($target->isSaasAdmin() || $target->company_id === null, 403, 'This user cannot be impersonated.');

        Session::put(self::SESSION_KEY, $impersonator->getKey());

        activity()
            ->performedOn($target)
            ->causedBy($impersonator)
            ->withProperties(['company_id' => $target->company_id])
            ->log('Started impersonating company admin');

        Auth::login($target);
    }

    /**
     * What: Restore the original SaaS admin and clear the impersonation stash.
     * Why: Ends the debug session and returns the platform owner to their own identity; no-op if
     *      no impersonation is active so the route is safe to hit directly.
     * When: Invoked from `ImpersonationController@stop` via the banner's "Stop impersonating" button.
     */
    public function stop(): void
    {
        $impersonatorId = Session::pull(self::SESSION_KEY);

        if ($impersonatorId === null) {
            return;
        }

        $impersonator = User::find($impersonatorId);

        if ($impersonator === null) {
            return;
        }

        activity()
            ->causedBy($impersonator)
            ->log('Stopped impersonating company admin');

        Auth::login($impersonator);
    }

    /**
     * What: Whether the current request is running inside an impersonation session.
     * Why: Drives the persistent banner and guards `stop` against being a no-op surprise.
     * When: Read by the layout and the controller.
     */
    public function isImpersonating(): bool
    {
        return Session::has(self::SESSION_KEY);
    }
}
