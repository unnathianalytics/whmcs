<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Clients') }}</flux:heading>
        @can('clients.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                {{ __('New Client') }}
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search clients...') }}"
            class="max-w-sm"
        />

        <flux:select wire:model.live="status" placeholder="{{ __('All statuses') }}" class="max-w-44">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach ($this->statuses as $statusOption)
                <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        @if (count($this->countries) > 0)
            <flux:select wire:model.live="country" placeholder="{{ __('All countries') }}" class="max-w-44">
                <flux:select.option value="">{{ __('All countries') }}</flux:select.option>
                @foreach ($this->countries as $countryCode)
                    <flux:select.option value="{{ $countryCode }}">{{ $countryCode }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    <flux:table :paginate="$this->clients">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">
                {{ __('Name') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">
                {{ __('Email') }}
            </flux:table.column>
            <flux:table.column>{{ __('Phone') }}</flux:table.column>
            <flux:table.column>{{ __('Country') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">
                {{ __('Status') }}
            </flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->clients as $client)
                <flux:table.row :key="$client->id">
                    <flux:table.cell>
                        <a href="{{ route('admin.clients.show', $client) }}" wire:navigate class="flex flex-col hover:underline">
                            <span class="font-medium">{{ $client->name }}</span>
                            @if ($client->company_name)
                                <flux:text size="sm">{{ $client->company_name }}</flux:text>
                            @endif
                        </a>
                    </flux:table.cell>

                    <flux:table.cell>{{ $client->email }}</flux:table.cell>
                    <flux:table.cell>{{ $client->phone ?? __('—') }}</flux:table.cell>
                    <flux:table.cell>{{ $client->country ?? __('—') }}</flux:table.cell>

                    <flux:table.cell>
                        <flux:badge :color="$client->status->color()" size="sm">
                            {{ $client->status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <flux:dropdown align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />

                            <flux:menu>
                                <flux:menu.item icon="eye" :href="route('admin.clients.show', $client)" wire:navigate>
                                    {{ __('View') }}
                                </flux:menu.item>
                                @can('clients.update')
                                    <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $client->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                @endcan
                                @can('clients.delete')
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $client->id }})">
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
                        <flux:text class="text-center">{{ __('No clients found.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Create / Edit modal --}}
    <flux:modal wire:model.self="showFormModal" class="md:w-[32rem]">
        <form wire:submit="save" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Client') : __('New Client') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Customer account details.') }}</flux:text>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Name') }}</flux:label>
                    <flux:input wire:model="name" autofocus />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input wire:model="email" type="email" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Phone') }}</flux:label>
                    <flux:input wire:model="phone" />
                    <flux:error name="phone" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Company') }}</flux:label>
                    <flux:input wire:model="companyName" />
                    <flux:error name="companyName" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Address') }}</flux:label>
                <flux:input wire:model="address" />
                <flux:error name="address" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('City') }}</flux:label>
                    <flux:input wire:model="city" />
                    <flux:error name="city" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('State') }}</flux:label>
                    <flux:input wire:model="state" />
                    <flux:error name="state" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Postcode') }}</flux:label>
                    <flux:input wire:model="postcode" />
                    <flux:error name="postcode" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Country code') }}</flux:label>
                    <flux:input wire:model="country_code" placeholder="US" maxlength="2" />
                    <flux:error name="country_code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Currency') }}</flux:label>
                    <flux:input wire:model="currency" placeholder="USD" maxlength="3" />
                    <flux:error name="currency" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Language') }}</flux:label>
                    <flux:input wire:model="language" placeholder="en" maxlength="5" />
                    <flux:error name="language" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model="statusField">
                    @foreach ($this->statuses as $statusOption)
                        <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="statusField" />
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
                <flux:heading size="lg">{{ __('Delete client?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This will remove the client from your lists. This action can be reversed by support.') }}</flux:text>
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
