<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * What: Authorizes company-admin actions on the product catalog against the `services.*` permission set.
 * Why: Per the project authorization rule, gates check permissions (never role names). Products, groups
 *      and pricing are all part of the Services module, so they share the `services.*` catalog. The
 *      permission resolves against the request's tenant team id (set by the AuthCompanyTeamResolver), so a
 *      permission only counts within the user's own company.
 * When: Consulted by `authorize()` calls in the Products Livewire component for every read/write.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('services.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('services.view');
    }

    public function create(User $user): bool
    {
        return $user->can('services.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('services.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('services.delete');
    }
}
