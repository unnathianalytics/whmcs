<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * What: Makes a model automatically isolated to the authenticated user's tenant (`company_id`).
 * Why: This is a single-database, `company_id`-scoped SaaS. Rather than repeating
 *      `where('company_id', auth()->user()->company_id)` on every tenant query (error-prone, easy to
 *      forget and leak data across tenants), this trait adds a global scope that filters reads and a
 *      `creating` hook that stamps the column on writes. Every tenant-scoped model uses it.
 * When: Booted automatically when a consuming model boots; the scope/stamp apply only when a non-SaaS
 *       user is authenticated. Outside an authenticated tenant context (console, seeders, queues,
 *       SaaS admin) the scope no-ops so those callers must scope explicitly — preventing the scope
 *       from silently hiding all rows during seeding or maintenance.
 *
 * @property int $company_id
 */
trait BelongsToCompany
{
    /**
     * What: Register the tenant global scope and the create-time auto-fill.
     * Why: Centralises isolation so consuming models need no per-query boilerplate.
     * When: Called once per model lifecycle by Eloquent's `bootTraits()`.
     */
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            if (($companyId = self::resolveCompanyId()) !== null) {
                $builder->where($builder->getModel()->getTable().'.company_id', $companyId);
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('company_id') === null && ($companyId = self::resolveCompanyId()) !== null) {
                $model->setAttribute('company_id', $companyId);
            }
        });
    }

    /**
     * What: The tenant id to scope by, or null when there is no tenant context.
     * Why: Only authenticated company admins (not SaaS admins, not guests, not the console) should be
     *      auto-scoped; everyone else must scope explicitly so we never silently filter all rows.
     * When: Evaluated on every query build and every create for a consuming model.
     */
    protected static function resolveCompanyId(): ?int
    {
        $user = Auth::user();

        if ($user === null || $user->isSaasAdmin()) {
            return null;
        }

        return $user->company_id;
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
