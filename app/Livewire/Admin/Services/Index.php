<?php

namespace App\Livewire\Admin\Services;

use App\Enums\BillingCycle;
use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Product;
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
 * What: Company-admin screen to list, filter, assign, edit and delete client services across all clients.
 * Why: Services are the operational record behind billing and renewals; this is the module's main
 *      workspace. All queries are tenant-isolated automatically by the BelongsToCompany scope. Dates are
 *      entered manually (per the Phase 3 decision); selecting a product pricing only pre-fills the price
 *      and cycle, which the admin can override.
 * When: Rendered at `/admin/services` for company admins holding `services.view`.
 */
#[Title('Services')]
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

    public string $productId = '';

    public string $label = '';

    public string $statusField = ServiceStatus::Pending->value;

    public string $billingCycle = BillingCycle::Monthly->value;

    public string $price = '0';

    public string $currency = 'INR';

    public string $startsAt = '';

    public string $expiresAt = '';

    public string $nextDueDate = '';

    public string $notes = '';

    // --- Delete modal state ---
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    /**
     * What: Authorize that the admin may view the services list at all.
     * Why: The list is gated on `services.view`; without it the screen 403s rather than leaking data.
     * When: On component mount, before any query runs.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', ClientService::class);
        $this->startsAt = now()->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'clientId' => ['required', Rule::exists('clients', 'id')],
            'productId' => ['nullable', Rule::exists('products', 'id')],
            'label' => ['nullable', 'string', 'max:255'],
            'statusField' => ['required', Rule::enum(ServiceStatus::class)],
            'billingCycle' => ['required', Rule::enum(BillingCycle::class)],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'startsAt' => ['required', 'date'],
            'expiresAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'nextDueDate' => ['nullable', 'date'],
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

    public function updatingExpiry(): void
    {
        $this->resetPage();
    }

    /**
     * What: When a product is chosen on the form, pre-fill price/cycle from its first pricing row.
     * Why: Saves typing while keeping dates manual — the admin can still override every field.
     * When: Triggered by Livewire when `productId` changes.
     */
    public function updatedProductId(string $value): void
    {
        if ($value === '') {
            return;
        }

        $product = Product::with('pricings')->find($value);
        $pricing = $product?->pricings->first();

        if ($pricing !== null) {
            $this->price = (string) $pricing->price;
            $this->currency = $pricing->currency;
            $this->billingCycle = $pricing->cycle->value;
        }
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
        $this->authorize('create', ClientService::class);
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $serviceId): void
    {
        $service = ClientService::findOrFail($serviceId);
        $this->authorize('update', $service);

        $this->editingId = $service->id;
        $this->clientId = (string) $service->client_id;
        $this->productId = (string) ($service->product_id ?? '');
        $this->label = (string) $service->label;
        $this->statusField = $service->status->value;
        $this->billingCycle = $service->billing_cycle->value;
        $this->price = (string) $service->price;
        $this->currency = $service->currency;
        $this->startsAt = $service->starts_at->toDateString();
        $this->expiresAt = $service->expires_at?->toDateString() ?? '';
        $this->nextDueDate = $service->next_due_date?->toDateString() ?? '';
        $this->notes = (string) $service->notes;

        $this->resetValidation();
        $this->showFormModal = true;
    }

    /**
     * What: Persist the service — creating a new one or updating the one being edited.
     * Why: `company_id` is auto-stamped on create by BelongsToCompany; on edit the scoped lookup keeps
     *      tenants isolated. Dates are taken verbatim from the form (manual entry).
     * When: Triggered on submit of the form modal.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $attributes = [
            'client_id' => (int) $validated['clientId'],
            'product_id' => $validated['productId'] ? (int) $validated['productId'] : null,
            'label' => $validated['label'] ?: null,
            'status' => $validated['statusField'],
            'billing_cycle' => $validated['billingCycle'],
            'price' => $validated['price'],
            'currency' => strtoupper($validated['currency']),
            'starts_at' => $validated['startsAt'],
            'expires_at' => $validated['expiresAt'] ?: null,
            'next_due_date' => $validated['nextDueDate'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ];

        if ($this->editingId !== null) {
            $service = ClientService::findOrFail($this->editingId);
            $this->authorize('update', $service);
            $service->update($attributes);
            Flux::toast(variant: 'success', text: __('Service updated.'));
        } else {
            $this->authorize('create', ClientService::class);
            ClientService::create($attributes);
            Flux::toast(variant: 'success', text: __('Service created.'));
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function confirmDelete(int $serviceId): void
    {
        $service = ClientService::findOrFail($serviceId);
        $this->authorize('delete', $service);

        $this->deletingId = $service->id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $service = ClientService::findOrFail($this->deletingId);
        $this->authorize('delete', $service);
        $service->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: __('Service deleted.'));
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'clientId', 'productId', 'label', 'price', 'expiresAt', 'nextDueDate', 'notes',
        ]);
        $this->statusField = ServiceStatus::Pending->value;
        $this->billingCycle = BillingCycle::Monthly->value;
        $this->currency = 'INR';
        $this->price = '0';
        $this->startsAt = now()->toDateString();
        $this->resetValidation();
    }

    /**
     * @return LengthAwarePaginator<int, ClientService>
     */
    #[Computed]
    public function services(): LengthAwarePaginator
    {
        $sortable = ['label', 'status', 'price', 'starts_at', 'expires_at', 'next_due_date'];
        $sortBy = in_array($this->sortBy, $sortable, true) ? $this->sortBy : 'expires_at';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return ClientService::query()
            ->with(['client', 'product'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('label', 'like', "%{$this->search}%")
                        ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->search}%"));
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
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        return Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<int, ServiceStatus>
     */
    #[Computed]
    public function statuses(): array
    {
        return ServiceStatus::cases();
    }

    /**
     * @return array<int, BillingCycle>
     */
    #[Computed]
    public function cycles(): array
    {
        return BillingCycle::cases();
    }

    public function render()
    {
        return view('livewire.admin.services.index');
    }
}
