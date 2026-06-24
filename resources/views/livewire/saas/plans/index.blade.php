<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Plans') }}</flux:heading>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('New Plan') }}
        </flux:button>
    </div>

    <flux:table :paginate="$this->plans">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Price') }}</flux:table.column>
            <flux:table.column>{{ __('Limits') }}</flux:table.column>
            <flux:table.column>{{ __('Tenants') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->plans as $plan)
                <flux:table.row :key="$plan->id">
                    <flux:table.cell variant="strong">{{ $plan->name }}</flux:table.cell>

                    <flux:table.cell>
                        ${{ number_format($plan->price, 2) }}/{{ $plan->interval }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:text size="sm">
                            {{ __('Clients') }}: {{ $plan->limits['max_clients'] ?? __('∞') }} ·
                            {{ __('Admins') }}: {{ $plan->limits['max_admins'] ?? __('∞') }}
                        </flux:text>
                    </flux:table.cell>

                    <flux:table.cell>{{ $plan->subscriptions_count }}</flux:table.cell>

                    <flux:table.cell>
                        @if ($plan->is_active)
                            <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $plan->id }})">
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $plan->id }})">
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <flux:text class="text-center">{{ __('No plans yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="showFormModal" class="md:w-96">
        <form wire:submit="save" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Plan') : __('New Plan') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Leave a limit blank for unlimited.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" autofocus />
                <flux:error name="name" />
            </flux:field>

            <div class="flex gap-4">
                <flux:field class="flex-1">
                    <flux:label>{{ __('Price') }}</flux:label>
                    <flux:input wire:model="price" type="number" step="0.01" min="0" />
                    <flux:error name="price" />
                </flux:field>

                <flux:field class="flex-1">
                    <flux:label>{{ __('Interval') }}</flux:label>
                    <flux:select wire:model="interval">
                        <flux:select.option value="monthly">{{ __('Monthly') }}</flux:select.option>
                        <flux:select.option value="annual">{{ __('Annual') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="interval" />
                </flux:field>
            </div>

            <div class="flex gap-4">
                <flux:field class="flex-1">
                    <flux:label>{{ __('Max Clients') }}</flux:label>
                    <flux:input wire:model="maxClients" type="number" min="0" placeholder="{{ __('Unlimited') }}" />
                    <flux:error name="maxClients" />
                </flux:field>

                <flux:field class="flex-1">
                    <flux:label>{{ __('Max Admins') }}</flux:label>
                    <flux:input wire:model="maxAdmins" type="number" min="0" placeholder="{{ __('Unlimited') }}" />
                    <flux:error name="maxAdmins" />
                </flux:field>
            </div>

            <flux:field variant="inline">
                <flux:checkbox wire:model="isActive" />
                <flux:label>{{ __('Active (available for assignment)') }}</flux:label>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Plan') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This permanently removes the plan. This cannot be undone.') }}</flux:text>
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
