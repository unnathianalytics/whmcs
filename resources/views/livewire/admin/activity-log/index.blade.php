<div class="flex h-full w-full flex-1 flex-col gap-6">
    <flux:heading size="xl">{{ __('Activity Log') }}</flux:heading>

    <div class="flex flex-wrap gap-3">
        <flux:select wire:model.live="event" class="max-w-48" placeholder="{{ __('All events') }}">
            <flux:select.option value="">{{ __('All events') }}</flux:select.option>
            <flux:select.option value="created">{{ __('Created') }}</flux:select.option>
            <flux:select.option value="updated">{{ __('Updated') }}</flux:select.option>
            <flux:select.option value="deleted">{{ __('Deleted') }}</flux:select.option>
            <flux:select.option value="restored">{{ __('Restored') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="causerId" class="max-w-56" placeholder="{{ __('All admins') }}">
            <flux:select.option value="">{{ __('All admins') }}</flux:select.option>
            @foreach ($this->causers as $causer)
                <flux:select.option value="{{ $causer->id }}">{{ $causer->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->activities">
        <flux:table.columns>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Admin') }}</flux:table.column>
            <flux:table.column>{{ __('Event') }}</flux:table.column>
            <flux:table.column>{{ __('Subject') }}</flux:table.column>
            <flux:table.column>{{ __('Description') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->activities as $activity)
                <flux:table.row :key="$activity->id">
                    <flux:table.cell class="whitespace-nowrap text-zinc-500">
                        {{ $activity->created_at?->format('M j, Y H:i') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $activity->causer?->name ?? __('System') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($activity->event)
                            <flux:badge size="sm" :color="match ($activity->event) {
                                'created' => 'green',
                                'updated' => 'blue',
                                'deleted' => 'red',
                                'restored' => 'amber',
                                default => 'zinc',
                            }">{{ ucfirst($activity->event) }}</flux:badge>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">
                        @if ($activity->subject_type)
                            {{ class_basename($activity->subject_type) }}#{{ $activity->subject_id }}
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="max-w-sm truncate">{{ $activity->description }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-400">
                        {{ __('No activity recorded yet.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
