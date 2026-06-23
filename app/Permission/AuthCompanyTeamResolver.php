<?php

namespace App\Permission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * What: Resolves the spatie "team id" (our `company_id`) for every role/permission lookup, defaulting
 *       to the authenticated company admin's company when nothing was explicitly set.
 * Why: Roles/permissions are team-scoped (`team_id = company_id`). A request-bound team id (set by
 *      `EnsureCompanyAdmin`) does not survive into Livewire's `/livewire/update` calls, which skip that
 *      middleware — leaving the default resolver's id null and making every permission check fail (403).
 *      Resolving live from `Auth` makes `$user->can('clients.create')` correct on page loads, Livewire
 *      updates, and any other authenticated context, while still honouring an explicit override.
 * When: Wired via `config('permission.team_resolver')`; consulted by spatie on every permission check.
 */
class AuthCompanyTeamResolver implements PermissionsTeamResolver
{
    protected int|string|null $teamId = null;

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        $this->teamId = $id;
    }

    public function getPermissionsTeamId(): int|string|null
    {
        // An explicitly-set id (seeders, onboarding, tests scoping to a specific company) wins.
        if ($this->teamId !== null) {
            return $this->teamId;
        }

        // Otherwise fall back to the authenticated company admin's company so permission checks resolve
        // correctly even on requests that never ran the company_admin middleware (e.g. Livewire updates).
        $user = Auth::user();

        if ($user instanceof User && ! $user->isSaasAdmin()) {
            return $user->company_id;
        }

        return null;
    }
}
