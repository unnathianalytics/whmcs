<?php

namespace App\Livewire\Admin\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\User;
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
 * What: Company-admin helpdesk queue — list/filter tickets and open a new one.
 * Why: This is the support workspace entry point. All queries are tenant-isolated automatically by the
 *      BelongsToCompany scope. Opening a ticket captures the header (client, department, priority, assignee,
 *      subject) plus the first message as the opening reply, then redirects to the thread. Gated on
 *      `tickets.*`.
 * When: Rendered at `/admin/tickets` for company admins holding `tickets.view`.
 */
#[Title('Tickets')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $priority = '';

    #[Url]
    public string $department = '';

    #[Url]
    public string $assignee = '';

    #[Url]
    public string $sortBy = 'last_reply_at';

    #[Url]
    public string $sortDirection = 'desc';

    // --- Create modal state ---
    public bool $showCreateModal = false;

    public string $clientId = '';

    public string $departmentId = '';

    public string $assignedTo = '';

    public string $subject = '';

    public string $priorityValue = TicketPriority::Medium->value;

    public string $message = '';

    // --- Delete modal state ---
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    /**
     * What: Authorize that the admin may view the ticket queue at all.
     * Why: The list is gated on `tickets.view`; without it the screen 403s rather than leaking data.
     * When: On component mount, before any query runs.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Ticket::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'clientId' => ['required', Rule::exists('clients', 'id')],
            'departmentId' => ['required', Rule::exists('ticket_departments', 'id')],
            'assignedTo' => ['nullable', Rule::exists('users', 'id')],
            'subject' => ['required', 'string', 'max:255'],
            'priorityValue' => ['required', Rule::enum(TicketPriority::class)],
            'message' => ['required', 'string', 'max:5000'],
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

    public function updatingPriority(): void
    {
        $this->resetPage();
    }

    public function updatingDepartment(): void
    {
        $this->resetPage();
    }

    public function updatingAssignee(): void
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
        $this->authorize('create', Ticket::class);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    /**
     * What: Open a new ticket — create the header, store the first message as the opening reply, redirect.
     * Why: A ticket is a conversation, so creation seeds the header (Open status, auto-stamped `company_id`,
     *      next per-tenant number) and records the opening message authored by the current admin, then hands
     *      off to the thread page. `last_reply_at` is set so the new ticket sorts to the top of the queue.
     * When: Triggered on submit of the create modal.
     */
    public function create()
    {
        $this->authorize('create', Ticket::class);

        $validated = $this->validate();

        $ticket = Ticket::create([
            'client_id' => (int) $validated['clientId'],
            'department_id' => (int) $validated['departmentId'],
            'assigned_to' => $validated['assignedTo'] !== '' ? (int) $validated['assignedTo'] : null,
            'number' => Ticket::nextNumber((int) auth()->user()->company_id),
            'subject' => $validated['subject'],
            'status' => TicketStatus::Open,
            'priority' => $validated['priorityValue'],
            'last_reply_at' => now(),
        ]);

        $reply = $ticket->replies()->make([
            'user_id' => auth()->id(),
            'body' => $validated['message'],
            'is_internal_note' => false,
        ]);
        $reply->company_id = $ticket->company_id;
        $reply->save();

        $this->showCreateModal = false;
        $this->resetForm();

        Flux::toast(variant: 'success', text: __('Ticket created.'));

        return $this->redirectRoute('admin.tickets.show', $ticket, navigate: true);
    }

    public function confirmDelete(int $ticketId): void
    {
        $ticket = Ticket::findOrFail($ticketId);
        $this->authorize('delete', $ticket);

        $this->deletingId = $ticket->id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $ticket = Ticket::findOrFail($this->deletingId);
        $this->authorize('delete', $ticket);
        $ticket->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: __('Ticket deleted.'));
    }

    protected function resetForm(): void
    {
        $this->reset(['clientId', 'departmentId', 'assignedTo', 'subject', 'message']);
        $this->priorityValue = TicketPriority::Medium->value;
        $this->resetValidation();
    }

    /**
     * @return LengthAwarePaginator<int, Ticket>
     */
    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        $sortable = ['number', 'subject', 'status', 'priority', 'last_reply_at', 'created_at'];
        $sortBy = in_array($this->sortBy, $sortable, true) ? $this->sortBy : 'last_reply_at';
        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return Ticket::query()
            ->with(['client', 'department', 'assignee'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('number', 'like', "%{$this->search}%")
                        ->orWhere('subject', 'like', "%{$this->search}%")
                        ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->priority !== '', fn ($query) => $query->where('priority', $this->priority))
            ->when($this->department !== '', fn ($query) => $query->where('department_id', $this->department))
            ->when($this->assignee !== '', fn ($query) => $query->where('assigned_to', $this->assignee))
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
     * @return Collection<int, TicketDepartment>
     */
    #[Computed]
    public function departments(): Collection
    {
        return TicketDepartment::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function admins(): Collection
    {
        return User::query()
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return array<int, TicketStatus>
     */
    #[Computed]
    public function statuses(): array
    {
        return TicketStatus::cases();
    }

    /**
     * @return array<int, TicketPriority>
     */
    #[Computed]
    public function priorities(): array
    {
        return TicketPriority::cases();
    }

    public function render()
    {
        return view('livewire.admin.tickets.index');
    }
}
