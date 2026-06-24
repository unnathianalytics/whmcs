<?php

namespace App\Livewire\Admin\TicketDepartments;

use App\Models\TicketDepartment;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What: Company-admin screen to manage the tenant's ticket departments (name + active flag).
 * Why: Tickets are routed into departments; this is where admins maintain the buckets the ticket form picks
 *      from. All queries are tenant-isolated automatically by the BelongsToCompany scope. Departments share
 *      the `tickets.*` permission set (see TicketDepartmentPolicy).
 * When: Rendered at `/admin/ticket-departments` for company admins holding `tickets.view`.
 */
#[Title('Ticket Departments')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    // --- Form modal state ---
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public bool $isActive = true;

    // --- Delete modal state ---
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    /**
     * What: Authorize that the admin may view the departments list at all.
     * Why: The screen is gated on `tickets.view`; without it it 403s rather than leaking data.
     * When: On component mount, before any query runs.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', TicketDepartment::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'isActive' => ['boolean'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', TicketDepartment::class);
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $departmentId): void
    {
        $department = TicketDepartment::findOrFail($departmentId);
        $this->authorize('update', $department);

        $this->editingId = $department->id;
        $this->name = $department->name;
        $this->isActive = $department->is_active;

        $this->resetValidation();
        $this->showFormModal = true;
    }

    /**
     * What: Persist the department — creating a new one or updating the one being edited.
     * Why: `company_id` is auto-stamped on create by BelongsToCompany; on edit the scoped lookup keeps
     *      tenants isolated.
     * When: Triggered on submit of the form modal.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $attributes = [
            'name' => $validated['name'],
            'is_active' => $validated['isActive'],
        ];

        if ($this->editingId !== null) {
            $department = TicketDepartment::findOrFail($this->editingId);
            $this->authorize('update', $department);
            $department->update($attributes);
            Flux::toast(variant: 'success', text: __('Department updated.'));
        } else {
            $this->authorize('create', TicketDepartment::class);
            TicketDepartment::create($attributes);
            Flux::toast(variant: 'success', text: __('Department created.'));
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function confirmDelete(int $departmentId): void
    {
        $department = TicketDepartment::findOrFail($departmentId);
        $this->authorize('delete', $department);

        $this->deletingId = $department->id;
        $this->showDeleteModal = true;
    }

    /**
     * What: Soft-delete the confirmed department.
     * Why: Soft delete keeps history; tickets referencing it null their `department_id` on delete
     *      (nullOnDelete), so removing a department never breaks an existing ticket.
     * When: Triggered on confirm of the delete modal.
     */
    public function delete(): void
    {
        $department = TicketDepartment::findOrFail($this->deletingId);
        $this->authorize('delete', $department);
        $department->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: __('Department deleted.'));
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name']);
        $this->isActive = true;
        $this->resetValidation();
    }

    /**
     * @return LengthAwarePaginator<int, TicketDepartment>
     */
    #[Computed]
    public function departments(): LengthAwarePaginator
    {
        return TicketDepartment::query()
            ->withCount('tickets')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.admin.ticket-departments.index');
    }
}
