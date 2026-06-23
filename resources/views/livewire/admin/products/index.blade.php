<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Products') }}</flux:heading>
        <div class="flex gap-2">
            @can('services.create')
                <flux:button variant="ghost" icon="folder-plus" wire:click="openCreateGroupModal">
                    {{ __('New Group') }}
                </flux:button>
                <flux:button variant="primary" icon="plus" wire:click="openCreateProductModal">
                    {{ __('New Product') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search products...') }}"
            class="max-w-sm"
        />

        @if ($this->allGroups->count() > 0)
            <flux:select wire:model.live="group" placeholder="{{ __('All groups') }}" class="max-w-56">
                <flux:select.option value="">{{ __('All groups') }}</flux:select.option>
                @foreach ($this->allGroups as $groupOption)
                    <flux:select.option value="{{ $groupOption->id }}">{{ $groupOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    <div class="flex flex-col gap-6">
        @forelse ($this->groups as $productGroup)
            <flux:card wire:key="group-{{ $productGroup->id }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ $productGroup->name }}</flux:heading>
                            @unless ($productGroup->is_active)
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endunless
                        </div>
                        @if ($productGroup->description)
                            <flux:text size="sm" class="text-zinc-500">{{ $productGroup->description }}</flux:text>
                        @endif
                    </div>

                    <flux:dropdown align="end">
                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                        <flux:menu>
                            @can('services.create')
                                <flux:menu.item icon="plus" wire:click="openCreateProductModal({{ $productGroup->id }})">
                                    {{ __('Add product') }}
                                </flux:menu.item>
                            @endcan
                            @can('services.update')
                                <flux:menu.item icon="pencil-square" wire:click="openEditGroupModal({{ $productGroup->id }})">
                                    {{ __('Edit group') }}
                                </flux:menu.item>
                            @endcan
                            @can('services.delete')
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeleteGroup({{ $productGroup->id }})">
                                    {{ __('Delete group') }}
                                </flux:menu.item>
                            @endcan
                        </flux:menu>
                    </flux:dropdown>
                </div>

                <flux:separator class="my-4" />

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Product') }}</flux:table.column>
                        <flux:table.column>{{ __('Pricing') }}</flux:table.column>
                        <flux:table.column>{{ __('Setup fee') }}</flux:table.column>
                        <flux:table.column>{{ __('Services') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($productGroup->products as $product)
                            <flux:table.row :key="'product-' . $product->id">
                                <flux:table.cell>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ $product->name }}</span>
                                        @if ($product->description)
                                            <flux:text size="sm">{{ $product->description }}</flux:text>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    @forelse ($product->pricings as $pricing)
                                        <div class="text-sm">
                                            {{ $pricing->currency }} {{ number_format((float) $pricing->price, 2) }}
                                            <flux:text size="sm" class="text-zinc-500">/ {{ $pricing->cycle->label() }}</flux:text>
                                        </div>
                                    @empty
                                        <flux:text size="sm" class="text-zinc-500">{{ __('No pricing') }}</flux:text>
                                    @endforelse
                                </flux:table.cell>

                                <flux:table.cell>{{ number_format((float) $product->setup_fee, 2) }}</flux:table.cell>
                                <flux:table.cell>{{ $product->services_count }}</flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge :color="$product->is_active ? 'green' : 'zinc'" size="sm">
                                        {{ $product->is_active ? __('Active') : __('Inactive') }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell align="end">
                                    <flux:dropdown align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                                        <flux:menu>
                                            @can('services.update')
                                                <flux:menu.item icon="pencil-square" wire:click="openEditProductModal({{ $product->id }})">
                                                    {{ __('Edit') }}
                                                </flux:menu.item>
                                            @endcan
                                            @can('services.delete')
                                                <flux:menu.separator />
                                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeleteProduct({{ $product->id }})">
                                                    {{ __('Delete') }}
                                                </flux:menu.item>
                                            @endcan
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6">
                                    <flux:text class="text-center">{{ __('No products in this group yet.') }}</flux:text>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @empty
            <flux:card>
                <flux:text class="text-center">{{ __('No product groups yet. Create one to get started.') }}</flux:text>
            </flux:card>
        @endforelse
    </div>

    {{-- Group create / edit modal --}}
    <flux:modal wire:model.self="showGroupModal" class="md:w-96">
        <form wire:submit="saveGroup" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $editingGroupId ? __('Edit Group') : __('New Group') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Categorise your products.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="groupName" autofocus />
                <flux:error name="groupName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:input wire:model="groupDescription" />
                <flux:error name="groupDescription" />
            </flux:field>

            <flux:field variant="inline">
                <flux:checkbox wire:model="groupIsActive" />
                <flux:label>{{ __('Active') }}</flux:label>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $editingGroupId ? __('Save') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Product create / edit modal --}}
    <flux:modal wire:model.self="showProductModal" class="md:w-[36rem]">
        <form wire:submit="saveProduct" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $editingProductId ? __('Edit Product') : __('New Product') }}</flux:heading>
                <flux:text class="mt-2">{{ __('A plan clients can subscribe to.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Group') }}</flux:label>
                <flux:select wire:model="productGroupId">
                    @foreach ($this->allGroups as $groupOption)
                        <flux:select.option value="{{ $groupOption->id }}">{{ $groupOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="productGroupId" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Name') }}</flux:label>
                    <flux:input wire:model="productName" />
                    <flux:error name="productName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Setup fee') }}</flux:label>
                    <flux:input wire:model="setupFee" type="number" step="0.01" min="0" />
                    <flux:error name="setupFee" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:input wire:model="productDescription" />
                <flux:error name="productDescription" />
            </flux:field>

            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <flux:label>{{ __('Pricing') }}</flux:label>
                    <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addPricingRow">
                        {{ __('Add cycle') }}
                    </flux:button>
                </div>
                <flux:error name="pricings" />

                @foreach ($pricings as $index => $pricing)
                    <div class="flex items-end gap-2" wire:key="pricing-{{ $index }}">
                        <flux:field class="flex-1">
                            <flux:label>{{ __('Cycle') }}</flux:label>
                            <flux:select wire:model="pricings.{{ $index }}.cycle">
                                @foreach ($this->cycles as $cycleOption)
                                    <flux:select.option value="{{ $cycleOption->value }}">{{ $cycleOption->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="pricings.{{ $index }}.cycle" />
                        </flux:field>

                        <flux:field class="flex-1">
                            <flux:label>{{ __('Price') }}</flux:label>
                            <flux:input wire:model="pricings.{{ $index }}.price" type="number" step="0.01" min="0" />
                            <flux:error name="pricings.{{ $index }}.price" />
                        </flux:field>

                        <flux:field class="w-24">
                            <flux:label>{{ __('Currency') }}</flux:label>
                            <flux:input wire:model="pricings.{{ $index }}.currency" maxlength="3" />
                            <flux:error name="pricings.{{ $index }}.currency" />
                        </flux:field>

                        <flux:button type="button" variant="ghost" icon="trash" wire:click="removePricingRow({{ $index }})" />
                    </div>
                @endforeach
            </div>

            <flux:field variant="inline">
                <flux:checkbox wire:model="productIsActive" />
                <flux:label>{{ __('Active') }}</flux:label>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $editingProductId ? __('Save') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model.self="showDeleteModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $deleteType === 'group' ? __('Delete group?') : __('Delete product?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ $deleteType === 'group'
                        ? __('This also removes the products inside it. This action can be reversed by support.')
                        : __('This removes the product and its pricing. Existing client services keep their snapshot.') }}
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
