<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <flux:button :href="route('admin.clients')" wire:navigate variant="ghost" size="sm" icon="arrow-left" />
            <div>
                <flux:heading size="xl">{{ $client->name }}</flux:heading>
                @if ($client->company_name)
                    <flux:text>{{ $client->company_name }}</flux:text>
                @endif
            </div>
        </div>
        <flux:badge :color="$client->status->color()">{{ $client->status->label() }}</flux:badge>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Contact / overview --}}
        <flux:card class="lg:col-span-1">
            <flux:heading size="lg">{{ __('Overview') }}</flux:heading>

            <flux:separator class="my-4" />

            <dl class="grid grid-cols-1 gap-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Email') }}</dt>
                    <dd class="text-right font-medium">{{ $client->email }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Phone') }}</dt>
                    <dd class="text-right font-medium">{{ $client->phone ?? __('—') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Address') }}</dt>
                    <dd class="text-right font-medium">
                        {{ collect([$client->address, $client->city, $client->state, $client->postcode, $client->country])->filter()->join(', ') ?: __('—') }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Currency') }}</dt>
                    <dd class="text-right font-medium">{{ $client->currency }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Language') }}</dt>
                    <dd class="text-right font-medium">{{ $client->language }}</dd>
                </div>
            </dl>
        </flux:card>

        {{-- Related modules (placeholders until later phases) + notes --}}
        <div class="flex flex-col gap-6 lg:col-span-2">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <flux:card>
                    <flux:text>{{ __('Services') }}</flux:text>
                    <flux:heading size="xl" class="mt-1">{{ $this->services->count() }}</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-500">{{ __('Active subscriptions') }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:text>{{ __('Invoices') }}</flux:text>
                    <flux:heading size="xl" class="mt-1">{{ $this->invoices->count() }}</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-500">{{ __('Billing documents') }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:text>{{ __('Tickets') }}</flux:text>
                    <flux:heading size="xl" class="mt-1">{{ $this->tickets->count() }}</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-500">{{ __('Support requests') }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:text>{{ __('Domains') }}</flux:text>
                    <flux:heading size="xl" class="mt-1">{{ $this->domains->count() }}</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-500">{{ __('Registrations') }}</flux:text>
                </flux:card>
            </div>

            {{-- Services --}}
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Services') }}</flux:heading>
                        <flux:text size="sm" class="mt-1 text-zinc-500">{{ __('Products this client is subscribed to.') }}</flux:text>
                    </div>
                    @can('services.view')
                        <flux:button :href="route('admin.services')" wire:navigate size="sm" variant="ghost" icon="arrow-up-right">
                            {{ __('Manage') }}
                        </flux:button>
                    @endcan
                </div>

                <flux:separator class="my-4" />

                @if ($this->services->isNotEmpty())
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Product') }}</flux:table.column>
                            <flux:table.column>{{ __('Cycle') }}</flux:table.column>
                            <flux:table.column>{{ __('Price') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column>{{ __('Expires') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->services as $service)
                                <flux:table.row wire:key="service-{{ $service->id }}">
                                    <flux:table.cell>
                                        <div class="flex flex-col">
                                            <span class="font-medium">{{ $service->product?->name ?? __('Custom') }}</span>
                                            @if ($service->label)
                                                <flux:text size="sm">{{ $service->label }}</flux:text>
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $service->billing_cycle->label() }}</flux:table.cell>
                                    <flux:table.cell>{{ $service->currency }} {{ number_format((float) $service->price, 2) }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$service->status->color()" size="sm">{{ $service->status->label() }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($service->expires_at)
                                            <flux:badge :color="$service->urgencyColor()" size="sm">{{ $service->expires_at->format('M j, Y') }}</flux:badge>
                                        @else
                                            <flux:text class="text-zinc-500">{{ __('—') }}</flux:text>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @else
                    <flux:text class="text-zinc-500">{{ __('No services yet.') }}</flux:text>
                @endif
            </flux:card>

            {{-- Invoices --}}
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Invoices') }}</flux:heading>
                        <flux:text size="sm" class="mt-1 text-zinc-500">{{ __('Billing documents raised for this client.') }}</flux:text>
                    </div>
                    @can('invoices.view')
                        <flux:button :href="route('admin.invoices')" wire:navigate size="sm" variant="ghost" icon="arrow-up-right">
                            {{ __('Manage') }}
                        </flux:button>
                    @endcan
                </div>

                <flux:separator class="my-4" />

                @if ($this->invoices->isNotEmpty())
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Number') }}</flux:table.column>
                            <flux:table.column>{{ __('Issued') }}</flux:table.column>
                            <flux:table.column>{{ __('Total') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->invoices as $invoice)
                                <flux:table.row wire:key="invoice-{{ $invoice->id }}">
                                    <flux:table.cell>
                                        @can('invoices.view')
                                            <a href="{{ route('admin.invoices.show', $invoice) }}" wire:navigate class="font-medium hover:underline">{{ $invoice->number }}</a>
                                        @else
                                            <span class="font-medium">{{ $invoice->number }}</span>
                                        @endcan
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $invoice->issue_date->format('M j, Y') }}</flux:table.cell>
                                    <flux:table.cell>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($invoice->isOverdue())
                                            <flux:badge color="red" size="sm">{{ __('Overdue') }}</flux:badge>
                                        @else
                                            <flux:badge :color="$invoice->status->color()" size="sm">{{ $invoice->status->label() }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @else
                    <flux:text class="text-zinc-500">{{ __('No invoices yet.') }}</flux:text>
                @endif
            </flux:card>

            {{-- Tickets --}}
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Tickets') }}</flux:heading>
                        <flux:text size="sm" class="mt-1 text-zinc-500">{{ __('Support requests raised for this client.') }}</flux:text>
                    </div>
                    @can('tickets.view')
                        <flux:button :href="route('admin.tickets')" wire:navigate size="sm" variant="ghost" icon="arrow-up-right">
                            {{ __('Manage') }}
                        </flux:button>
                    @endcan
                </div>

                <flux:separator class="my-4" />

                @if ($this->tickets->isNotEmpty())
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Number') }}</flux:table.column>
                            <flux:table.column>{{ __('Subject') }}</flux:table.column>
                            <flux:table.column>{{ __('Department') }}</flux:table.column>
                            <flux:table.column>{{ __('Priority') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->tickets as $ticket)
                                <flux:table.row wire:key="ticket-{{ $ticket->id }}">
                                    <flux:table.cell>
                                        @can('tickets.view')
                                            <a href="{{ route('admin.tickets.show', $ticket) }}" wire:navigate class="font-medium hover:underline">{{ $ticket->number }}</a>
                                        @else
                                            <span class="font-medium">{{ $ticket->number }}</span>
                                        @endcan
                                    </flux:table.cell>
                                    <flux:table.cell class="max-w-xs truncate">{{ $ticket->subject }}</flux:table.cell>
                                    <flux:table.cell>{{ $ticket->department?->name ?? __('—') }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$ticket->priority->color()" size="sm">{{ $ticket->priority->label() }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$ticket->status->color()" size="sm">{{ $ticket->status->label() }}</flux:badge>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @else
                    <flux:text class="text-zinc-500">{{ __('No tickets yet.') }}</flux:text>
                @endif
            </flux:card>

            {{-- Domains --}}
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Domains') }}</flux:heading>
                        <flux:text size="sm" class="mt-1 text-zinc-500">{{ __('Domain registrations tracked for this client.') }}</flux:text>
                    </div>
                    @can('domains.view')
                        <flux:button :href="route('admin.domains')" wire:navigate size="sm" variant="ghost" icon="arrow-up-right">
                            {{ __('Manage') }}
                        </flux:button>
                    @endcan
                </div>

                <flux:separator class="my-4" />

                @if ($this->domains->isNotEmpty())
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Domain') }}</flux:table.column>
                            <flux:table.column>{{ __('Registrar') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column>{{ __('Expires') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->domains as $domain)
                                <flux:table.row wire:key="domain-{{ $domain->id }}">
                                    <flux:table.cell class="font-medium">{{ $domain->domain_name }}</flux:table.cell>
                                    <flux:table.cell>{{ $domain->registrar ?? __('—') }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$domain->status->color()" size="sm">{{ $domain->status->label() }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($domain->expires_at)
                                            <flux:badge :color="$domain->urgencyColor()" size="sm">{{ $domain->expires_at->format('M j, Y') }}</flux:badge>
                                        @else
                                            <flux:text class="text-zinc-500">{{ __('—') }}</flux:text>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @else
                    <flux:text class="text-zinc-500">{{ __('No domains yet.') }}</flux:text>
                @endif
            </flux:card>

            <flux:card>
                <flux:heading size="lg">{{ __('Internal Notes') }}</flux:heading>
                <flux:text size="sm" class="mt-1 text-zinc-500">{{ __('Visible to admins only.') }}</flux:text>

                @can('clients.update')
                    <form wire:submit="addNote" class="mt-4 flex flex-col gap-3">
                        <flux:field>
                            <flux:textarea wire:model="noteBody" rows="2" placeholder="{{ __('Add a note...') }}" />
                            <flux:error name="noteBody" />
                        </flux:field>
                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary" size="sm" icon="plus">{{ __('Add note') }}</flux:button>
                        </div>
                    </form>
                @endcan

                <flux:separator class="my-4" />

                <div class="flex flex-col gap-4">
                    @forelse ($this->notes as $note)
                        <div class="flex items-start justify-between gap-4" wire:key="note-{{ $note->id }}">
                            <div class="flex flex-col gap-1">
                                <flux:text>{{ $note->body }}</flux:text>
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ $note->author?->name ?? __('Unknown') }} · {{ $note->created_at->diffForHumans() }}
                                </flux:text>
                            </div>
                            @can('clients.update')
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="deleteNote({{ $note->id }})"
                                    wire:confirm="{{ __('Delete this note?') }}"
                                />
                            @endcan
                        </div>
                    @empty
                        <flux:text class="text-zinc-500">{{ __('No notes yet.') }}</flux:text>
                    @endforelse
                </div>
            </flux:card>
        </div>
    </div>
</div>
