<?php

namespace App\Livewire\Saas\Companies;

use App\Models\Company;
use App\Models\SaasPlan;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What: SaaS Admin detail screen for a single tenant company.
 * Why: Centralises every lifecycle action the platform owner performs on a tenant — editing contact
 *      details, assigning/changing the subscription (plan, status, billing window, trial), suspending,
 *      soft-deleting, and impersonating one of its admins. Guarded by `saas_admin` middleware.
 * When: Rendered at `/saas/companies/{company}`.
 */
#[Title('Company')]
class Show extends Component
{
    public Company $company;

    // --- Contact details form ---
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    // --- Subscription form ---
    public ?int $planId = null;

    public string $status = 'trialing';

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public ?string $trialEndsAt = null;

    // --- Delete modal ---
    public bool $showDeleteModal = false;

    public string $deleteConfirmation = '';

    public function mount(Company $company): void
    {
        $this->company = $company;
        $this->fillForms();
    }

    /**
     * What: Hydrate both forms from the current company + subscription state.
     * Why: Keeps the edit fields in sync after every persisted action without re-fetching the model.
     * When: On mount and after each save.
     */
    protected function fillForms(): void
    {
        $this->name = $this->company->name;
        $this->email = (string) $this->company->email;
        $this->phone = (string) $this->company->phone;
        $this->address = (string) $this->company->address;
        $this->trialEndsAt = $this->company->trial_ends_at?->toDateString();

        $subscription = $this->company->subscription;
        $this->planId = $subscription?->saas_plan_id ?? $this->company->plan_id;
        $this->status = $subscription?->status ?? 'trialing';
        $this->startsAt = $subscription?->starts_at?->toDateString();
        $this->endsAt = $subscription?->ends_at?->toDateString();
    }

    /**
     * What: Persist edits to the company's contact details.
     * When: Submit of the contact details form.
     */
    public function saveDetails(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'trialEndsAt' => ['nullable', 'date'],
        ]);

        $this->company->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'address' => $validated['address'] ?: null,
            'trial_ends_at' => $validated['trialEndsAt'] ?: null,
        ]);

        Flux::toast(variant: 'success', text: __('Company details updated.'));
        $this->fillForms();
    }

    /**
     * What: Assign or change the company's subscription, keeping `companies.plan_id` in sync.
     * Why: The subscription row is the source of truth for tenant access (`EnsureCompanyAdmin`), while
     *      `companies.plan_id` is a denormalised pointer used elsewhere; writing both together prevents
     *      drift. A company without a subscription gets one created on first assignment.
     * When: Submit of the subscription form.
     */
    public function saveSubscription(): void
    {
        $validated = $this->validate([
            'planId' => ['required', 'exists:saas_plans,id'],
            'status' => ['required', 'in:trialing,active,past_due,cancelled'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
        ]);

        $attributes = [
            'saas_plan_id' => $validated['planId'],
            'status' => $validated['status'],
            'starts_at' => $validated['startsAt'] ?: null,
            'ends_at' => $validated['endsAt'] ?: null,
        ];

        $subscription = $this->company->subscription;

        if ($subscription !== null) {
            $subscription->update($attributes);
        } else {
            $this->company->subscriptions()->create($attributes);
        }

        $this->company->update(['plan_id' => $validated['planId']]);
        $this->company->refresh();

        Flux::toast(variant: 'success', text: __('Subscription updated.'));
        $this->fillForms();
    }

    /**
     * What: Toggle the company's suspended state.
     * Why: Locks a tenant out of the panel without destroying its data; `EnsureCompanyAdmin` reads it.
     * When: Suspend/Reactivate button in the header.
     */
    public function toggleSuspend(): void
    {
        $this->company->update([
            'suspended_at' => $this->company->isSuspended() ? null : now(),
        ]);

        $this->company->refresh();

        Flux::toast(text: $this->company->isSuspended() ? __('Company suspended.') : __('Company reactivated.'));
    }

    /**
     * What: Soft-delete the company after a typed-name confirmation.
     * Why: SoftDeletes preserves all tenant data while hiding it from the platform; the typed name
     *      guards against accidental destruction of a live tenant.
     * When: Confirm of the delete modal.
     */
    public function delete(): void
    {
        if ($this->deleteConfirmation !== $this->company->name) {
            $this->addError('deleteConfirmation', __('The name does not match.'));

            return;
        }

        $this->company->delete();

        Flux::toast(variant: 'success', text: __('Company deleted.'));

        $this->redirectRoute('saas.companies', navigate: true);
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
        return view('livewire.saas.companies.show', [
            'admins' => $this->company->users()->orderBy('name')->get(),
        ]);
    }
}
