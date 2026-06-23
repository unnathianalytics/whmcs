<?php

namespace App\Policies;

use App\Models\ClientService;
use App\Models\User;

/**
 * What: Authorizes company-admin actions on client services against the `services.*` permission set.
 * Why: Per the project authorization rule, gates check permissions (never role names). The permission
 *      resolves against the request's tenant team id (set by the AuthCompanyTeamResolver), so a
 *      permission only counts within the user's own company.
 * When: Consulted by `authorize()` calls in the Services Livewire component for every read/write.
 */
class ClientServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('services.view');
    }

    public function view(User $user, ClientService $clientService): bool
    {
        return $user->can('services.view');
    }

    public function create(User $user): bool
    {
        return $user->can('services.create');
    }

    public function update(User $user, ClientService $clientService): bool
    {
        return $user->can('services.update');
    }

    public function delete(User $user, ClientService $clientService): bool
    {
        return $user->can('services.delete');
    }
}
