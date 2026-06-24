<?php

namespace App\Livewire\Admin;

use App\Enums\DomainStatus;
use App\Enums\ServiceStatus;
use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Company;
use App\Models\Domain;
use App\Models\Ticket;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What: The company-admin landing dashboard — a per-tenant operational overview wired to live data.
 * Why: Gives a company admin an at-a-glance read on their own clients, services, tickets and revenue,
 *      plus the expiry watchlists that drive renewals and a six-month revenue trend. Every query runs
 *      under the BelongsToCompany global scope, so all numbers are this tenant's only.
 * When: Rendered at `/admin` for authenticated company admins.
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    /** Days ahead the "expiring soon" watchlist looks. */
    private const EXPIRY_WINDOW_DAYS = 7;

    /**
     * What: The tenant this dashboard belongs to.
     * Why: Surfaces the company name in the header.
     * When: Read on render.
     */
    #[Computed]
    public function company(): ?Company
    {
        return Auth::user()->company;
    }

    /**
     * What: Headline metrics for the company.
     * Why: The four stat cards: total clients, active services, open tickets, revenue this month.
     * When: Read by the stat cards on render.
     *
     * @return array<string, int|float>
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'clients' => Client::query()->count(),
            'active_services' => ClientService::query()->where('status', ServiceStatus::Active)->count(),
            'open_tickets' => Ticket::query()->where('status', '!=', TicketStatus::Closed)->count(),
            'revenue_this_month' => (float) Transaction::query()
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
        ];
    }

    /**
     * What: Services and domains whose `expires_at` falls within the next 7 days (still Active).
     * Why: The warning watchlist that prompts renewals before anything lapses.
     * When: Read by the "Expiring Soon" card on render.
     *
     * @return Collection<int, array{type: string, name: string, client: ?string, expires_at: Carbon, days: int, color: string}>
     */
    #[Computed]
    public function expiringSoon(): Collection
    {
        $until = now()->startOfDay()->addDays(self::EXPIRY_WINDOW_DAYS)->endOfDay();

        $services = ClientService::query()
            ->with('client')
            ->where('status', ServiceStatus::Active)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->startOfDay(), $until])
            ->get()
            ->map(fn (ClientService $service): array => $this->serviceRow($service));

        $domains = Domain::query()
            ->with('client')
            ->where('status', DomainStatus::Active)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->startOfDay(), $until])
            ->get()
            ->map(fn (Domain $domain): array => $this->domainRow($domain));

        return $services->concat($domains)->sortBy('expires_at')->values();
    }

    /**
     * What: Services and domains already past `expires_at` but still flagged Active.
     * Why: The danger watchlist — overdue renewals needing attention.
     * When: Read by the "Already Expired" card on render.
     *
     * @return Collection<int, array{type: string, name: string, client: ?string, expires_at: Carbon, days: int, color: string}>
     */
    #[Computed]
    public function expired(): Collection
    {
        // Include both Active-but-lapsed and already-Expired statuses — both need renewal attention.
        // Cancelled/Suspended are excluded since they are intentional end states, not overdue renewals.
        $services = ClientService::query()
            ->with('client')
            ->whereIn('status', [ServiceStatus::Active, ServiceStatus::Expired])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->startOfDay())
            ->get()
            ->map(fn (ClientService $service): array => $this->serviceRow($service));

        $domains = Domain::query()
            ->with('client')
            ->whereIn('status', [DomainStatus::Active, DomainStatus::Expired])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->startOfDay())
            ->get()
            ->map(fn (Domain $domain): array => $this->domainRow($domain));

        return $services->concat($domains)->sortBy('expires_at')->values();
    }

    /**
     * What: Monthly paid-revenue totals for the last six calendar months (oldest first).
     * Why: Feeds the Chart.js bar chart; pre-aggregating server-side keeps the view dumb.
     * When: Read by the revenue chart on render.
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    #[Computed]
    public function revenueSeries(): array
    {
        $start = now()->startOfMonth()->subMonths(5);

        $byMonth = Transaction::query()
            ->where('paid_at', '>=', $start)
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (Transaction $transaction): string => $transaction->paid_at->format('Y-m'))
            ->map(fn (Collection $group): float => (float) $group->sum('amount'));

        $labels = [];
        $values = [];

        // `now()` is CarbonImmutable (see AppServiceProvider), so advance by reassigning, not mutating.
        $thisMonth = now()->startOfMonth();
        for ($cursor = $start; $cursor->lte($thisMonth); $cursor = $cursor->addMonth()) {
            $labels[] = $cursor->format('M Y');
            $values[] = round($byMonth->get($cursor->format('Y-m'), 0.0), 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{type: string, name: string, client: ?string, expires_at: Carbon, days: int, color: string}
     */
    private function serviceRow(ClientService $service): array
    {
        return [
            'type' => __('Service'),
            'name' => $service->label,
            'client' => $service->client?->name,
            'expires_at' => $service->expires_at,
            'days' => (int) $service->daysUntilExpiry(),
            'color' => $service->urgencyColor(),
        ];
    }

    /**
     * @return array{type: string, name: string, client: ?string, expires_at: Carbon, days: int, color: string}
     */
    private function domainRow(Domain $domain): array
    {
        return [
            'type' => __('Domain'),
            'name' => $domain->domain_name,
            'client' => $domain->client?->name,
            'expires_at' => $domain->expires_at,
            'days' => (int) $domain->daysUntilExpiry(),
            'color' => $domain->urgencyColor(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.dashboard');
    }
}
