<?php

namespace App\Livewire\Admin;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What: The company-admin landing dashboard — a per-tenant operational overview.
 * Why: Gives a company admin an at-a-glance read on their own clients, services, tickets and
 *      revenue, plus the expiry watchlists that drive renewals. Metrics are placeholders in
 *      Phase 1 (zeros / empty lists) and get wired to real data in later module phases.
 * When: Rendered at `/admin` for authenticated company admins.
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    /**
     * What: The tenant this dashboard belongs to.
     * Why: Surfaces the company name in the header and anchors future scoped queries.
     * When: Read on render.
     */
    #[Computed]
    public function company(): ?Company
    {
        return Auth::user()->company;
    }

    /**
     * What: Placeholder headline metrics for the company.
     * Why: Establishes the dashboard layout now; real counts arrive with the Clients/Services/
     *      Invoices/Tickets modules in later phases.
     * When: Read by the stat cards on render.
     *
     * @return array<string, int|float>
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'clients' => 0,
            'active_services' => 0,
            'open_tickets' => 0,
            'revenue_this_month' => 0.0,
        ];
    }

    public function render()
    {
        return view('livewire.admin.dashboard');
    }
}
