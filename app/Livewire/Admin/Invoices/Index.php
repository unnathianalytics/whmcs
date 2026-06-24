<?php

namespace App\Livewire\Admin\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
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
 * What: Company-admin screen to list and filter invoices and start a new one.
 * Why: This is the billing workspace entry point. All queries are tenant-isolated automatically by the
 *      BelongsToCompany scope. New invoices are created as a draft header here (client + dates) and the
 *      admin is redirected to the builder to add line items and record payments. Gated on `invoices.*`.
 * When: Rendered at `/admin/invoices` for company admins holding `invoices.view`.
 */
#[Title('Invoices')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $sortBy = 'issue_date';

    #[Url]
    public string $sortDirection = 'desc';

    // --- Create modal state ---
    public bool $showCreateModal = false;

    public string $clientId = '';

    public string $issueDate = '';

    public string $dueDate = '';

    public string $currency = 'INR';

    public string $notes = '';

    // --- Delete modal state ---
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    /**
     * What: Authorize that the admin may view the invoice list at all, and seed default dates.
     * Why: The list is gated on `invoices.view`; without it the screen 403s rather than leaking data.
     * When: On component mount, before any query runs.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Invoice::class);
        $this->issueDate = now()->toDateString();
        $this->dueDate = now()->addDays(14)->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'clientId' => ['required', Rule::exists('clients', 'id')],
            'issueDate' => ['required', 'date'],
            'dueDate' => ['required', 'date', 'after_or_equal:issueDate'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
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
        $this->authorize('create', Invoice::class);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    /**
     * What: Create a draft invoice header and redirect to the builder.
     * Why: Invoices are not simple CRUD (line items + payments), so creation seeds the header then hands
     *      off to the detail page. `company_id` is auto-stamped by BelongsToCompany; the number is the next
     *      per-tenant sequence. A client's default currency seeds the field when not overridden.
     * When: Triggered on submit of the create modal.
     */
    public function create()
    {
        $this->authorize('create', Invoice::class);

        $validated = $this->validate();

        $invoice = Invoice::create([
            'client_id' => (int) $validated['clientId'],
            'number' => Invoice::nextNumber((int) auth()->user()->company_id),
            'status' => InvoiceStatus::Draft,
            'issue_date' => $validated['issueDate'],
            'due_date' => $validated['dueDate'],
            'currency' => strtoupper($validated['currency']),
            'notes' => $validated['notes'] ?: null,
        ]);

        $this->showCreateModal = false;
        $this->resetForm();

        Flux::toast(variant: 'success', text: __('Invoice created.'));

        return $this->redirectRoute('admin.invoices.show', $invoice, navigate: true);
    }

    public function confirmDelete(int $invoiceId): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $this->authorize('delete', $invoice);

        $this->deletingId = $invoice->id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $invoice = Invoice::findOrFail($this->deletingId);
        $this->authorize('delete', $invoice);
        $invoice->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: __('Invoice deleted.'));
    }

    protected function resetForm(): void
    {
        $this->reset(['clientId', 'notes']);
        $this->currency = 'INR';
        $this->issueDate = now()->toDateString();
        $this->dueDate = now()->addDays(14)->toDateString();
        $this->resetValidation();
    }

    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        $sortable = ['number', 'status', 'issue_date', 'due_date', 'total'];
        $sortBy = in_array($this->sortBy, $sortable, true) ? $this->sortBy : 'issue_date';
        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return Invoice::query()
            ->with('client')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('number', 'like', "%{$this->search}%")
                        ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderBy($sortBy, $sortDirection)
            ->paginate(15);
    }

    /**
     * @return Collection<int, Client>
     */
    #[Computed]
    public function clients(): Collection
    {
        return Client::query()->orderBy('name')->get(['id', 'name', 'currency']);
    }

    /**
     * @return array<int, InvoiceStatus>
     */
    #[Computed]
    public function statuses(): array
    {
        return InvoiceStatus::cases();
    }

    public function render()
    {
        return view('livewire.admin.invoices.index');
    }
}
