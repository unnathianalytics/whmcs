<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Invoices') }}</flux:heading>
        @can('invoices.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                {{ __('New Invoice') }}
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search invoices...') }}"
            class="max-w-sm"
        />

        <flux:select wire:model.live="status" placeholder="{{ __('All statuses') }}" class="max-w-44">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach ($this->statuses as $statusOption)
                <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->invoices">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'number'" :direction="$sortDirection" wire:click="sort('number')">
                {{ __('Number') }}
            </flux:table.column>
            <flux:table.column>{{ __('Client') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'issue_date'" :direction="$sortDirection" wire:click="sort('issue_date')">
                {{ __('Issued') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'due_date'" :direction="$sortDirection" wire:click="sort('due_date')">
                {{ __('Due') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'total'" :direction="$sortDirection" wire:click="sort('total')">
                {{ __('Total') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">
                {{ __('Status') }}
            </flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->invoices as $invoice)
                <flux:table.row :key="$invoice->id">
                    <flux:table.cell>
                        <a href="{{ route('admin.invoices.show', $invoice) }}" wire:navigate class="font-medium hover:underline">
                            {{ $invoice->number }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <a href="{{ route('admin.clients.show', $invoice->client_id) }}" wire:navigate class="hover:underline">
                            {{ $invoice->client?->name ?? __('—') }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>{{ $invoice->issue_date->format('M j, Y') }}</flux:table.cell>
                    <flux:table.cell>{{ $invoice->due_date->format('M j, Y') }}</flux:table.cell>
                    <flux:table.cell>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($invoice->isOverdue())
                            <flux:badge color="red" size="sm">{{ __('Overdue') }}</flux:badge>
                        @else
                            <flux:badge :color="$invoice->status->color()" size="sm">{{ $invoice->status->label() }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="eye" :href="route('admin.invoices.show', $invoice)" wire:navigate>
                                    {{ __('View') }}
                                </flux:menu.item>
                                @can('invoices.view')
                                    <flux:menu.item icon="arrow-down-tray" :href="route('admin.invoices.pdf', $invoice)" target="_blank">
                                        {{ __('Download PDF') }}
                                    </flux:menu.item>
                                @endcan
                                @can('invoices.delete')
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $invoice->id }})">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                @endcan
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">
                        <flux:text class="text-center">{{ __('No invoices found.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Create modal --}}
    <flux:modal wire:model.self="showCreateModal" class="md:w-[28rem]">
        <form wire:submit="create" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('New Invoice') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Create a draft, then add line items on the next screen.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Client') }}</flux:label>
                <flux:select wire:model="clientId" placeholder="{{ __('Select client') }}">
                    @foreach ($this->clients as $clientOption)
                        <flux:select.option value="{{ $clientOption->id }}">{{ $clientOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="clientId" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Issue date') }}</flux:label>
                    <flux:input wire:model="issueDate" type="date" />
                    <flux:error name="issueDate" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Due date') }}</flux:label>
                    <flux:input wire:model="dueDate" type="date" />
                    <flux:error name="dueDate" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Currency') }}</flux:label>
                <flux:input wire:model="currency" maxlength="3" class="max-w-28" />
                <flux:error name="currency" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Notes') }}</flux:label>
                <flux:textarea wire:model="notes" rows="2" />
                <flux:error name="notes" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model.self="showDeleteModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Delete invoice?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This removes the invoice and its line items. This action can be reversed by support.') }}</flux:text>
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
