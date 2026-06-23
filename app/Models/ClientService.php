<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\ServiceStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ClientServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What: A client's subscription to a product — the operational record that tracks status, billing cycle,
 *       a price snapshot, and the key dates (`starts_at`, `expires_at`, `next_due_date`).
 * Why: This is where billing, renewals and expiry reminders live. Price and cycle are snapshotted on the
 *      row (not read live from the product) so a later catalog change never rewrites history, and
 *      `product_id` is nullable so deleting a product leaves the service intact. Tenant isolation is
 *      automatic via `BelongsToCompany`.
 * When: Created from the Services screen (or the client profile); read on the services list (with expiry
 *       urgency), the client profile, and — in Phase 7 — the daily reminder command.
 *
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property int|null $product_id
 * @property string|null $label
 * @property ServiceStatus $status
 * @property BillingCycle $billing_cycle
 * @property string $price
 * @property string $currency
 * @property Carbon $starts_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $next_due_date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ClientService extends Model
{
    /** @use HasFactory<ClientServiceFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_id',
        'product_id',
        'label',
        'status',
        'billing_cycle',
        'price',
        'currency',
        'starts_at',
        'expires_at',
        'next_due_date',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ServiceStatus::class,
            'billing_cycle' => BillingCycle::class,
            'price' => 'decimal:2',
            'starts_at' => 'date',
            'expires_at' => 'date',
            'next_due_date' => 'date',
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * What: Whether the service's expiry date has already passed.
     * Why: Drives the "expired" danger styling and the dashboard/reminder "already expired" lists.
     * When: Read on the services list, the client profile, and expiry filtering.
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
     * Why: Gives admins an at-a-glance sense of which services need renewing — the Phase 3 "color-coded
     *      urgency" requirement. Services with no expiry date are neutral (zinc).
     * When: Passed to `flux:badge :color` on the services list and client profile.
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
     * What: Configure the spatie activity log for client services.
     * Why: Service lifecycle, dates and pricing are the most audit-sensitive billing data; track them.
     * When: Invoked automatically by the LogsActivity trait on create/update/delete.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'client_id', 'product_id', 'label', 'status', 'billing_cycle',
                'price', 'currency', 'starts_at', 'expires_at', 'next_due_date',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
