<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between">
        <div class="flex flex-col gap-1">
            <div class="flex items-center gap-3">
                <flux:heading size="xl">{{ $company->name }}</flux:heading>
                @if ($company->isSuspended())
                    <flux:badge color="red" size="sm">{{ __('Suspended') }}</flux:badge>
                @elseif ($company->onTrial())
                    <flux:badge color="yellow" size="sm">{{ __('Trial') }}</flux:badge>
                @else
                    <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                @endif
            </div>
            <flux:text>{{ $company->email ?? __('No contact email') }}</flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('saas.companies')" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:button
                size="sm"
                variant="{{ $company->isSuspended() ? 'primary' : 'ghost' }}"
                wire:click="toggleSuspend"
            >
                {{ $company->isSuspended() ? __('Reactivate') : __('Suspend') }}
            </flux:button>
            <flux:button size="sm" variant="danger" icon="trash" wire:click="$set('showDeleteModal', true)">
                {{ __('Delete') }}
            </flux:button>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Contact details --}}
        <flux:card>
            <form wire:submit="saveDetails" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Contact Details') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('Name') }}</flux:label>
                    <flux:input wire:model="name" />
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
                    <flux:label>{{ __('Address') }}</flux:label>
                    <flux:textarea wire:model="address" rows="2" />
                    <flux:error name="address" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Trial ends at') }}</flux:label>
                    <flux:input wire:model="trialEndsAt" type="date" />
                    <flux:error name="trialEndsAt" />
                </flux:field>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:card>

        {{-- Subscription --}}
        <flux:card>
            <form wire:submit="saveSubscription" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Subscription') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('Plan') }}</flux:label>
                    <flux:select wire:model="planId" placeholder="{{ __('Select a plan') }}">
                        @foreach ($this->plans as $plan)
                            <flux:select.option value="{{ $plan->id }}">
                                {{ $plan->name }} — ${{ number_format($plan->price, 2) }}/{{ $plan->interval }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="planId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:select wire:model="status">
                        <flux:select.option value="trialing">{{ __('Trialing') }}</flux:select.option>
                        <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                        <flux:select.option value="past_due">{{ __('Past Due') }}</flux:select.option>
                        <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="status" />
                </flux:field>

                <div class="flex gap-4">
                    <flux:field class="flex-1">
                        <flux:label>{{ __('Starts at') }}</flux:label>
                        <flux:input wire:model="startsAt" type="date" />
                        <flux:error name="startsAt" />
                    </flux:field>

                    <flux:field class="flex-1">
                        <flux:label>{{ __('Ends at') }}</flux:label>
                        <flux:input wire:model="endsAt" type="date" />
                        <flux:error name="endsAt" />
                    </flux:field>
                </div>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:card>
    </div>

    {{-- Company admins --}}
    <flux:card>
        <flux:heading size="lg">{{ __('Company Admins') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Log in as one of these admins to debug the tenant.') }}</flux:text>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($admins as $admin)
                    <flux:table.row :key="$admin->id">
                        <flux:table.cell variant="strong">{{ $admin->name }}</flux:table.cell>
                        <flux:table.cell>{{ $admin->email }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <form method="POST" action="{{ route('saas.impersonate', $admin) }}">
                                @csrf
                                <flux:button type="submit" size="sm" variant="ghost" icon="user-circle">
                                    {{ __('Impersonate') }}
                                </flux:button>
                            </form>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">
                            <flux:text class="text-center">{{ __('No admins for this company.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Company') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('This hides the tenant and all its data. Type the company name to confirm.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Company name') }}</flux:label>
                <flux:input wire:model="deleteConfirmation" placeholder="{{ $company->name }}" />
                <flux:error name="deleteConfirmation" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
