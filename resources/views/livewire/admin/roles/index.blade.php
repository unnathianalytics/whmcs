<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Roles & Permissions') }}</flux:heading>
        @can('roles.manage')
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                {{ __('Add Role') }}
            </flux:button>
        @endcan
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Permissions') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->roles as $role)
                <flux:table.row :key="$role->id">
                    <flux:table.cell class="font-medium">{{ $role->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="zinc" size="sm">
                            {{ trans_choice(':count permission|:count permissions', $role->permissions->count(), ['count' => $role->permissions->count()]) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @can('roles.manage')
                            <flux:dropdown align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $role->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $role->id }})">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" class="text-center text-zinc-400">
                        {{ __('No roles yet.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Role form modal with permission matrix --}}
    <flux:modal wire:model.self="showFormModal" class="md:w-150">
        <form wire:submit="save" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Role') : __('Add Role') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Name the role and grant the permissions it should carry.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Role name') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('e.g. billing') }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Permissions') }}</flux:label>
                <flux:error name="selectedPermissions" />
                <div class="mt-2 flex max-h-96 flex-col gap-5 overflow-y-auto pr-1">
                    @foreach ($this->groupedPermissions as $module => $permissions)
                        <div>
                            <flux:text class="font-semibold capitalize">{{ str_replace('-', ' ', $module) }}</flux:text>
                            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @foreach ($permissions as $permission)
                                    <flux:checkbox
                                        wire:model="selectedPermissions"
                                        value="{{ $permission->name }}"
                                        label="{{ \Illuminate\Support\Str::after($permission->name, '.') ?: $permission->name }}" />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Save') : __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model.self="showDeleteModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Delete role?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Users assigned only this role will lose its permissions. This cannot be undone.') }}</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
