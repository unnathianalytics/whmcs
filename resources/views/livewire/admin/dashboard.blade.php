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

            @forelse ($this->expiringSoon as $item)
                <div class="mt-3 flex items-center justify-between gap-2 border-t border-zinc-100 pt-3 dark:border-zinc-700">
                    <div class="min-w-0">
                        <flux:text class="truncate font-medium">{{ $item['name'] }}</flux:text>
                        <flux:text size="sm" class="text-zinc-400">
                            {{ $item['type'] }}@if ($item['client']) · {{ $item['client'] }}@endif
                        </flux:text>
                    </div>
                    <flux:badge :color="$item['color']" size="sm">
                        {{ $item['days'] }} {{ __('days') }}
                    </flux:badge>
                </div>
            @empty
                <flux:text class="mt-2">{{ __('Nothing expiring in the next 7 days.') }}</flux:text>
            @endforelse
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="flex items-center gap-2">
                <flux:icon.x-circle variant="micro" class="text-red-500" />
                {{ __('Already Expired') }}
            </flux:heading>

            @forelse ($this->expired as $item)
                <div class="mt-3 flex items-center justify-between gap-2 border-t border-zinc-100 pt-3 dark:border-zinc-700">
                    <div class="min-w-0">
                        <flux:text class="truncate font-medium">{{ $item['name'] }}</flux:text>
                        <flux:text size="sm" class="text-zinc-400">
                            {{ $item['type'] }}@if ($item['client']) · {{ $item['client'] }}@endif
                        </flux:text>
                    </div>
                    <flux:badge color="red" size="sm">
                        {{ $item['expires_at']->format('M j, Y') }}
                    </flux:badge>
                </div>
            @empty
                <flux:text class="mt-2">{{ __('No overdue renewals.') }}</flux:text>
            @endforelse
        </flux:card>
    </div>

    <flux:card>
        <flux:heading size="lg">{{ __('Revenue (last 6 months)') }}</flux:heading>

        <div
            wire:ignore
            x-data="{
                chart: null,
                init() {
                    const render = () => {
                        if (this.chart) { this.chart.destroy(); }
                        this.chart = new Chart(this.$refs.canvas, {
                            type: 'bar',
                            data: {
                                labels: @js($this->revenueSeries['labels']),
                                datasets: [{
                                    label: @js(__('Revenue')),
                                    data: @js($this->revenueSeries['values']),
                                    backgroundColor: 'rgba(16, 185, 129, 0.6)',
                                    borderColor: 'rgb(16, 185, 129)',
                                    borderWidth: 1,
                                    borderRadius: 4,
                                }],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: { y: { beginAtZero: true } },
                            },
                        });
                    };

                    if (window.Chart) {
                        render();
                    } else {
                        const script = document.createElement('script');
                        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4';
                        script.onload = render;
                        document.head.appendChild(script);
                    }
                },
            }"
            class="mt-4 h-64"
        >
            <canvas x-ref="canvas"></canvas>
        </div>
    </flux:card>
</div>
