<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * What: Authorizes company-admin actions on tickets against the `tickets.*` permission catalog.
 * Why: Per the project authorization rule, gates check permissions (never role names). The permission check
 *      resolves against the request's tenant team id, which `EnsureCompanyAdmin` has already set, so a
 *      permission only counts within the user's own company. Replies and attachments authorize through the
 *      parent ticket's `update`/`view` here rather than a dedicated policy.
 * When: Consulted by `authorize()` calls in the Tickets Livewire components and the attachment download.
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.create');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.update');
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.delete');
    }

    public function restore(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.delete');
    }

    public function forceDelete(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.delete');
    }
}
