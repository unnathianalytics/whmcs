<?php

namespace App\Livewire\Admin\Domains;

use App\Enums\DomainStatus;
use App\Models\Client;
use App\Models\Domain;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use App\Support\ReminderDispatcher;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What: Company-admin screen to list, filter, create, edit, renew and delete domain registrations.
 * Why: Domains are tracked manually in v1 (no registrar API); this is the module's main workspace. All
 *      queries are tenant-isolated automatically by the BelongsToCompany scope. Dates are entered manually;
 *      the dedicated "Renew" action stamps the renewal date and advances expiry without forcing the admin
 *      through the full edit form.
 * When: Rendered at `/admin/domains` for company admins holding `domains.view`.
 */
#[Title('Domains')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    /** Expiry filter: '' | 'expiring' (≤30d, not expired) | 'expired'. */
    #[Url]
    public string $expiry = '';

    #[Url]
    public string $sortBy = 'expires_at';

    #[Url]
    public string $sortDirection = 'asc';

    // --- Form modal state ---
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $clientId = '';

    public string $domainName = '';

    public string $registrar = '';

    public string $statusField = DomainStatus::Active->value;

    public string $registeredAt = '';

    public string $expiresAt = '';

    public string $renewalCost = '';

    public string $currency = 'INR';

    public string $ns1 = '';

    public string $ns2 = '';

    public string $ns3 = '';

    public string $ns4 = '';

    public string $whoisNotes = '';

    // --- Renew modal state ---
    public bool $showRenewModal = false;

    public ?int $renewingId = null;

    public string $renewExpiresAt = '';

    public string $renewCost = '';

    // --- Delete modal state ---
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    /**
     * What: Authorize that the admin may view the domains list at all.
     * Why: The list is gated on `domains.view`; without it the screen 403s rather than leaking data.
     * When: On component mount, before any query runs.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Domain::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'clientId' => ['required', Rule::exists('clients', 'id')],
            'domainName' => ['required', 'string', 'max:255'],
            'registrar' => ['nullable', 'string', 'max:255'],
            'statusField' => ['required', Rule::enum(DomainStatus::class)],
            'registeredAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date', 'after_or_equal:registeredAt'],
            'renewalCost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'ns1' => ['nullable', 'string', 'max:255'],
            'ns2' => ['nullable', 'string', 'max:255'],
            'ns3' => ['nullable', 'string', 'max:255'],
            'ns4' => ['nullable', 'string', 'max:255'],
            'whoisNotes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingExpiry(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortBy = $column;
        $this->sortDirection = 'asc';
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Domain::class);
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $domainId): void
    {
        $domain = Domain::findOrFail($domainId);
        $this->authorize('update', $domain);

        $this->editingId = $domain->id;
        $this->clientId = (string) $domain->client_id;
        $this->domainName = $domain->domain_name;
        $this->registrar = (string) $domain->registrar;
        $this->statusField = $domain->status->value;
        $this->registeredAt = $domain->registered_at?->toDateString() ?? '';
        $this->expiresAt = $domain->expires_at?->toDateString() ?? '';
        $this->renewalCost = $domain->renewal_cost !== null ? (string) $domain->renewal_cost : '';
        $this->currency = $domain->currency;
        $this->ns1 = (string) $domain->ns1;
        $this->ns2 = (string) $domain->ns2;
        $this->ns3 = (string) $domain->ns3;
        $this->ns4 = (string) $domain->ns4;
        $this->whoisNotes = (string) $domain->whois_notes;

        $this->resetValidation();
        $this->showFormModal = true;
    }

    /**
     * What: Persist the domain — creating a new one or updating the one being edited.
     * Why: `company_id` is auto-stamped on create by BelongsToCompany; on edit the scoped lookup keeps
     *      tenants isolated. Dates are taken verbatim from the form (manual entry).
     * When: Triggered on submit of the form modal.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $attributes = [
            'client_id' => (int) $validated['clientId'],
            'domain_name' => $validated['domainName'],
            'registrar' => $validated['registrar'] ?: null,
            'status' => $validated['statusField'],
            'registered_at' => $validated['registeredAt'] ?: null,
            'expires_at' => $validated['expiresAt'] ?: null,
            'renewal_cost' => $validated['renewalCost'] !== '' ? $validated['renewalCost'] : null,
            'currency' => strtoupper($validated['currency']),
            'ns1' => $validated['ns1'] ?: null,
            'ns2' => $validated['ns2'] ?: null,
            'ns3' => $validated['ns3'] ?: null,
            'ns4' => $validated['ns4'] ?: null,
            'whois_notes' => $validated['whoisNotes'] ?: null,
        ];

        if ($this->editingId !== null) {
            $domain = Domain::findOrFail($this->editingId);
            $this->authorize('update', $domain);
            $domain->update($attributes);
            Flux::toast(variant: 'success', text: __('Domain updated.'));
        } else {
            $this->authorize('create', Domain::class);
            Domain::create($attributes);
            Flux::toast(variant: 'success', text: __('Domain created.'));
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    /**
     * What: Open the renew modal, pre-filling expiry one year past the current expiry (or one year from today).
     * Why: Manual renewal logging (idea.md) is a first-class action — admins renew far more often than they
     *      edit registrar/nameserver details, so it gets its own quick flow.
     * When: Triggered from the row's "Renew" menu item.
     */
    public function openRenewModal(int $domainId): void
    {
        $domain = Domain::findOrFail($domainId);
        $this->authorize('update', $domain);

        $base = $domain->expires_at !== null && $domain->expires_at->isFuture()
            ? $domain->expires_at
            : now();

        $this->renewingId = $domain->id;
        $this->renewExpiresAt = $base->copy()->addYear()->toDateString();
        $this->renewCost = $domain->renewal_cost !== null ? (string) $domain->renewal_cost : '';

        $this->resetValidation();
        $this->showRenewModal = true;
    }

    /**
     * What: Record a renewal — stamp `last_renewed_at` to today, set the new expiry and renewal cost, and
     *       flip an expired domain back to Active.
     * Why: Captures the manual renewal event the registrar performed out-of-band, and keeps the urgency badge
     *      and the Phase 7 reminder scan working off a fresh `expires_at`.
     * When: Triggered on submit of the renew modal.
     */
    public function renew(): void
    {
        $domain = Domain::findOrFail($this->renewingId);
        $this->authorize('update', $domain);

        $validated = $this->validate([
            'renewExpiresAt' => ['required', 'date'],
            'renewCost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $domain->update([
            'last_renewed_at' => now()->toDateString(),
            'expires_at' => $validated['renewExpiresAt'],
            'renewal_cost' => $validated['renewCost'] !== '' ? $validated['renewCost'] : $domain->renewal_cost,
            'status' => $domain->status === DomainStatus::Expired ? DomainStatus::Active : $domain->status,
        ]);

        // Re-arm reminders for the new cycle: clearing prior log rows lets the next interval send again.
        ReminderLog::where('remindable_type', $domain->getMorphClass())
            ->where('remindable_id', $domain->id)
            ->delete();

        $this->showRenewModal = false;
        $this->renewingId = null;

        Flux::toast(variant: 'success', text: __('Domain renewed.'));
    }

    public function confirmDelete(int $domainId): void
    {
        $domain = Domain::findOrFail($domainId);
        $this->authorize('delete', $domain);

        $this->deletingId = $domain->id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $domain = Domain::findOrFail($this->deletingId);
        $this->authorize('delete', $domain);
        $domain->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: __('Domain deleted.'));
    }

    /**
     * What: Immediately send this domain's expiry reminder(s) via the company's active domain rules.
     * Why: Admins sometimes need to nudge a client outside the daily schedule; the dispatcher honours the
     *      rule channels/templates but bypasses the days_before gate. Gated on `reminders.manage`.
     * When: Triggered from the row's "Send reminder" menu item.
     */
    public function sendReminder(int $domainId, ReminderDispatcher $dispatcher): void
    {
        $this->authorize('create', ReminderRule::class);

        $domain = Domain::with('client')->findOrFail($domainId);
        $sent = $dispatcher->sendNow(auth()->user()->company, $domain);

        Flux::toast(
            variant: $sent > 0 ? 'success' : 'warning',
            text: $sent > 0 ? __('Reminder sent.') : __('No active domain reminder rule to send.'),
        );
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'clientId', 'domainName', 'registrar', 'registeredAt', 'expiresAt',
            'renewalCost', 'ns1', 'ns2', 'ns3', 'ns4', 'whoisNotes',
        ]);
        $this->statusField = DomainStatus::Active->value;
        $this->currency = 'INR';
        $this->resetValidation();
    }

    /**
     * @return LengthAwarePaginator<int, Domain>
     */
    #[Computed]
    public function domains(): LengthAwarePaginator
    {
        $sortable = ['domain_name', 'registrar', 'status', 'registered_at', 'expires_at'];
        $sortBy = in_array($this->sortBy, $sortable, true) ? $this->sortBy : 'expires_at';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return Domain::query()
            ->with('client')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('domain_name', 'like', "%{$this->search}%")
                        ->orWhere('registrar', 'like', "%{$this->search}%")
                        ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->expiry === 'expired', fn ($query) => $query->whereNotNull('expires_at')->whereDate('expires_at', '<', now()))
            ->when($this->expiry === 'expiring', fn ($query) => $query
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '>=', now())
                ->whereDate('expires_at', '<=', now()->addDays(30)))
            ->orderBy($sortBy, $sortDirection)
            ->paginate(15);
    }

    /**
     * @return Collection<int, Client>
     */
    #[Computed]
    public function clients(): Collection
    {
        return Client::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<int, DomainStatus>
     */
    #[Computed]
    public function statuses(): array
    {
        return DomainStatus::cases();
    }

    public function render()
    {
        return view('livewire.admin.domains.index');
    }
}
