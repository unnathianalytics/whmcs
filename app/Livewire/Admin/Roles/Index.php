<?php

namespace App\Livewire\Admin\Roles;

use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * What: The company-admin Role–Permission screen — create/rename/delete tenant roles and sync their
 *       permissions via a grouped checkbox matrix.
 * Why: idea.md Phase 8 requires a UI to manage RBAC. Roles are team-scoped (the EnsureCompanyAdmin
 *      middleware sets the spatie team id to the current company), so every role read/write here is
 *      automatically isolated to the admin's own tenant. Gates check the `roles.*` permissions, never
 *      role names.
 * When: Rendered at `/admin/roles` for company admins holding `roles.view`; mutations require
 *       `roles.manage`.
 */
#[Title('Roles & Permissions')]
class Index extends Component
{
    // --- Form modal state ---
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $name = '';

    /** @var array<int, string> Permission names currently checked in the matrix. */
    public array $selectedPermissions = [];

    // --- Delete modal state ---
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    /**
     * What: Authorize that the admin may view roles at all.
     * When: On component mount.
     */
    public function mount(): void
    {
        abort_unless(Auth::user()->can('roles.view'), 403);
    }

    public function openCreateModal(): void
    {
        abort_unless(Auth::user()->can('roles.manage'), 403);
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $roleId): void
    {
        abort_unless(Auth::user()->can('roles.manage'), 403);

        $role = $this->findTenantRole($roleId);

        $this->editingId = (int) $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->all();

        $this->resetValidation();
        $this->showFormModal = true;
    }

    /**
     * What: Persist the role and sync its permissions.
     * Why: `findOrCreate`/`update` runs under the current team id so the role belongs to this tenant only;
     *      `syncPermissions` replaces the role's grant set with the checked matrix in one write. Only
     *      catalog permissions are synced so the form can never grant an unknown string.
     * When: On submit of the role form modal.
     */
    public function save(): void
    {
        abort_unless(Auth::user()->can('roles.manage'), 403);

        $teamId = $this->teamId();

        $validated = $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->where('team_id', $teamId)
                    ->ignore($this->editingId),
            ],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string'],
        ]);

        $permissions = array_values(array_intersect($validated['selectedPermissions'], $this->permissionCatalog()));

        if ($this->editingId !== null) {
            $role = $this->findTenantRole($this->editingId);
            $role->update(['name' => $validated['name']]);
            Flux::toast(variant: 'success', text: __('Role updated.'));
        } else {
            $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
            Flux::toast(variant: 'success', text: __('Role created.'));
        }

        $role->syncPermissions($permissions);

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->roles);
    }

    public function confirmDelete(int $roleId): void
    {
        abort_unless(Auth::user()->can('roles.manage'), 403);
        $this->findTenantRole($roleId);

        $this->deletingId = $roleId;
        $this->showDeleteModal = true;
    }

    /**
     * What: Delete the confirmed role.
     * Why: spatie detaches its pivots on delete; users who had only this role simply lose it. The role is
     *      re-fetched under the team scope so a tenant can never delete another tenant's role by id.
     * When: On confirm of the delete modal.
     */
    public function delete(): void
    {
        abort_unless(Auth::user()->can('roles.manage'), 403);

        $role = $this->findTenantRole($this->deletingId);
        $role->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;
        unset($this->roles);

        Flux::toast(variant: 'success', text: __('Role deleted.'));
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'selectedPermissions']);
        $this->resetValidation();
    }

    /**
     * What: Fetch a role for the current tenant or 404.
     * Why: Roles are team-scoped; an out-of-tenant id must not resolve.
     * When: Before every role read/write keyed by id.
     */
    protected function findTenantRole(int $roleId): Role
    {
        return Role::query()
            ->where('team_id', $this->teamId())
            ->findOrFail($roleId);
    }

    protected function teamId(): ?int
    {
        return Auth::user()->company_id;
    }

    /**
     * @return array<int, string>
     */
    protected function permissionCatalog(): array
    {
        return Permission::query()->orderBy('name')->pluck('name')->all();
    }

    /**
     * What: This tenant's roles with their permission counts.
     * When: Read by the roles table on render.
     *
     * @return Collection<int, Role>
     */
    #[Computed]
    public function roles(): Collection
    {
        return Role::query()
            ->where('team_id', $this->teamId())
            ->with('permissions')
            ->orderBy('name')
            ->get();
    }

    /**
     * What: The permission catalog grouped by module (the part before the dot).
     * Why: The matrix renders one section per module (clients, invoices, …) for a scannable layout.
     * When: Read by the permission matrix in the role form modal.
     *
     * @return Collection<string, Collection<int, Permission>>
     */
    #[Computed]
    public function groupedPermissions(): Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => explode('.', $permission->name)[0]);
    }

    public function render()
    {
        return view('livewire.admin.roles.index');
    }
}
