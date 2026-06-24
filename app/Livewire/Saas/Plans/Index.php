<?php

namespace App\Livewire\Saas\Plans;

use App\Models\SaasPlan;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What: SaaS Admin screen to manage the platform's subscription plan catalog.
 * Why: Plans are the sellable tiers assigned to tenants; their `limits` JSON (max clients/admins)
 *      defines per-tenant capacity. This is the only place the platform owner defines pricing and
 *      feature ceilings. Guarded by the `saas_admin` middleware — no tenant scoping applies.
 * When: Rendered at `/saas/plans` for the platform owner.
 */
#[Title('Plans')]
class Index extends Component
{
    use WithPagination;

    // --- Form modal state ---
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $price = '0';

    public string $interval = 'monthly';

    public string $maxClients = '';

    public string $maxAdmins = '';

    public bool $isActive = true;

    // --- Delete modal state ---
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'interval' => ['required', 'in:monthly,annual'],
            'maxClients' => ['nullable', 'integer', 'min:0'],
            'maxAdmins' => ['nullable', 'integer', 'min:0'],
            'isActive' => ['boolean'],
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $planId): void
    {
        $plan = SaasPlan::findOrFail($planId);

        $this->editingId = $plan->id;
        $this->name = $plan->name;
        $this->price = (string) $plan->price;
        $this->interval = $plan->interval;
        $this->maxClients = $this->limitToInput($plan->limits['max_clients'] ?? null);
        $this->maxAdmins = $this->limitToInput($plan->limits['max_admins'] ?? null);
        $this->isActive = $plan->is_active;

        $this->resetValidation();
        $this->showFormModal = true;
    }

    /**
     * What: Persist the plan — creating a new tier or updating the one being edited.
     * Why: `limits` is a JSON map; blank capacity inputs mean "unlimited" (stored as null). Slugs are
     *      generated once on create and kept stable so existing subscriptions never lose their link.
     * When: Triggered on submit of the form modal.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $attributes = [
            'name' => $validated['name'],
            'price' => $validated['price'],
            'interval' => $validated['interval'],
            'limits' => [
                'max_clients' => $this->inputToLimit($validated['maxClients'] ?? null),
                'max_admins' => $this->inputToLimit($validated['maxAdmins'] ?? null),
            ],
            'is_active' => $validated['isActive'],
        ];

        if ($this->editingId !== null) {
            $plan = SaasPlan::findOrFail($this->editingId);
            $plan->update($attributes);
            Flux::toast(variant: 'success', text: __('Plan updated.'));
        } else {
            SaasPlan::create([...$attributes, 'slug' => $this->uniqueSlug($validated['name'])]);
            Flux::toast(variant: 'success', text: __('Plan created.'));
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function confirmDelete(int $planId): void
    {
        $this->deletingId = $planId;
        $this->showDeleteModal = true;
    }

    /**
     * What: Permanently delete a plan, but only when no tenant is subscribed to it.
     * Why: Deleting a plan with live subscriptions would orphan tenants and break MRR/access checks;
     *      the platform owner should deactivate such a plan instead. The guard enforces that.
     * When: Triggered on confirm of the delete modal.
     */
    public function delete(): void
    {
        $plan = SaasPlan::withCount('subscriptions')->findOrFail($this->deletingId);

        if ($plan->subscriptions_count > 0) {
            $this->showDeleteModal = false;
            $this->deletingId = null;
            Flux::toast(variant: 'danger', text: __('Cannot delete a plan with active subscriptions. Deactivate it instead.'));

            return;
        }

        $plan->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: __('Plan deleted.'));
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'maxClients', 'maxAdmins']);
        $this->price = '0';
        $this->interval = 'monthly';
        $this->isActive = true;
        $this->resetValidation();
    }

    /**
     * What: Render a stored limit (int or null) as a form input string.
     * Why: Null means "unlimited" and shows as an empty field.
     * When: When opening the edit modal.
     */
    protected function limitToInput(?int $limit): string
    {
        return $limit === null ? '' : (string) $limit;
    }

    /**
     * What: Convert a form input string back to a stored limit.
     * Why: An empty field persists as null ("unlimited"); a numeric field as an int.
     * When: When saving the form.
     */
    protected function inputToLimit(int|string|null $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    /**
     * What: Generate a plan slug guaranteed unique across plans.
     * Why: `saas_plans.slug` must be unique; common names like "Pro" would otherwise collide.
     * When: Called when creating a plan.
     */
    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (SaasPlan::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @return LengthAwarePaginator<int, SaasPlan>
     */
    #[Computed]
    public function plans(): LengthAwarePaginator
    {
        return SaasPlan::query()
            ->withCount('subscriptions')
            ->orderBy('price')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.saas.plans.index');
    }
}
