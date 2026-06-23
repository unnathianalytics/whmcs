<?php

namespace App\Livewire\Saas\Companies;

use App\Models\Company;
use App\Models\SaasPlan;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What: SaaS Admin screen to list and create tenant companies.
 * Why: Tenant onboarding is the platform owner's primary action; this is the entry point for
 *      creating a company and its initial subscription. Listing supports search and pagination so
 *      the platform scales past a handful of tenants.
 * When: Rendered at `/saas/companies` for authenticated SaaS admins.
 */
#[Title('Companies')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showCreateModal = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|email|max:255')]
    public string $email = '';

    #[Validate('nullable|exists:saas_plans,id')]
    public ?int $planId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * What: Open the create-company modal with a clean form.
     * Why: Resetting fields prevents stale values leaking between opens.
     * When: Triggered by the "New company" button.
     */
    public function openCreateModal(): void
    {
        $this->reset(['name', 'email', 'planId']);
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    /**
     * What: Persist a new tenant company and a starting trial subscription.
     * Why: A company is only usable with a subscription, so both are created together; the role
     *      seeding for the new tenant is deferred to a dedicated action in a later phase.
     * When: Triggered on submit of the create-company modal form.
     */
    public function createCompany(): void
    {
        $validated = $this->validate();

        $company = Company::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'email' => $validated['email'] ?: null,
            'plan_id' => $this->planId,
            'trial_ends_at' => now()->addDays(14),
        ]);

        if ($this->planId !== null) {
            $company->subscription()->create([
                'saas_plan_id' => $this->planId,
                'status' => 'trialing',
                'starts_at' => now(),
                'ends_at' => now()->addDays(14),
            ]);
        }

        $this->showCreateModal = false;
        $this->reset(['name', 'email', 'planId']);

        Flux::toast(variant: 'success', text: __('Company created.'));
    }

    /**
     * What: Toggle a company's suspended state.
     * Why: Lets the platform owner lock out a tenant (non-payment, abuse) without deleting data.
     * When: Triggered from the suspend/unsuspend action on each table row.
     */
    public function toggleSuspend(int $companyId): void
    {
        $company = Company::findOrFail($companyId);
        $company->update([
            'suspended_at' => $company->isSuspended() ? null : now(),
        ]);

        Flux::toast(text: $company->isSuspended() ? __('Company suspended.') : __('Company reactivated.'));
    }

    /**
     * What: Generate a slug guaranteed unique across companies.
     * Why: `companies.slug` is unique; collisions on common names would otherwise fail insertion.
     * When: Called when creating a company.
     */
    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Company::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @return LengthAwarePaginator<int, Company>
     */
    #[Computed]
    public function companies(): LengthAwarePaginator
    {
        return Company::query()
            ->with(['plan', 'subscription'])
            ->withCount('users')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->latest()
            ->paginate(10);
    }

    /**
     * @return Collection<int, SaasPlan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return SaasPlan::where('is_active', true)->orderBy('price')->get();
    }

    public function render()
    {
        return view('livewire.saas.companies.index');
    }
}
