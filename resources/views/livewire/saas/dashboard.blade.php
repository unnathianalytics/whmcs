<div class="flex h-full w-full flex-1 flex-col gap-6">
    <flux:heading size="xl">{{ __('SaaS Dashboard') }}</flux:heading>

    <div class="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
        <flux:card class="flex flex-col gap-2">
            <flux:text>{{ __('Active Tenants') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->activeTenants) }}</flux:heading>
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:text>{{ __('Trialing') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->trialingTenants) }}</flux:heading>
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:text>{{ __('Churned Tenants') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->churnedTenants) }}</flux:heading>
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:text>{{ __('MRR') }}</flux:text>
            <flux:heading size="xl">${{ number_format($this->monthlyRecurringRevenue, 2) }}</flux:heading>
        </flux:card>
    </div>

    <flux:card>
        <div class="flex items-center justify-between">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">{{ __('Recent Tenants') }}</flux:heading>
                <flux:text>{{ __('Total companies') }}: {{ number_format($this->totalCompanies) }}</flux:text>
            </div>
            <flux:button variant="primary" icon="building-office-2" :href="route('saas.companies')" wire:navigate>
                {{ __('Manage Companies') }}
            </flux:button>
        </div>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Plan') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->recentCompanies as $company)
                    <flux:table.row :key="$company->id">
                        <flux:table.cell variant="strong">{{ $company->name }}</flux:table.cell>
                        <flux:table.cell>{{ $company->plan?->name ?? __('—') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($company->isSuspended())
                                <flux:badge color="red" size="sm">{{ __('Suspended') }}</flux:badge>
                            @elseif ($company->subscription?->status === 'cancelled')
                                <flux:badge color="zinc" size="sm">{{ __('Cancelled') }}</flux:badge>
                            @elseif ($company->onTrial() || $company->subscription?->status === 'trialing')
                                <flux:badge color="yellow" size="sm">{{ __('Trial') }}</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="arrow-right" :href="route('saas.companies.show', $company)" wire:navigate>
                                {{ __('Manage') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <flux:text class="text-center">{{ __('No tenants yet.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
