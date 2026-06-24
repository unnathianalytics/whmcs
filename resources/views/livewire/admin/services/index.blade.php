<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Services') }}</flux:heading>
        @can('services.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                {{ __('Assign Service') }}
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="{{ __('Search services...') }}" class="max-w-sm" />

        <flux:select wire:model.live="status" placeholder="{{ __('All statuses') }}" class="max-w-44">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach ($this->statuses as $statusOption)
                <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="expiry" placeholder="{{ __('Any expiry') }}" class="max-w-44">
            <flux:select.option value="">{{ __('Any expiry') }}</flux:select.option>
            <flux:select.option value="expiring">{{ __('Expiring (30 days)') }}</flux:select.option>
            <flux:select.option value="expired">{{ __('Expired') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->services">
        <flux:table.columns>
            <flux:table.column>{{ __('Client') }}</flux:table.column>
            <flux:table.column>{{ __('Product') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'price'" :direction="$sortDirection"
                wire:click="sort('price')">
                {{ __('Price') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection"
                wire:click="sort('status')">
                {{ __('Status') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'expires_at'" :direction="$sortDirection"
                wire:click="sort('expires_at')">
                {{ __('Expires') }}
            </flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->services as $service)
                <flux:table.row :key="$service->id">
                    <flux:table.cell>
                        <a href="{{ route('admin.clients.show', $service->client_id) }}" wire:navigate
                            class="flex flex-col hover:underline">
                            <span class="font-medium">{{ $service->client?->name ?? __('—') }}</span>
                            @if ($service->label)
                                <flux:text size="sm">{{ $service->label }}</flux:text>
                            @endif
                        </a>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $service->product?->name ?? __('Custom') }}</span>
                            <flux:text size="sm" class="text-zinc-500">{{ $service->billing_cycle->label() }}
                            </flux:text>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>{{ $service->currency }} {{ number_format((float) $service->price, 2) }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge :color="$service->status->color()" size="sm">
                            {{ $service->status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($service->expires_at)
                            <flux:badge :color="$service->urgencyColor()" size="sm">
                                {{ $service->expires_at->format('M j, Y') }}
                            </flux:badge>
                        @else
                            <flux:text class="text-zinc-500">{{ __('—') }}</flux:text>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <flux:dropdown align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                            <flux:menu>
                                @can('services.update')
                                    <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $service->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                @endcan
                                @can('reminders.manage')
                                    <flux:menu.item icon="bell-alert" wire:click="sendReminder({{ $service->id }})">
                                        {{ __('Send reminder') }}
                                    </flux:menu.item>
                                @endcan
                                @can('services.delete')
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger"
                                        wire:click="confirmDelete({{ $service->id }})">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                @endcan
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <flux:text class="text-center">{{ __('No services found.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Assign / edit modal --}}
    <flux:modal wire:model.self="showFormModal" class="md:w-136">
        <form wire:submit="save" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Service') : __('Assign Service') }}
                </flux:heading>
                <flux:text class="mt-2">{{ __('Subscribe a client to a product.') }}</flux:text>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Client') }}</flux:label>
                    <flux:select wire:model="clientId" placeholder="{{ __('Select client') }}">
                        @foreach ($this->clients as $clientOption)
                            <flux:select.option value="{{ $clientOption->id }}">{{ $clientOption->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="clientId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Product') }}</flux:label>
                    <flux:select wire:model.live="productId" placeholder="{{ __('Custom (none)') }}">
                        <flux:select.option value="">{{ __('Custom (none)') }}</flux:select.option>
                        @foreach ($this->products as $productOption)
                            <flux:select.option value="{{ $productOption->id }}">{{ $productOption->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="productId" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Label / domain') }}</flux:label>
                <flux:input wire:model="label" placeholder="{{ __('e.g. example.com') }}" />
                <flux:error name="label" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Cycle') }}</flux:label>
                    <flux:select wire:model="billingCycle">
                        @foreach ($this->cycles as $cycleOption)
                            <flux:select.option value="{{ $cycleOption->value }}">{{ $cycleOption->label() }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="billingCycle" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Price') }}</flux:label>
                    <flux:input wire:model="price" type="number" step="0.01" min="0" />
                    <flux:error name="price" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Currency') }}</flux:label>
                    <flux:input wire:model="currency" maxlength="3" />
                    <flux:error name="currency" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Starts at') }}</flux:label>
                    <flux:input wire:model="startsAt" type="date" />
                    <flux:error name="startsAt" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Expires at') }}</flux:label>
                    <flux:input wire:model="expiresAt" type="date" />
                    <flux:error name="expiresAt" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Next due') }}</flux:label>
                    <flux:input wire:model="nextDueDate" type="date" />
                    <flux:error name="nextDueDate" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model="statusField">
                    @foreach ($this->statuses as $statusOption)
                        <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="statusField" />
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
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Save') : __('Assign') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model.self="showDeleteModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Delete service?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('This removes the service from the client. This action can be reversed by support.') }}
                </flux:text>
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
