<?php

namespace App\Policies;

use App\Models\ReminderRule;
use App\Models\User;

/**
 * What: Authorizes company-admin actions on reminder rules against the `reminders.*` permission catalog.
 * Why: Per the project authorization rule, gates check permissions (never role names). Viewing rules and the
 *      log viewer needs `reminders.view`; creating/editing/deleting rules and triggering a manual send needs
 *      `reminders.manage`. The permission check resolves against the request's tenant team id set by
 *      `EnsureCompanyAdmin`, so a permission only counts within the user's own company.
 * When: Consulted by `authorize()` calls in the Reminders Livewire component and the manual-send actions on
 *       the Services and Domains screens.
 */
class ReminderRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reminders.view');
    }

    public function view(User $user, ReminderRule $rule): bool
    {
        return $user->can('reminders.view');
    }

    public function create(User $user): bool
    {
        return $user->can('reminders.manage');
    }

    public function update(User $user, ReminderRule $rule): bool
    {
        return $user->can('reminders.manage');
    }

    public function delete(User $user, ReminderRule $rule): bool
    {
        return $user->can('reminders.manage');
    }

    public function restore(User $user, ReminderRule $rule): bool
    {
        return $user->can('reminders.manage');
    }

    public function forceDelete(User $user, ReminderRule $rule): bool
    {
        return $user->can('reminders.manage');
    }
}
