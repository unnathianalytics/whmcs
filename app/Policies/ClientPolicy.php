<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * What: Authorizes company-admin actions on clients against the `clients.*` permission catalog.
 * Why: Per the project authorization rule, gates check permissions (never role names). The permission
 *      check resolves against the request's tenant team id, which `EnsureCompanyAdmin` has already set,
 *      so a permission only counts within the user's own company.
 * When: Consulted by `authorize()` calls in the Clients Livewire components for every read/write.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        return $user->can('clients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can('clients.update');
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->can('clients.delete');
    }

    public function restore(User $user, Client $client): bool
    {
        return $user->can('clients.delete');
    }

    public function forceDelete(User $user, Client $client): bool
    {
        return $user->can('clients.delete');
    }
}
