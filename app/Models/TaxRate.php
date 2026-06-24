<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\TaxRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What: A reusable, named tax percentage in a tenant's catalog (e.g. "GST 18%").
 * Why: Phase 4 applies tax per invoice line item; admins maintain a catalog so they pick a rate per line
 *      instead of retyping percentages. The percent is snapshotted onto the invoice item at line time, so
 *      this record only seeds new lines and editing it never rewrites historical invoices. Tenant isolation
 *      is automatic via `BelongsToCompany`.
 * When: Managed from `/admin/tax-rates`; referenced when adding a line item on the invoice builder.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $rate
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TaxRate extends Model
{
    /** @use HasFactory<TaxRateFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'rate',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * What: Configure the spatie activity log for tax rates.
     * Why: Tax configuration affects every invoice total; track the editable fields for audit.
     * When: Invoked automatically by the LogsActivity trait on create/update/delete.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'rate', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
