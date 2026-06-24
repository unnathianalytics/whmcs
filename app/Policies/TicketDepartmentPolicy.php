<?php

namespace App\Policies;

use App\Models\TicketDepartment;
use App\Models\User;

/**
 * What: Authorizes company-admin actions on ticket departments against the `tickets.*` permission catalog.
 * Why: Departments are part of the helpdesk module, which has no separate permission set, so they reuse
 *      `tickets.*` (same decision Phase 4 took for tax rates reusing `invoices.*`). Gates check permissions,
 *      never role names; the check resolves against the request's tenant team id set by `EnsureCompanyAdmin`.
 * When: Consulted by `authorize()` calls in the TicketDepartments Livewire component for every read/write.
 */
class TicketDepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view');
    }

    public function view(User $user, TicketDepartment $department): bool
    {
        return $user->can('tickets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.create');
    }

    public function update(User $user, TicketDepartment $department): bool
    {
        return $user->can('tickets.update');
    }

    public function delete(User $user, TicketDepartment $department): bool
    {
        return $user->can('tickets.delete');
    }
}
