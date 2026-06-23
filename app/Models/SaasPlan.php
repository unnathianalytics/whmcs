<?php

namespace App\Models;

use Database\Factories\SaasPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What: A platform-level subscription tier the SaaS Admin offers to tenant companies.
 * Why: Centralises pricing and feature limits so tenancy entitlements are data-driven rather than
 *      hard-coded; the `limits` JSON gates per-company capacity (clients, admins, etc.).
 * When: Created/edited from the SaaS Admin area and assigned to companies via subscriptions.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $price
 * @property string $interval
 * @property array<string, mixed>|null $limits
 * @property bool $is_active
 */
class SaasPlan extends Model
{
    /** @use HasFactory<SaasPlanFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'interval',
        'limits',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'limits' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CompanySubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class);
    }

    /**
     * What: Configure the spatie activity log for this model.
     * Why: Plan changes affect every tenant on the plan, so they must be auditable.
     * When: Invoked automatically by the LogsActivity trait on create/update/delete.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'price', 'interval', 'limits', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
