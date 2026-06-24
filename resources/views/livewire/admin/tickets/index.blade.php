<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Tickets') }}</flux:heading>
        @can('tickets.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                {{ __('New Ticket') }}
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search tickets...') }}"
            class="max-w-sm"
        />

        <flux:select wire:model.live="status" placeholder="{{ __('All statuses') }}" class="max-w-40">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach ($this->statuses as $statusOption)
                <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="priority" placeholder="{{ __('All priorities') }}" class="max-w-40">
            <flux:select.option value="">{{ __('All priorities') }}</flux:select.option>
            @foreach ($this->priorities as $priorityOption)
                <flux:select.option value="{{ $priorityOption->value }}">{{ $priorityOption->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="department" placeholder="{{ __('All departments') }}" class="max-w-44">
            <flux:select.option value="">{{ __('All departments') }}</flux:select.option>
            @foreach ($this->departments as $departmentOption)
                <flux:select.option value="{{ $departmentOption->id }}">{{ $departmentOption->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="assignee" placeholder="{{ __('Any assignee') }}" class="max-w-44">
            <flux:select.option value="">{{ __('Any assignee') }}</flux:select.option>
            @foreach ($this->admins as $adminOption)
                <flux:select.option value="{{ $adminOption->id }}">{{ $adminOption->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->tickets">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'number'" :direction="$sortDirection" wire:click="sort('number')">
                {{ __('Number') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'subject'" :direction="$sortDirection" wire:click="sort('subject')">
                {{ __('Subject') }}
            </flux:table.column>
            <flux:table.column>{{ __('Client') }}</flux:table.column>
            <flux:table.column>{{ __('Department') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'priority'" :direction="$sortDirection" wire:click="sort('priority')">
                {{ __('Priority') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">
                {{ __('Status') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'last_reply_at'" :direction="$sortDirection" wire:click="sort('last_reply_at')">
                {{ __('Last reply') }}
            </flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->tickets as $ticket)
                <flux:table.row :key="$ticket->id">
                    <flux:table.cell>
                        <a href="{{ route('admin.tickets.show', $ticket) }}" wire:navigate class="font-medium hover:underline">
                            {{ $ticket->number }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-xs truncate">
                        <a href="{{ route('admin.tickets.show', $ticket) }}" wire:navigate class="hover:underline">
                            {{ $ticket->subject }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <a href="{{ route('admin.clients.show', $ticket->client_id) }}" wire:navigate class="hover:underline">
                            {{ $ticket->client?->name ?? __('—') }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>{{ $ticket->department?->name ?? __('—') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$ticket->priority->color()" size="sm">{{ $ticket->priority->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$ticket->status->color()" size="sm">{{ $ticket->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $ticket->last_reply_at?->diffForHumans() ?? __('—') }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="eye" :href="route('admin.tickets.show', $ticket)" wire:navigate>
                                    {{ __('View') }}
                                </flux:menu.item>
                                @can('tickets.delete')
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $ticket->id }})">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                @endcan
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">
                        <flux:text class="text-center">{{ __('No tickets found.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Create modal --}}
    <flux:modal wire:model.self="showCreateModal" class="md:w-[32rem]">
        <form wire:submit="create" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('New Ticket') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Open a ticket for a client and post the first message.') }}</flux:text>
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
                    <flux:label>{{ __('Department') }}</flux:label>
                    <flux:select wire:model="departmentId" placeholder="{{ __('Select department') }}">
                        @foreach ($this->departments as $departmentOption)
                            <flux:select.option value="{{ $departmentOption->id }}">{{ $departmentOption->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="departmentId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Priority') }}</flux:label>
                    <flux:select wire:model="priorityValue">
                        @foreach ($this->priorities as $priorityOption)
                            <flux:select.option value="{{ $priorityOption->value }}">{{ $priorityOption->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="priorityValue" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Assign to') }}</flux:label>
                <flux:select wire:model="assignedTo" placeholder="{{ __('Unassigned') }}">
                    <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                    @foreach ($this->admins as $adminOption)
                        <flux:select.option value="{{ $adminOption->id }}">{{ $adminOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="assignedTo" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Subject') }}</flux:label>
                <flux:input wire:model="subject" placeholder="{{ __('e.g. Unable to access cPanel') }}" />
                <flux:error name="subject" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Message') }}</flux:label>
                <flux:textarea wire:model="message" rows="4" />
                <flux:error name="message" />
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
                <flux:heading size="lg">{{ __('Delete ticket?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This removes the ticket and its replies. This action can be reversed by support.') }}</flux:text>
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
