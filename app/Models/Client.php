<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What: A customer account belonging to a tenant company.
 * Why: Clients are the central entity of the panel — services, invoices, tickets and domains all hang
 *      off a client. Tenant isolation is automatic via `BelongsToCompany`, so no query in the company
 *      area needs to filter `company_id` by hand.
 * When: Managed by company admins from `/admin/clients`; created with `company_id` auto-stamped from the
 *       authenticated admin.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $company_name
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postcode
 * @property string|null $country
 * @property string $currency
 * @property string $language
 * @property ClientStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company_name',
        'address',
        'city',
        'state',
        'postcode',
        'country',
        'currency',
        'language',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
        ];
    }

    /**
     * @return HasMany<ClientNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class)->latest();
    }

    /**
     * @return HasMany<ClientService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(ClientService::class)->latest('starts_at');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('issue_date');
    }

    /**
     * What: Configure the spatie activity log for clients.
     * Why: Client record changes are an auditable admin action; track the editable fields only.
     * When: Invoked automatically by the LogsActivity trait on create/update/delete.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'email', 'phone', 'company_name', 'address',
                'city', 'state', 'postcode', 'country', 'currency', 'language', 'status',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
