<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What: A tenant record — the isolation boundary for every company-scoped resource.
 * Why: All tenant data (users, clients, invoices, roles) is scoped by `company_id`; suspension and
 *      trial state on this record drive panel access via `EnsureCompanyAdmin` middleware.
 * When: Created by the SaaS Admin when onboarding a tenant; read on every authenticated company-admin
 *       request to resolve the spatie permission team id and check access.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property int|null $plan_id
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $suspended_at
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'plan_id',
        'trial_ends_at',
        'suspended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SaasPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'plan_id');
    }

    /**
     * @return HasOne<CompanySubscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(CompanySubscription::class)->latestOfMany();
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Client, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * @return HasMany<ProductGroup, $this>
     */
    public function productGroups(): HasMany
    {
        return $this->hasMany(ProductGroup::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<ClientService, $this>
     */
    public function clientServices(): HasMany
    {
        return $this->hasMany(ClientService::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<TaxRate, $this>
     */
    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<TicketDepartment, $this>
     */
    public function ticketDepartments(): HasMany
    {
        return $this->hasMany(TicketDepartment::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * What: Whether the company is currently suspended.
     * Why: Suspended tenants must be locked out of the panel without deleting their data.
     * When: Checked by `EnsureCompanyAdmin` on every company-admin request.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * What: Whether the company is within an active trial window.
     * Why: Trialing tenants get full access before a paid subscription is required.
     * When: Used alongside the subscription status to decide panel access.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * What: Configure the spatie activity log for this model.
     * Why: Tenant lifecycle changes (suspension, plan, contact details) need an audit trail.
     * When: Invoked automatically by the LogsActivity trait on create/update/delete.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'email', 'phone', 'plan_id', 'trial_ends_at', 'suspended_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
