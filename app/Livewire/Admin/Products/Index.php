<?php

namespace App\Livewire\Admin\Products;

use App\Enums\BillingCycle;
use App\Models\Product;
use App\Models\ProductGroup;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * What: Company-admin screen to manage the product catalog — product groups, their products, and each
 *       product's per-cycle pricing rows.
 * Why: The catalog defines what clients can be subscribed to. All queries are tenant-isolated automatically
 *      by the BelongsToCompany scope, so the component never filters `company_id` by hand. Groups and
 *      products are authorized through the `services.*` permission set.
 * When: Rendered at `/admin/products` for company admins holding `services.view`.
 */
#[Title('Products')]
class Index extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $group = '';

    // --- Group modal state ---
    public bool $showGroupModal = false;

    public ?int $editingGroupId = null;

    public string $groupName = '';

    public string $groupDescription = '';

    public bool $groupIsActive = true;

    // --- Product modal state ---
    public bool $showProductModal = false;

    public ?int $editingProductId = null;

    public string $productGroupId = '';

    public string $productName = '';

    public string $productDescription = '';

    public string $setupFee = '0';

    public bool $productIsActive = true;

    /**
     * Editable pricing rows for the product being created/edited.
     *
     * @var list<array{id: ?int, cycle: string, price: string, currency: string}>
     */
    public array $pricings = [];

    // --- Delete modal state ---
    public bool $showDeleteModal = false;

    public string $deleteType = '';

    public ?int $deletingId = null;

    /**
     * What: Authorize that the admin may view the catalog at all.
     * Why: The screen is gated on `services.view`; without it it 403s rather than leaking data.
     * When: On component mount, before any query runs.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Product::class);
    }

    /**
     * What: Abort with 403 unless the current admin holds the given permission.
     * Why: Product groups have no dedicated policy (they share the Services module's `services.*` set),
     *      so group actions are gated on the permission string directly — consistent with the project's
     *      "gates check permissions, never role names" rule. Products themselves use ProductPolicy.
     * When: Called by the group create/edit/delete actions before mutating data.
     */
    protected function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()->can($permission), 403);
    }

    public function updatingSearch(): void
    {
        // No pagination here; groups render in full. Method kept for parity/intent.
    }

    // =========================================================================
    // Product groups
    // =========================================================================

    public function openCreateGroupModal(): void
    {
        $this->authorizePermission('services.create');
        $this->resetGroupForm();
        $this->showGroupModal = true;
    }

    public function openEditGroupModal(int $groupId): void
    {
        $group = ProductGroup::findOrFail($groupId);
        $this->authorizePermission('services.update');

        $this->editingGroupId = $group->id;
        $this->groupName = $group->name;
        $this->groupDescription = (string) $group->description;
        $this->groupIsActive = $group->is_active;

        $this->resetValidation();
        $this->showGroupModal = true;
    }

    /**
     * What: Persist a product group, creating or updating it; the slug is derived from the name.
     * Why: `company_id` is auto-stamped on create by BelongsToCompany; the slug is unique per tenant.
     * When: Triggered on submit of the group modal.
     */
    public function saveGroup(): void
    {
        $validated = $this->validate([
            'groupName' => ['required', 'string', 'max:255'],
            'groupDescription' => ['nullable', 'string', 'max:255'],
            'groupIsActive' => ['boolean'],
        ]);

        $attributes = [
            'name' => $validated['groupName'],
            'slug' => $this->uniqueGroupSlug($validated['groupName']),
            'description' => $validated['groupDescription'] ?: null,
            'is_active' => $validated['groupIsActive'],
        ];

        if ($this->editingGroupId !== null) {
            $group = ProductGroup::findOrFail($this->editingGroupId);
            $this->authorizePermission('services.update');
            // Keep the existing slug on edit to avoid breaking references.
            unset($attributes['slug']);
            $group->update($attributes);
            Flux::toast(variant: 'success', text: __('Group updated.'));
        } else {
            $this->authorizePermission('services.create');
            ProductGroup::create($attributes);
            Flux::toast(variant: 'success', text: __('Group created.'));
        }

        $this->showGroupModal = false;
        $this->resetGroupForm();
    }

    // =========================================================================
    // Products
    // =========================================================================

    public function openCreateProductModal(?int $groupId = null): void
    {
        $this->authorize('create', Product::class);
        $this->resetProductForm();
        $this->productGroupId = (string) ($groupId ?? $this->groups->first()?->id ?? '');
        $this->showProductModal = true;
    }

    public function openEditProductModal(int $productId): void
    {
        $product = Product::with('pricings')->findOrFail($productId);
        $this->authorize('update', $product);

        $this->editingProductId = $product->id;
        $this->productGroupId = (string) $product->product_group_id;
        $this->productName = $product->name;
        $this->productDescription = (string) $product->description;
        $this->setupFee = (string) $product->setup_fee;
        $this->productIsActive = $product->is_active;

        $this->pricings = $product->pricings
            ->map(fn ($pricing): array => [
                'id' => $pricing->id,
                'cycle' => $pricing->cycle->value,
                'price' => (string) $pricing->price,
                'currency' => $pricing->currency,
            ])
            ->all();

        if ($this->pricings === []) {
            $this->addPricingRow();
        }

        $this->resetValidation();
        $this->showProductModal = true;
    }

    /**
     * What: Add a blank pricing row to the product form.
     * Why: A product may be sold on several cycles; admins add a row per cycle.
     * When: Triggered by the "Add pricing" button in the product modal.
     */
    public function addPricingRow(): void
    {
        $this->pricings[] = [
            'id' => null,
            'cycle' => BillingCycle::Monthly->value,
            'price' => '0',
            'currency' => 'INR',
        ];
    }

    /**
     * What: Remove a pricing row from the product form by index.
     * Why: Lets admins drop a cycle they no longer offer before saving.
     * When: Triggered by the remove action on a pricing row.
     */
    public function removePricingRow(int $index): void
    {
        unset($this->pricings[$index]);
        $this->pricings = array_values($this->pricings);
    }

    /**
     * What: Persist a product and sync its pricing rows in one transaction-like flow.
     * Why: `company_id` is auto-stamped by BelongsToCompany; pricing is replaced to match the form so
     *      removed cycles are deleted and new ones created. Cycles must be unique within the product.
     * When: Triggered on submit of the product modal.
     */
    public function saveProduct(): void
    {
        $validated = $this->validate([
            'productGroupId' => ['required', Rule::exists('product_groups', 'id')],
            'productName' => ['required', 'string', 'max:255'],
            'productDescription' => ['nullable', 'string', 'max:255'],
            'setupFee' => ['required', 'numeric', 'min:0'],
            'productIsActive' => ['boolean'],
            'pricings' => ['array'],
            'pricings.*.cycle' => ['required', Rule::enum(BillingCycle::class)],
            'pricings.*.price' => ['required', 'numeric', 'min:0'],
            'pricings.*.currency' => ['required', 'string', 'size:3'],
        ]);

        $cycles = array_column($validated['pricings'], 'cycle');
        if (count($cycles) !== count(array_unique($cycles))) {
            $this->addError('pricings', __('Each billing cycle can only be priced once.'));

            return;
        }

        $attributes = [
            'product_group_id' => (int) $validated['productGroupId'],
            'name' => $validated['productName'],
            'description' => $validated['productDescription'] ?: null,
            'setup_fee' => $validated['setupFee'],
            'is_active' => $validated['productIsActive'],
        ];

        if ($this->editingProductId !== null) {
            $product = Product::findOrFail($this->editingProductId);
            $this->authorize('update', $product);
            $product->update($attributes);
            Flux::toast(variant: 'success', text: __('Product updated.'));
        } else {
            $this->authorize('create', Product::class);
            $product = Product::create($attributes);
            Flux::toast(variant: 'success', text: __('Product created.'));
        }

        $this->syncPricings($product);

        $this->showProductModal = false;
        $this->resetProductForm();
    }

    /**
     * What: Replace the product's pricing rows with the ones from the form.
     * Why: Keeps the persisted set in lockstep with what the admin sees — added cycles inserted, removed
     *      cycles deleted, kept cycles updated. `company_id` is stamped from the product's tenant.
     * When: Called at the end of `saveProduct`.
     */
    protected function syncPricings(Product $product): void
    {
        $keptIds = [];

        foreach ($this->pricings as $row) {
            $pricing = $product->pricings()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'company_id' => $product->company_id,
                    'cycle' => $row['cycle'],
                    'price' => $row['price'],
                    'currency' => strtoupper($row['currency']),
                ],
            );

            $keptIds[] = $pricing->id;
        }

        $product->pricings()->whereNotIn('id', $keptIds)->delete();
    }

    // =========================================================================
    // Deletion (shared confirm modal for groups & products)
    // =========================================================================

    public function confirmDeleteGroup(int $groupId): void
    {
        $this->authorizePermission('services.delete');
        $this->deleteType = 'group';
        $this->deletingId = $groupId;
        $this->showDeleteModal = true;
    }

    public function confirmDeleteProduct(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $this->authorize('delete', $product);
        $this->deleteType = 'product';
        $this->deletingId = $product->id;
        $this->showDeleteModal = true;
    }

    /**
     * What: Soft-delete the confirmed group or product.
     * Why: Soft delete keeps history recoverable; tenant scope guarantees same-company only. Deleting a
     *      group cascades (DB) to its products; a product's pricings cascade with it.
     * When: Triggered on confirm of the delete modal.
     */
    public function delete(): void
    {
        if ($this->deleteType === 'group') {
            $group = ProductGroup::findOrFail($this->deletingId);
            $this->authorizePermission('services.delete');
            $group->delete();
            Flux::toast(variant: 'success', text: __('Group deleted.'));
        } elseif ($this->deleteType === 'product') {
            $product = Product::findOrFail($this->deletingId);
            $this->authorize('delete', $product);
            $product->delete();
            Flux::toast(variant: 'success', text: __('Product deleted.'));
        }

        $this->showDeleteModal = false;
        $this->deleteType = '';
        $this->deletingId = null;
    }

    // =========================================================================
    // Helpers & computed data
    // =========================================================================

    protected function resetGroupForm(): void
    {
        $this->reset(['editingGroupId', 'groupName', 'groupDescription']);
        $this->groupIsActive = true;
        $this->resetValidation();
    }

    protected function resetProductForm(): void
    {
        $this->reset(['editingProductId', 'productGroupId', 'productName', 'productDescription']);
        $this->setupFee = '0';
        $this->productIsActive = true;
        $this->pricings = [];
        $this->addPricingRow();
        $this->resetValidation();
    }

    /**
     * What: Build a tenant-unique slug for a group from its name.
     * Why: `product_groups` enforces a unique (company_id, slug); append a counter on collision.
     * When: Called when creating a group.
     */
    protected function uniqueGroupSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'group';
        $slug = $base;
        $counter = 1;

        while (ProductGroup::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$counter);
        }

        return $slug;
    }

    /**
     * @return Collection<int, ProductGroup>
     */
    #[Computed]
    public function groups(): Collection
    {
        return ProductGroup::query()
            ->withCount('products')
            ->with(['products' => function ($query): void {
                $query->withCount('services')->with('pricings')->orderBy('name');
            }])
            ->when($this->group !== '', fn ($query) => $query->whereKey($this->group))
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhereHas('products', fn ($p) => $p->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, ProductGroup>
     */
    #[Computed]
    public function allGroups(): Collection
    {
        return ProductGroup::query()->orderBy('name')->get();
    }

    /**
     * @return array<int, BillingCycle>
     */
    #[Computed]
    public function cycles(): array
    {
        return BillingCycle::cases();
    }

    public function render()
    {
        return view('livewire.admin.products.index');
    }
}
