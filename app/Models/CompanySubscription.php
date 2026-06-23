<?php

namespace App\Models;

use Database\Factories\CompanySubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What: A company's assignment to a SaaS plan, with its billing window and lifecycle status.
 * Why: Kept separate from `companies.plan_id` so the platform can track status transitions and
 *      billing dates over time; `isActive()` is the single source of truth for "may this tenant
 *      use the panel right now?".
 * When: Created when a plan is assigned; `status` read by `EnsureCompanyAdmin` middleware.
 *
 * @property int $id
 * @property int $company_id
 * @property int $saas_plan_id
 * @property string $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class CompanySubscription extends Model
{
    /** @use HasFactory<CompanySubscriptionFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'company_id',
        'saas_plan_id',
        'status',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<SaasPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    /**
     * What: Whether this subscription currently entitles the tenant to use the panel.
     * Why: Both fully active and trialing subscriptions grant access; past_due/cancelled do not.
     * When: Checked by `EnsureCompanyAdmin` on every company-admin request.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing'], true)
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * What: Configure the spatie activity log for this model.
     * Why: Subscription status and billing-date changes are billing-sensitive and must be auditable.
     * When: Invoked automatically by the LogsActivity trait on create/update/delete.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['company_id', 'saas_plan_id', 'status', 'starts_at', 'ends_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
