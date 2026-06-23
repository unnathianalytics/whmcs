<div class="flex h-full w-full flex-1 flex-col gap-6">
    <flux:heading size="xl">
        {{ __('Dashboard') }}
        @if ($this->company)
            <span class="text-zinc-400">· {{ $this->company->name }}</span>
        @endif
    </flux:heading>

    <div class="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
        <flux:card class="flex flex-col gap-2">
            <flux:text>{{ __('Total Clients') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->stats['clients']) }}</flux:heading>
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:text>{{ __('Active Services') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->stats['active_services']) }}</flux:heading>
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:text>{{ __('Open Tickets') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->stats['open_tickets']) }}</flux:heading>
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:text>{{ __('Revenue This Month') }}</flux:text>
            <flux:heading size="xl">${{ number_format($this->stats['revenue_this_month'], 2) }}</flux:heading>
        </flux:card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card>
            <flux:heading size="lg" class="flex items-center gap-2">
                <flux:icon.exclamation-triangle variant="micro" class="text-yellow-500" />
                {{ __('Expiring Soon (7 days)') }}
            </flux:heading>
            <flux:text class="mt-2">{{ __('Services and domains nearing expiry will appear here.') }}</flux:text>
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="flex items-center gap-2">
                <flux:icon.x-circle variant="micro" class="text-red-500" />
                {{ __('Already Expired') }}
            </flux:heading>
            <flux:text class="mt-2">{{ __('Overdue renewals needing attention will appear here.') }}</flux:text>
        </flux:card>
    </div>

    <flux:card>
        <flux:heading size="lg">{{ __('Revenue (last 6 months)') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Chart will be wired to real data in a later phase.') }}</flux:text>
    </flux:card>
</div>
