<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Ticket Departments') }}</flux:heading>
        @can('tickets.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                {{ __('New Department') }}
            </flux:button>
        @endcan
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        placeholder="{{ __('Search departments...') }}"
        class="max-w-sm"
    />

    <flux:table :paginate="$this->departments">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Tickets') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->departments as $department)
                <flux:table.row :key="$department->id">
                    <flux:table.cell class="font-medium">{{ $department->name }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $department->tickets_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$department->is_active ? 'green' : 'zinc'" size="sm">
                            {{ $department->is_active ? __('Active') : __('Inactive') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                            <flux:menu>
                                @can('tickets.update')
                                    <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $department->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                @endcan
                                @can('tickets.delete')
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $department->id }})">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                @endcan
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <flux:text class="text-center">{{ __('No departments found.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Create / edit modal --}}
    <flux:modal wire:model.self="showFormModal" class="md:w-96">
        <form wire:submit="save" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Department') : __('New Department') }}</flux:heading>
                <flux:text class="mt-2">{{ __('A routing bucket for support tickets.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('e.g. Technical') }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:field variant="inline">
                <flux:checkbox wire:model="isActive" />
                <flux:label>{{ __('Active') }}</flux:label>
                <flux:error name="isActive" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Save') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model.self="showDeleteModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Delete department?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Existing tickets keep their history and become unassigned. This action can be reversed by support.') }}</flux:text>
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
