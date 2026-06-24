<?php

namespace App\Models;

use App\Enums\ReminderResourceType;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ReminderRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What: An admin-defined rule that fires a reminder `days_before` a resource of `resource_type` expires.
 * Why: Reminders are configurable per tenant rather than hard-coded. A rule pairs a trigger interval with
 *      the channels (client / admin) and the email subject/body templates; `is_active` lets admins pause a
 *      rule without losing it. Scoped by `resource_type` only in v1 — no per-product-group targeting. Tenant
 *      isolation is automatic via `BelongsToCompany`.
 * When: Managed from `/admin/reminders`; read by the daily `reminders:send` dispatcher (active rules per
 *       type) and by the manual "send reminder now" action.
 *
 * @property int $id
 * @property int $company_id
 * @property ReminderResourceType $resource_type
 * @property int $days_before
 * @property string $subject
 * @property string $body
 * @property bool $notify_client
 * @property bool $notify_admin
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReminderRule extends Model
{
    /** @use HasFactory<ReminderRuleFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'resource_type',
        'days_before',
        'subject',
        'body',
        'notify_client',
        'notify_admin',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resource_type' => ReminderResourceType::class,
            'days_before' => 'integer',
            'notify_client' => 'boolean',
            'notify_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * What: Scope to active rules of a given resource type.
     * Why: The daily dispatcher loads exactly these per company; keeps the query in one place.
     * When: Called by the reminder dispatcher when evaluating each resource type.
     *
     * @param  Builder<ReminderRule>  $query
     * @return Builder<ReminderRule>
     */
    public function scopeActiveFor(Builder $query, ReminderResourceType $type): Builder
    {
        return $query->where('is_active', true)->where('resource_type', $type);
    }

    /**
     * What: Configure the spatie activity log for reminder rules.
     * Why: Rule changes alter what notifications clients receive, so they warrant an audit trail.
     * When: Invoked automatically by the LogsActivity trait on create/update/delete.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'resource_type', 'days_before', 'subject', 'body',
                'notify_client', 'notify_admin', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
