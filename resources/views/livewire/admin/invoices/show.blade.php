<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:button :href="route('admin.invoices')" wire:navigate variant="ghost" size="sm" icon="arrow-left" />
            <div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl">{{ $invoice->number }}</flux:heading>
                    @if ($invoice->isOverdue())
                        <flux:badge color="red">{{ __('Overdue') }}</flux:badge>
                    @else
                        <flux:badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</flux:badge>
                    @endif
                </div>
                <flux:text class="mt-1">
                    <a href="{{ route('admin.clients.show', $invoice->client_id) }}" wire:navigate
                        class="hover:underline">
                        {{ $invoice->client?->name }}
                    </a>
                </flux:text>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @can('invoices.view')
                <flux:button :href="route('admin.invoices.pdf', $invoice)" target="_blank" variant="ghost"
                    icon="arrow-down-tray">
                    {{ __('Download PDF') }}
                </flux:button>
            @endcan
            @can('invoices.update')
                <flux:button wire:click="openHeaderModal" variant="ghost" icon="pencil-square">
                    {{ __('Edit Details') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Line items + summary --}}
        <div class="flex flex-col gap-6 lg:col-span-2">
            <flux:card>
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Line Items') }}</flux:heading>
                    @can('invoices.update')
                        <flux:button size="sm" variant="primary" icon="plus" wire:click="openCreateItemModal">
                            {{ __('Add Item') }}
                        </flux:button>
                    @endcan
                </div>

                <flux:separator class="my-4" />

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Description') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Qty') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Unit') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Tax') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->items as $item)
                            <flux:table.row :key="$item->id">
                                <flux:table.cell class="font-medium">{{ $item->description }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $item->quantity, 2) }}
                                </flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $item->unit_price, 2) }}
                                </flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $item->tax_rate, 2) }}%
                                </flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $item->line_total, 2) }}
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @can('invoices.update')
                                        <flux:dropdown align="end">
                                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                                            <flux:menu>
                                                <flux:menu.item icon="pencil-square"
                                                    wire:click="openEditItemModal({{ $item->id }})">
                                                    {{ __('Edit') }}
                                                </flux:menu.item>
                                                <flux:menu.separator />
                                                <flux:menu.item icon="trash" variant="danger"
                                                    wire:click="confirmDeleteItem({{ $item->id }})">
                                                    {{ __('Remove') }}
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    @endcan
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6">
                                    <flux:text class="text-center">{{ __('No line items yet.') }}</flux:text>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>

                <flux:separator class="my-4" />

                <div class="flex justify-end">
                    <dl class="w-full max-w-xs gap-2 text-sm">
                        <div class="flex justify-between py-1">
                            <dt class="text-zinc-500">{{ __('Subtotal') }}</dt>
                            <dd>{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</dd>
                        </div>
                        <div class="flex justify-between py-1">
                            <dt class="text-zinc-500">{{ __('Tax') }}</dt>
                            <dd>{{ $invoice->currency }} {{ number_format((float) $invoice->tax_total, 2) }}</dd>
                        </div>
                        <flux:separator class="my-1" />
                        <div class="flex justify-between py-1 font-semibold">
                            <dt>{{ __('Total') }}</dt>
                            <dd>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</dd>
                        </div>
                        <div class="flex justify-between py-1">
                            <dt class="text-zinc-500">{{ __('Paid') }}</dt>
                            <dd>{{ $invoice->currency }} {{ number_format($invoice->amountPaid(), 2) }}</dd>
                        </div>
                        <div
                            class="flex justify-between py-1 font-semibold {{ $invoice->balance() > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                            <dt>{{ __('Balance') }}</dt>
                            <dd>{{ $invoice->currency }} {{ number_format($invoice->balance(), 2) }}</dd>
                        </div>
                    </dl>
                </div>
            </flux:card>

            {{-- Payments --}}
            <flux:card>
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Payments') }}</flux:heading>
                    @can('invoices.update')
                        <flux:button size="sm" variant="primary" icon="banknotes" wire:click="openPaymentModal">
                            {{ __('Record Payment') }}
                        </flux:button>
                    @endcan
                </div>

                <flux:separator class="my-4" />

                @if ($this->transactions->isNotEmpty())
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Date') }}</flux:table.column>
                            <flux:table.column>{{ __('Method') }}</flux:table.column>
                            <flux:table.column>{{ __('Reference') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->transactions as $transaction)
                                <flux:table.row wire:key="txn-{{ $transaction->id }}">
                                    <flux:table.cell>{{ $transaction->paid_at->format('M j, Y') }}</flux:table.cell>
                                    <flux:table.cell>{{ $transaction->method->label() }}</flux:table.cell>
                                    <flux:table.cell>{{ $transaction->reference ?? __('—') }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ $invoice->currency }}
                                        {{ number_format((float) $transaction->amount, 2) }}</flux:table.cell>
                                    <flux:table.cell align="end">
                                        @can('invoices.update')
                                            <flux:button size="sm" variant="ghost" icon="trash"
                                                wire:click="confirmDeletePayment({{ $transaction->id }})" />
                                        @endcan
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @else
                    <flux:text class="text-zinc-500">{{ __('No payments recorded.') }}</flux:text>
                @endif
            </flux:card>
        </div>

        {{-- Details sidebar --}}
        <flux:card class="lg:col-span-1">
            <flux:heading size="lg">{{ __('Details') }}</flux:heading>
            <flux:separator class="my-4" />
            <dl class="grid grid-cols-1 gap-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Client') }}</dt>
                    <dd class="text-right font-medium">{{ $invoice->client?->name }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Status') }}</dt>
                    <dd class="text-right font-medium">{{ $invoice->status->label() }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Issued') }}</dt>
                    <dd class="text-right font-medium">{{ $invoice->issue_date->format('M j, Y') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Due') }}</dt>
                    <dd class="text-right font-medium">{{ $invoice->due_date->format('M j, Y') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Currency') }}</dt>
                    <dd class="text-right font-medium">{{ $invoice->currency }}</dd>
                </div>
                @if ($invoice->paid_at)
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Paid at') }}</dt>
                        <dd class="text-right font-medium">{{ $invoice->paid_at->format('M j, Y') }}</dd>
                    </div>
                @endif
            </dl>

            @if ($invoice->notes)
                <flux:separator class="my-4" />
                <flux:heading size="sm">{{ __('Notes') }}</flux:heading>
                <flux:text class="mt-2 whitespace-pre-line">{{ $invoice->notes }}</flux:text>
            @endif
        </flux:card>
    </div>

    {{-- Header edit modal --}}
    <flux:modal wire:model.self="showHeaderModal" class="md:w-md">
        <form wire:submit="saveHeader" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Edit Invoice') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model="status">
                    @foreach ($this->statuses as $statusOption)
                        <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="status" />
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
                <flux:label>{{ __('Notes') }}</flux:label>
                <flux:textarea wire:model="notes" rows="3" />
                <flux:error name="notes" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Line item modal --}}
    <flux:modal wire:model.self="showItemModal" class="md:w-120">
        <form wire:submit="saveItem" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ $editingItemId ? __('Edit Line Item') : __('Add Line Item') }}
            </flux:heading>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:input wire:model="itemDescription" placeholder="{{ __('e.g. Shared Hosting — Annual') }}" />
                <flux:error name="itemDescription" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Quantity') }}</flux:label>
                    <flux:input wire:model="itemQuantity" type="number" step="0.01" min="0.01" />
                    <flux:error name="itemQuantity" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Unit price') }}</flux:label>
                    <flux:input wire:model="itemUnitPrice" type="number" step="0.01" min="0" />
                    <flux:error name="itemUnitPrice" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Tax') }}</flux:label>
                    <flux:select wire:model="itemTaxRateId" placeholder="{{ __('None') }}">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($this->taxRates as $taxRateOption)
                            <flux:select.option value="{{ $taxRateOption->id }}">
                                {{ $taxRateOption->name }} ({{ number_format((float) $taxRateOption->rate, 2) }}%)
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="itemTaxRateId" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Payment modal --}}
    <flux:modal wire:model.self="showPaymentModal" class="md:w-md">
        <form wire:submit="savePayment" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Record Payment') }}</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Amount') }}</flux:label>
                    <flux:input wire:model="paymentAmount" type="number" step="0.01" min="0.01" />
                    <flux:error name="paymentAmount" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Date') }}</flux:label>
                    <flux:input wire:model="paymentPaidAt" type="date" />
                    <flux:error name="paymentPaidAt" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Method') }}</flux:label>
                <flux:select wire:model="paymentMethod">
                    @foreach ($this->methods as $methodOption)
                        <flux:select.option value="{{ $methodOption->value }}">{{ $methodOption->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="paymentMethod" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Reference') }}</flux:label>
                <flux:input wire:model="paymentReference" placeholder="{{ __('Transaction / cheque no.') }}" />
                <flux:error name="paymentReference" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Notes') }}</flux:label>
                <flux:textarea wire:model="paymentNotes" rows="2" />
                <flux:error name="paymentNotes" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Record') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete line item modal --}}
    <flux:modal wire:model.self="showDeleteItemModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Remove line item?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('The invoice totals will be recalculated.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteItem">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete payment modal --}}
    <flux:modal wire:model.self="showDeletePaymentModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Remove payment?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('The balance will be re-evaluated; a settled invoice may re-open.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deletePayment">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
