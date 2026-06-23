<div class="flex h-full w-full flex-1 flex-col gap-6">
    <flux:heading size="xl">{{ __('SaaS Dashboard') }}</flux:heading>

    <div class="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
        <flux:card class="flex flex-col gap-2">
            <flux:text>{{ __('Active Tenants') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->activeTenants) }}</flux:heading>
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:text>{{ __('Total Companies') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->totalCompanies) }}</flux:heading>
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
            <flux:heading size="lg">{{ __('Tenants') }}</flux:heading>
            <flux:button variant="primary" icon="building-office-2" :href="route('saas.companies')" wire:navigate>
                {{ __('Manage Companies') }}
            </flux:button>
        </div>
        <flux:text class="mt-2">{{ __('Revenue and churn charts will appear here in a later phase.') }}</flux:text>
    </flux:card>
</div>
