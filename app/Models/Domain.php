<?php

namespace App\Models;

use App\Enums\DomainStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What: A domain registration tracked against a client — the manual record of a domain the client owns.
 * Why: Phase 6 has no live registrar API, so registrar and WHOIS are free-text and renewal is logged by hand.
 *      The key dates (`registered_at`, `expires_at`) drive the expiry urgency badge and the Phase 7 reminder
 *      system; `expires_at` is the same trigger field services use, so the expiry helpers mirror
 *      `ClientService`. Tenant isolation is automatic via `BelongsToCompany`.
 * When: Created/edited/renewed from `/admin/domains` (or surfaced read-only on the client profile); read on
 *       the domains list (with expiry urgency) and — in Phase 7 — the daily reminder command.
 *
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property string $domain_name
 * @property string|null $registrar
 * @property DomainStatus $status
 * @property Carbon|null $registered_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_renewed_at
 * @property string|null $renewal_cost
 * @property string $currency
 * @property string|null $ns1
 * @property string|null $ns2
 * @property string|null $ns3
 * @property string|null $ns4
 * @property string|null $whois_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_id',
        'domain_name',
        'registrar',
        'status',
        'registered_at',
        'expires_at',
        'last_renewed_at',
        'renewal_cost',
        'currency',
        'ns1',
        'ns2',
        'ns3',
        'ns4',
        'whois_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'registered_at' => 'date',
            'expires_at' => 'date',
            'last_renewed_at' => 'date',
            'renewal_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * What: Whether the domain's expiry date has already passed.
     * Why: Drives the "expired" danger styling and the dashboard/reminder "already expired" lists.
     * When: Read on the domains list, the client profile, and expiry filtering.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * What: Whole days from today until expiry (negative if already expired, null if no expiry).
     * Why: Both the urgency colour and the Phase 7 reminder thresholds key off this number.
     * When: Read when rendering expiry badges and when filtering "expiring soon".
     */
    public function daysUntilExpiry(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false);
    }

    /**
     * What: The Flux badge colour signalling expiry urgency (red ≤7 days or expired, yellow ≤30, else green).
     * Why: Gives admins an at-a-glance sense of which domains need renewing — the same colour-coded urgency
     *      used for services. Domains with no expiry date are neutral (zinc).
     * When: Passed to `flux:badge :color` on the domains list and client profile.
     */
    public function urgencyColor(): string
    {
        $days = $this->daysUntilExpiry();

        if ($days === null) {
            return 'zinc';
        }

        return match (true) {
            $days <= 7 => 'red',
            $days <= 30 => 'yellow',
            default => 'green',
        };
    }

    /**
     * What: Configure the spatie activity log for domains.
     * Why: Domain dates, status and renewal data are audit-sensitive (they drive renewals and reminders).
     * When: Invoked automatically by the LogsActivity trait on create/update/delete.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'client_id', 'domain_name', 'registrar', 'status', 'registered_at', 'expires_at',
                'last_renewed_at', 'renewal_cost', 'currency', 'ns1', 'ns2', 'ns3', 'ns4',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
