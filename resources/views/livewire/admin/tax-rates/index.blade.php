<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Tax Rates') }}</flux:heading>
        @can('invoices.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                {{ __('New Tax Rate') }}
            </flux:button>
        @endcan
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        placeholder="{{ __('Search tax rates...') }}"
        class="max-w-sm"
    />

    <flux:table :paginate="$this->taxRates">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Rate') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->taxRates as $taxRate)
                <flux:table.row :key="$taxRate->id">
                    <flux:table.cell class="font-medium">{{ $taxRate->name }}</flux:table.cell>
                    <flux:table.cell>{{ number_format((float) $taxRate->rate, 2) }}%</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$taxRate->is_active ? 'green' : 'zinc'" size="sm">
                            {{ $taxRate->is_active ? __('Active') : __('Inactive') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                            <flux:menu>
                                @can('invoices.update')
                                    <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $taxRate->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                @endcan
                                @can('invoices.delete')
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $taxRate->id }})">
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
                        <flux:text class="text-center">{{ __('No tax rates found.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Create / edit modal --}}
    <flux:modal wire:model.self="showFormModal" class="md:w-96">
        <form wire:submit="save" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Tax Rate') : __('New Tax Rate') }}</flux:heading>
                <flux:text class="mt-2">{{ __('A reusable tax percentage for invoice line items.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('e.g. GST 18%') }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Rate (%)') }}</flux:label>
                <flux:input wire:model="rate" type="number" step="0.01" min="0" max="100" />
                <flux:error name="rate" />
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
                <flux:heading size="lg">{{ __('Delete tax rate?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Existing invoices keep their snapshotted tax. This action can be reversed by support.') }}</flux:text>
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
