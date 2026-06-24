<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Companies') }}</flux:heading>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('New Company') }}
        </flux:button>
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        placeholder="{{ __('Search companies...') }}"
        class="max-w-sm"
    />

    <flux:table :paginate="$this->companies">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Plan') }}</flux:table.column>
            <flux:table.column>{{ __('Admins') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->companies as $company)
                <flux:table.row :key="$company->id">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium">{{ $company->name }}</span>
                            @if ($company->email)
                                <flux:text size="sm">{{ $company->email }}</flux:text>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        {{ $company->plan?->name ?? __('—') }}
                    </flux:table.cell>

                    <flux:table.cell>{{ $company->users_count }}</flux:table.cell>

                    <flux:table.cell>
                        @if ($company->isSuspended())
                            <flux:badge color="red" size="sm">{{ __('Suspended') }}</flux:badge>
                        @elseif ($company->onTrial())
                            <flux:badge color="yellow" size="sm">{{ __('Trial') }}</flux:badge>
                        @else
                            <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-2">
                            <flux:button
                                size="sm"
                                variant="{{ $company->isSuspended() ? 'primary' : 'ghost' }}"
                                wire:click="toggleSuspend({{ $company->id }})"
                            >
                                {{ $company->isSuspended() ? __('Reactivate') : __('Suspend') }}
                            </flux:button>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="arrow-right"
                                :href="route('saas.companies.show', $company)"
                                wire:navigate
                            >
                                {{ __('Manage') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text class="text-center">{{ __('No companies yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="showCreateModal" class="md:w-96">
        <form wire:submit="createCompany" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('New Company') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Create a new tenant company.') }}</flux:text>
            </div>

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
                <flux:label>{{ __('Plan') }}</flux:label>
                <flux:select wire:model="planId" placeholder="{{ __('No plan') }}">
                    @foreach ($this->plans as $plan)
                        <flux:select.option value="{{ $plan->id }}">
                            {{ $plan->name }} — ${{ number_format($plan->price, 2) }}/{{ $plan->interval }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="planId" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
