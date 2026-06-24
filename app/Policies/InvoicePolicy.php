<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/**
 * What: Authorizes company-admin actions on invoices against the `invoices.*` permission set.
 * Why: Per the project authorization rule, gates check permissions (never role names). The permission
 *      resolves against the request's tenant team id (set by the AuthCompanyTeamResolver), so a
 *      permission only counts within the user's own company.
 * When: Consulted by `authorize()` calls in the Invoices Livewire components for every read/write.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.update');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.delete');
    }
}
