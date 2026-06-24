<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:button :href="route('admin.tickets')" wire:navigate variant="ghost" size="sm" icon="arrow-left" />
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <flux:heading size="xl">{{ $ticket->subject }}</flux:heading>
                    <flux:badge :color="$ticket->status->color()">{{ $ticket->status->label() }}</flux:badge>
                    <flux:badge :color="$ticket->priority->color()" size="sm">{{ $ticket->priority->label() }}</flux:badge>
                </div>
                <flux:text class="mt-1">
                    {{ $ticket->number }} ·
                    <a href="{{ route('admin.clients.show', $ticket->client_id) }}" wire:navigate class="hover:underline">
                        {{ $ticket->client?->name }}
                    </a>
                </flux:text>
            </div>
        </div>

        @can('tickets.update')
            <flux:button wire:click="openHeaderModal" variant="ghost" icon="pencil-square">
                {{ __('Edit Details') }}
            </flux:button>
        @endcan
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Thread --}}
        <div class="flex flex-col gap-6 lg:col-span-2">
            @foreach ($this->replies as $reply)
                <flux:card class="{{ $reply->is_internal_note ? 'border-amber-300 bg-amber-50 dark:border-amber-700/60 dark:bg-amber-900/10' : '' }}">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <flux:avatar size="sm" :name="$reply->author?->name ?? __('System')" />
                            <div>
                                <flux:heading size="sm">{{ $reply->author?->name ?? __('System') }}</flux:heading>
                                <flux:text size="sm" class="text-zinc-500">{{ $reply->created_at?->format('M j, Y g:i A') }}</flux:text>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($reply->is_internal_note)
                                <flux:badge color="amber" size="sm" icon="lock-closed">{{ __('Internal note') }}</flux:badge>
                            @endif
                            @can('tickets.update')
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmDeleteReply({{ $reply->id }})" />
                            @endcan
                        </div>
                    </div>

                    <flux:separator class="my-4" />

                    <flux:text class="whitespace-pre-line">{{ $reply->body }}</flux:text>

                    @if ($reply->attachments->isNotEmpty())
                        <flux:separator class="my-4" variant="subtle" />
                        <div class="flex flex-col gap-2">
                            @foreach ($reply->attachments as $attachment)
                                <a
                                    href="{{ route('admin.ticket-attachments.download', $attachment) }}"
                                    target="_blank"
                                    class="flex items-center gap-2 text-sm text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    <flux:icon.paper-clip variant="micro" />
                                    {{ $attachment->original_name }}
                                    <span class="text-zinc-400">({{ $attachment->humanSize() }})</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </flux:card>
            @endforeach

            {{-- Reply composer --}}
            @can('tickets.update')
                <flux:card>
                    <form wire:submit="postReply" class="flex flex-col gap-4">
                        <flux:heading size="lg">{{ __('Add Reply') }}</flux:heading>

                        <flux:field>
                            <flux:textarea wire:model="body" rows="4" placeholder="{{ __('Type your reply...') }}" />
                            <flux:error name="body" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Attachments') }}</flux:label>
                            <flux:input type="file" wire:model="attachments" multiple />
                            <flux:description>{{ __('Up to 5 files, 5 MB each (images, pdf, txt, log, csv, zip).') }}</flux:description>
                            <flux:error name="attachments" />
                            <flux:error name="attachments.*" />
                        </flux:field>

                        <div wire:loading wire:target="attachments">
                            <flux:text size="sm" class="text-zinc-500">{{ __('Uploading...') }}</flux:text>
                        </div>

                        <div class="flex items-center justify-between">
                            <flux:field variant="inline">
                                <flux:checkbox wire:model="isInternalNote" />
                                <flux:label>{{ __('Internal note (not part of the conversation)') }}</flux:label>
                            </flux:field>

                            <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Post Reply') }}</flux:button>
                        </div>
                    </form>
                </flux:card>
            @endcan
        </div>

        {{-- Details sidebar --}}
        <flux:card class="lg:col-span-1">
            <flux:heading size="lg">{{ __('Details') }}</flux:heading>
            <flux:separator class="my-4" />
            <dl class="grid grid-cols-1 gap-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Client') }}</dt>
                    <dd class="text-right font-medium">{{ $ticket->client?->name }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Department') }}</dt>
                    <dd class="text-right font-medium">{{ $ticket->department?->name ?? __('—') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Assigned to') }}</dt>
                    <dd class="text-right font-medium">{{ $ticket->assignee?->name ?? __('Unassigned') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Priority') }}</dt>
                    <dd class="text-right font-medium">{{ $ticket->priority->label() }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Status') }}</dt>
                    <dd class="text-right font-medium">{{ $ticket->status->label() }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Opened') }}</dt>
                    <dd class="text-right font-medium">{{ $ticket->created_at?->format('M j, Y') }}</dd>
                </div>
                @if ($ticket->closed_at)
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Closed') }}</dt>
                        <dd class="text-right font-medium">{{ $ticket->closed_at->format('M j, Y') }}</dd>
                    </div>
                @endif
            </dl>
        </flux:card>
    </div>

    {{-- Header edit modal --}}
    <flux:modal wire:model.self="showHeaderModal" class="md:w-[28rem]">
        <form wire:submit="saveHeader" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Edit Ticket') }}</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:select wire:model="status">
                        @foreach ($this->statuses as $statusOption)
                            <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="status" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Priority') }}</flux:label>
                    <flux:select wire:model="priority">
                        @foreach ($this->priorities as $priorityOption)
                            <flux:select.option value="{{ $priorityOption->value }}">{{ $priorityOption->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="priority" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Department') }}</flux:label>
                <flux:select wire:model="departmentId" placeholder="{{ __('Unassigned') }}">
                    <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                    @foreach ($this->departments as $departmentOption)
                        <flux:select.option value="{{ $departmentOption->id }}">{{ $departmentOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="departmentId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Assign to') }}</flux:label>
                <flux:select wire:model="assignedTo" placeholder="{{ __('Unassigned') }}">
                    <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                    @foreach ($this->admins as $adminOption)
                        <flux:select.option value="{{ $adminOption->id }}">{{ $adminOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="assignedTo" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete reply modal --}}
    <flux:modal wire:model.self="showDeleteReplyModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Remove reply?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('The reply and its attachments will be permanently removed.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteReply">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
