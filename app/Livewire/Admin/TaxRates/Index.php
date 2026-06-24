<?php

namespace App\Livewire\Admin\TaxRates;

use App\Models\TaxRate;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What: Company-admin screen to manage the tenant's tax-rate catalog (name + percent + active flag).
 * Why: Phase 4 applies tax per invoice line item; this is where admins maintain the reusable rates they
 *      pick from on the invoice builder. All queries are tenant-isolated automatically by the
 *      BelongsToCompany scope. Tax rates share the `invoices.*` permission set (see TaxRatePolicy).
 * When: Rendered at `/admin/tax-rates` for company admins holding `invoices.view`.
 */
#[Title('Tax Rates')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    // --- Form modal state ---
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $rate = '0';

    public bool $isActive = true;

    // --- Delete modal state ---
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    /**
     * What: Authorize that the admin may view the tax-rate catalog at all.
     * Why: The screen is gated on `invoices.view`; without it it 403s rather than leaking data.
     * When: On component mount, before any query runs.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', TaxRate::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'isActive' => ['boolean'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', TaxRate::class);
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $taxRateId): void
    {
        $taxRate = TaxRate::findOrFail($taxRateId);
        $this->authorize('update', $taxRate);

        $this->editingId = $taxRate->id;
        $this->name = $taxRate->name;
        $this->rate = (string) $taxRate->rate;
        $this->isActive = $taxRate->is_active;

        $this->resetValidation();
        $this->showFormModal = true;
    }

    /**
     * What: Persist the tax rate — creating a new one or updating the one being edited.
     * Why: `company_id` is auto-stamped on create by BelongsToCompany; on edit the scoped lookup keeps
     *      tenants isolated.
     * When: Triggered on submit of the form modal.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $attributes = [
            'name' => $validated['name'],
            'rate' => $validated['rate'],
            'is_active' => $validated['isActive'],
        ];

        if ($this->editingId !== null) {
            $taxRate = TaxRate::findOrFail($this->editingId);
            $this->authorize('update', $taxRate);
            $taxRate->update($attributes);
            Flux::toast(variant: 'success', text: __('Tax rate updated.'));
        } else {
            $this->authorize('create', TaxRate::class);
            TaxRate::create($attributes);
            Flux::toast(variant: 'success', text: __('Tax rate created.'));
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function confirmDelete(int $taxRateId): void
    {
        $taxRate = TaxRate::findOrFail($taxRateId);
        $this->authorize('delete', $taxRate);

        $this->deletingId = $taxRate->id;
        $this->showDeleteModal = true;
    }

    /**
     * What: Soft-delete the confirmed tax rate.
     * Why: Soft delete keeps history; line items snapshot the percent and null their `tax_rate_id` on
     *      delete, so removing a rate never rewrites a historical invoice.
     * When: Triggered on confirm of the delete modal.
     */
    public function delete(): void
    {
        $taxRate = TaxRate::findOrFail($this->deletingId);
        $this->authorize('delete', $taxRate);
        $taxRate->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: __('Tax rate deleted.'));
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name']);
        $this->rate = '0';
        $this->isActive = true;
        $this->resetValidation();
    }

    /**
     * @return LengthAwarePaginator<int, TaxRate>
     */
    #[Computed]
    public function taxRates(): LengthAwarePaginator
    {
        return TaxRate::query()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.admin.tax-rates.index');
    }
}
