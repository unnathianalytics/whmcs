<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Reminders') }}</flux:heading>
        @can('reminders.manage')
            @if ($tab === 'rules')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                    {{ __('Add Rule') }}
                </flux:button>
            @endif
        @endcan
    </div>

    <flux:tabs wire:model.live="tab">
        <flux:tab name="rules" icon="bell-alert">{{ __('Rules') }}</flux:tab>
        <flux:tab name="logs" icon="clock">{{ __('Sent Log') }}</flux:tab>
    </flux:tabs>

    {{-- Rules tab --}}
    @if ($tab === 'rules')
        <flux:table :paginate="$this->reminderRules">
            <flux:table.columns>
                <flux:table.column>{{ __('Resource') }}</flux:table.column>
                <flux:table.column>{{ __('Days before') }}</flux:table.column>
                <flux:table.column>{{ __('Subject') }}</flux:table.column>
                <flux:table.column>{{ __('Channels') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->reminderRules as $rule)
                    <flux:table.row :key="$rule->id">
                        <flux:table.cell class="font-medium">{{ $rule->resource_type->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $rule->days_before }}</flux:table.cell>
                        <flux:table.cell class="max-w-xs truncate">{{ $rule->subject }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                @if ($rule->notify_client)
                                    <flux:badge color="blue" size="sm">{{ __('Client') }}</flux:badge>
                                @endif
                                @if ($rule->notify_admin)
                                    <flux:badge color="purple" size="sm">{{ __('Admin') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$rule->is_active ? 'green' : 'zinc'" size="sm">
                                {{ $rule->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @can('reminders.manage')
                                <flux:dropdown align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $rule->id }})">
                                            {{ __('Edit') }}
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger"
                                            wire:click="confirmDelete({{ $rule->id }})">
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <flux:text class="text-center">{{ __('No reminder rules yet.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    {{-- Sent log tab --}}
    @if ($tab === 'logs')
        <flux:table :paginate="$this->reminderLogs">
            <flux:table.columns>
                <flux:table.column>{{ __('Sent') }}</flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>{{ __('Resource') }}</flux:table.column>
                <flux:table.column>{{ __('Days before') }}</flux:table.column>
                <flux:table.column>{{ __('Channel') }}</flux:table.column>
                <flux:table.column>{{ __('Recipient') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->reminderLogs as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell>{{ $log->sent_at->format('M j, Y g:i A') }}</flux:table.cell>
                        <flux:table.cell>{{ $log->client?->name ?? __('—') }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $log->remindable?->domain_name ?? $log->remindable?->label ?? class_basename($log->remindable_type) }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $log->days_before }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$log->channel === 'admin' ? 'purple' : 'blue'" size="sm">
                                {{ ucfirst($log->channel) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $log->recipient }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <flux:text class="text-center">{{ __('No reminders have been sent yet.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    {{-- Create / edit modal --}}
    <flux:modal wire:model.self="showFormModal" class="md:w-150">
        <form wire:submit="save" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Reminder Rule') : __('Add Reminder Rule') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Send a templated reminder a set number of days before a resource expires.') }}
                </flux:text>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Resource type') }}</flux:label>
                    <flux:select wire:model="resourceType">
                        @foreach ($this->resourceTypes as $type)
                            <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="resourceType" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Days before expiry') }}</flux:label>
                    <flux:input wire:model="daysBefore" type="number" min="0" max="3650" />
                    <flux:error name="daysBefore" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Email subject') }}</flux:label>
                <flux:input wire:model="subject" placeholder="{{ __('{product_name} expires in {days_left} days') }}" />
                <flux:error name="subject" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Email body') }}</flux:label>
                <flux:textarea wire:model="body" rows="5"
                    placeholder="{{ __('Hi {client_name}, your {product_name} expires on {expires_at}.') }}" />
                <flux:description>
                    {{ __('Variables: {client_name}, {product_name}, {domain_name}, {expires_at}, {days_left}') }}
                </flux:description>
                <flux:error name="body" />
            </flux:field>

            <div class="flex flex-col gap-3">
                <flux:checkbox wire:model="notifyClient" label="{{ __('Notify the client') }}" />
                <flux:checkbox wire:model="notifyAdmin" label="{{ __('Notify the company admin email') }}" />
                <flux:checkbox wire:model="isActive" label="{{ __('Rule is active') }}" />
            </div>

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
                <flux:heading size="lg">{{ __('Delete reminder rule?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('This removes the rule. Sent-reminder history is kept.') }}
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
