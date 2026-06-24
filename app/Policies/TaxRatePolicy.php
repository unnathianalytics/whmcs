<?php

namespace App\Policies;

use App\Models\TaxRate;
use App\Models\User;

/**
 * What: Authorizes company-admin actions on tax rates against the `invoices.*` permission set.
 * Why: idea.md groups tax-rate management under the invoices/billing module, and the seeded permission
 *      catalog has no dedicated `taxes.*` set, so tax-rate gates reuse `invoices.*` (gates check
 *      permissions, never role names). The permission resolves against the request's tenant team id, so it
 *      only counts within the user's own company.
 * When: Consulted by `authorize()` calls in the TaxRates Livewire component for every read/write.
 */
class TaxRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, TaxRate $taxRate): bool
    {
        return $user->can('invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create');
    }

    public function update(User $user, TaxRate $taxRate): bool
    {
        return $user->can('invoices.update');
    }

    public function delete(User $user, TaxRate $taxRate): bool
    {
        return $user->can('invoices.delete');
    }
}
