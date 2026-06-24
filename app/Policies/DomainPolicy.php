<?php

namespace App\Policies;

use App\Models\Domain;
use App\Models\User;

/**
 * What: Authorizes company-admin actions on domains against the `domains.*` permission catalog.
 * Why: Per the project authorization rule, gates check permissions (never role names). The permission check
 *      resolves against the request's tenant team id, which `EnsureCompanyAdmin` has already set, so a
 *      permission only counts within the user's own company. The "renew" action authorizes through `update`.
 * When: Consulted by `authorize()` calls in the Domains Livewire component and the client profile.
 */
class DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('domains.view');
    }

    public function view(User $user, Domain $domain): bool
    {
        return $user->can('domains.view');
    }

    public function create(User $user): bool
    {
        return $user->can('domains.create');
    }

    public function update(User $user, Domain $domain): bool
    {
        return $user->can('domains.update');
    }

    public function delete(User $user, Domain $domain): bool
    {
        return $user->can('domains.delete');
    }

    public function restore(User $user, Domain $domain): bool
    {
        return $user->can('domains.delete');
    }

    public function forceDelete(User $user, Domain $domain): bool
    {
        return $user->can('domains.delete');
    }
}
