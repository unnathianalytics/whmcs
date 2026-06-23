<?php

namespace App\Livewire\Saas;

use App\Models\Company;
use App\Models\CompanySubscription;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What: The SaaS Admin landing dashboard — platform-wide tenancy and revenue overview.
 * Why: Gives the platform owner an at-a-glance read on tenant health (active, trialing, churned)
 *      and recurring revenue, independent of any single company's data.
 * When: Rendered at `/saas` for authenticated SaaS admins.
 */
#[Title('SaaS Dashboard')]
class Dashboard extends Component
{
    /**
     * What: Count of tenants with a live (active or trialing) subscription.
     * Why: The headline "how many paying/onboarding tenants do we have" metric.
     * When: Read by the dashboard stat cards on render.
     */
    #[Computed]
    public function activeTenants(): int
    {
        return Company::whereHas('subscription', function ($query): void {
            $query->whereIn('status', ['active', 'trialing']);
        })->count();
    }

    /**
     * What: Count of tenants whose latest subscription is cancelled.
     * Why: A simple churn signal for the platform owner.
     * When: Read by the dashboard stat cards on render.
     */
    #[Computed]
    public function churnedTenants(): int
    {
        return Company::whereHas('subscription', function ($query): void {
            $query->where('status', 'cancelled');
        })->count();
    }

    /**
     * What: Monthly recurring revenue across all active subscriptions.
     * Why: The core SaaS health metric; annual plans are normalised to a monthly figure.
     * When: Read by the dashboard stat cards on render.
     */
    #[Computed]
    public function monthlyRecurringRevenue(): float
    {
        return CompanySubscription::query()
            ->where('status', 'active')
            ->with('plan')
            ->get()
            ->sum(function (CompanySubscription $subscription): float {
                $plan = $subscription->plan;

                if ($plan === null) {
                    return 0.0;
                }

                return $plan->interval === 'annual'
                    ? (float) $plan->price / 12
                    : (float) $plan->price;
            });
    }

    #[Computed]
    public function totalCompanies(): int
    {
        return Company::count();
    }

    public function render()
    {
        return view('livewire.saas.dashboard');
    }
}
