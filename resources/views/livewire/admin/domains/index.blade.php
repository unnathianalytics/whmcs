<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Domains') }}</flux:heading>
        @can('domains.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                {{ __('Add Domain') }}
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="{{ __('Search domains...') }}" class="max-w-sm" />

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

    <flux:table :paginate="$this->domains">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'domain_name'" :direction="$sortDirection"
                wire:click="sort('domain_name')">
                {{ __('Domain') }}
            </flux:table.column>
            <flux:table.column>{{ __('Client') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'registrar'" :direction="$sortDirection"
                wire:click="sort('registrar')">
                {{ __('Registrar') }}
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
            @forelse ($this->domains as $domain)
                <flux:table.row :key="$domain->id">
                    <flux:table.cell class="font-medium">{{ $domain->domain_name }}</flux:table.cell>

                    <flux:table.cell>
                        <a href="{{ route('admin.clients.show', $domain->client_id) }}" wire:navigate
                            class="hover:underline">
                            {{ $domain->client?->name ?? __('—') }}
                        </a>
                    </flux:table.cell>

                    <flux:table.cell>{{ $domain->registrar ?? __('—') }}</flux:table.cell>

                    <flux:table.cell>
                        <flux:badge :color="$domain->status->color()" size="sm">
                            {{ $domain->status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($domain->expires_at)
                            <flux:badge :color="$domain->urgencyColor()" size="sm">
                                {{ $domain->expires_at->format('M j, Y') }}
                            </flux:badge>
                        @else
                            <flux:text class="text-zinc-500">{{ __('—') }}</flux:text>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <flux:dropdown align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                            <flux:menu>
                                @can('domains.update')
                                    <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $domain->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.item icon="arrow-path" wire:click="openRenewModal({{ $domain->id }})">
                                        {{ __('Renew') }}
                                    </flux:menu.item>
                                @endcan
                                @can('reminders.manage')
                                    <flux:menu.item icon="bell-alert" wire:click="sendReminder({{ $domain->id }})">
                                        {{ __('Send reminder') }}
                                    </flux:menu.item>
                                @endcan
                                @can('domains.delete')
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger"
                                        wire:click="confirmDelete({{ $domain->id }})">
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
                        <flux:text class="text-center">{{ __('No domains found.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Create / edit modal --}}
    <flux:modal wire:model.self="showFormModal" class="md:w-150">
        <form wire:submit="save" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Domain') : __('Add Domain') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Track a domain registration for a client.') }}</flux:text>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Client') }}</flux:label>
                    <flux:select wire:model="clientId" placeholder="{{ __('Select client') }}">
                        @foreach ($this->clients as $clientOption)
                            <flux:select.option value="{{ $clientOption->id }}">{{ $clientOption->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="clientId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Domain name') }}</flux:label>
                    <flux:input wire:model="domainName" placeholder="{{ __('example.com') }}" />
                    <flux:error name="domainName" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Registrar') }}</flux:label>
                    <flux:input wire:model="registrar" placeholder="{{ __('e.g. GoDaddy') }}" />
                    <flux:error name="registrar" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:select wire:model="statusField">
                        @foreach ($this->statuses as $statusOption)
                            <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="statusField" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Registered at') }}</flux:label>
                    <flux:input wire:model="registeredAt" type="date" />
                    <flux:error name="registeredAt" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Expires at') }}</flux:label>
                    <flux:input wire:model="expiresAt" type="date" />
                    <flux:error name="expiresAt" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Renewal cost') }}</flux:label>
                    <flux:input wire:model="renewalCost" type="number" step="0.01" min="0" />
                    <flux:error name="renewalCost" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Currency') }}</flux:label>
                    <flux:input wire:model="currency" maxlength="3" />
                    <flux:error name="currency" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Nameserver 1') }}</flux:label>
                    <flux:input wire:model="ns1" placeholder="ns1.example.com" />
                    <flux:error name="ns1" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Nameserver 2') }}</flux:label>
                    <flux:input wire:model="ns2" placeholder="ns2.example.com" />
                    <flux:error name="ns2" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Nameserver 3') }}</flux:label>
                    <flux:input wire:model="ns3" />
                    <flux:error name="ns3" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Nameserver 4') }}</flux:label>
                    <flux:input wire:model="ns4" />
                    <flux:error name="ns4" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('WHOIS notes') }}</flux:label>
                <flux:textarea wire:model="whoisNotes" rows="2" />
                <flux:error name="whoisNotes" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Save') : __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Renew modal --}}
    <flux:modal wire:model.self="showRenewModal" class="md:w-96">
        <form wire:submit="renew" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Renew domain') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Record a manual renewal. Today is logged as the renewal date.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('New expiry date') }}</flux:label>
                <flux:input wire:model="renewExpiresAt" type="date" />
                <flux:error name="renewExpiresAt" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Renewal cost') }}</flux:label>
                <flux:input wire:model="renewCost" type="number" step="0.01" min="0" />
                <flux:error name="renewCost" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Renew') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model.self="showDeleteModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Delete domain?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('This removes the domain record. This action can be reversed by support.') }}
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
